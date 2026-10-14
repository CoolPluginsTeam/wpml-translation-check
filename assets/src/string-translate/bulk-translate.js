import {
  queueStrings,
  pollUntilDone,
  jobProgressPercent,
} from "../shared/queue-client";
import {
  updatePendingPosts,
  unsetPendingPost,
  updateCompletedPosts,
  updateTranslatePostInfo,
  updateCountInfo,
  updateParentPostsInfo,
  updateProgressStatus,
} from "./redux-store/features/actions";
import { store } from "./redux-store/store";
import { __ } from "@wordpress/i18n";
// NOTE: The post/taxonomy bulk-translate pipeline (initBulkTranslate,
// translateContent, updateContent, bulkTranslateEntries) has been removed
// from this file. This module now only contains string-translation logic.
// Post bulk translation uses assets/src/bulk-translate/bulk-translate.js.

const bulkTranslateEntries = async ({ ids, langs, storeDispatch }) => {
  // This function is kept only for API compatibility but is no-op in the
  // string-translate context. Post/taxonomy bulk translation is handled by
  // assets/src/bulk-translate/bulk-translate.js.
  return { success: false, message: "bulkTranslateEntries is not used for strings" };
};
/**
 * Bulk translate strings for String Translation page.
 * Fetches first page only; initBulkTranslateStrings will fetch further pages in a pipeline (no 10k in memory).
 */
const bulkTranslateStrings = async ({
  langs,
  storeDispatch,
  stringFilters,
  selectedStrings = {},
  stringLanguageStatus = {},
}) => {
  const nonce = automlp_wpml_bulk_translate_object.nonce;
  const stringsByLanguage = {};
  const totalPerLanguage = {};
  const stringKeys = []; // only used for count; we don't put 10k keys in Redux



  for (const lang of langs) {
    let strings = [];
    let total = 0;
  
    const hasLocalSelected =
      selectedStrings &&
      Object.keys(selectedStrings).length > 0 &&
      Array.isArray(stringFilters?.selected_string_ids) &&
      stringFilters.selected_string_ids.length > 0;
  
             // Build rows from selected strings, then filter out already-translated for this language
             const rows = (stringFilters.selected_string_ids || [])
             .map((id) => selectedStrings[String(id)])
             .filter(Boolean);
   
           const rowsToTranslate = rows.filter((row) => {
             const stringId = String(row.string_id);
             return stringLanguageStatus[stringId]?.[lang] !== "edit";
           });
   
           strings = rowsToTranslate.map((row) => ({
             text: row.value || "",
             html: row.value || "",
             field_key: String(row.string_id),
             field_name: row.name || String(row.string_id),
             format: "html",
           }));
           total = strings.length;
  
    if (total === 0 || !strings.length) continue;
  
    totalPerLanguage[lang] = total;
    stringsByLanguage[lang] = strings;
  
    const flagUrl =
      automlp_wpml_bulk_translate_object.languageObject[lang]?.flag || "";
    const languageName =
      automlp_wpml_bulk_translate_object.languageObject[lang]?.name || lang;
  
    // One Redux entry per language
    const key = `strings_${lang}`;
    stringKeys.push(key);
    storeDispatch(updatePendingPosts([key]));
    storeDispatch(
      updateTranslatePostInfo({
        [key]: {
          parentPostId: `strings_${lang}`,
          targetLanguage: lang,
          status: "in-progress",
          messageClass: "in-progress",
          parentPostTitle: languageName,
          firstPostLanguage: true,
          flagUrl,
          languageName,
          editorType: "strings",
          total,
          completed: 0,
        },
      }),
    );
  }

  if (stringKeys.length === 0) {
    return {
      success: false,
      message: __(
        "No strings found to translate.",
        "wpml-translation-check",
      ),
    };
  }

  const totalStrings = Object.values(totalPerLanguage).reduce(
    (a, b) => a + b,
    0,
  );
  storeDispatch(updateCountInfo({ totalPosts: totalStrings }));

  return {
    success: true,
    stringKeys,
    stringsByLanguage,
    totalPerLanguage,
    nonce,
  };
};

/**
 * Initialize bulk translation for strings.
 *
 * Strings are queued on the server (WPML st-batch jobs assigned to the AutoMLP
 * translator) and translated in the background. This function only queues the
 * work and polls progress, feeding the same Redux fields the status modal has
 * always used, so the modal looks and behaves exactly as before.
 */
const STRING_QUEUE_CHUNK = 500;

const errorRow = (message) => ({
  status: "error",
  messageClass: "error",
  errorMessage: __("Translation failed", "wpml-translation-check"),
  errorHtml:
    '<div class="automlp-wpml-error-html">' +
    escapeHtml(message || __("Translation failed", "wpml-translation-check")) +
    "</div>",
});

const escapeHtml = (value) =>
  String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

/**
 * Strings finished so far for one job, in real string units.
 *
 * @param {Object} job Job row from /queue/status.
 * @return {number}
 */
const jobStringsDone = (job) => {
  const total = Number(job.string_count) || 0;

  if (job.state === "done") {
    return total;
  }

  return Math.floor((total * jobProgressPercent(job)) / 100);
};

const initBulkTranslateStrings = async (
  stringKeys = [],
  stringsByLanguage = {},
  nonce,
  storeDispatch,
  prefix,
  updateDestoryHandler,
  totalPerLanguage = {},
) => {
  const pendingPosts = store.getState().pendingPosts;
  if (pendingPosts.length < 1) return;

  let modalClosed = false;
  updateDestoryHandler(() => {
    modalClosed = true;
  });

  const sourceLang =
    automlp_wpml_bulk_translate_object.default_language_slug || "en";
  const activeProvider = store.getState().serviceProvider || "";
  const serviceSlug = activeProvider.replace(/_ai$/, "");
  const startedAt = Date.now();

  const finishLang = (lang, info) => {
    const langKey = `strings_${lang}`;
    storeDispatch(updateTranslatePostInfo({ [langKey]: info }));
    storeDispatch(unsetPendingPost(langKey));
    storeDispatch(updateCompletedPosts([langKey]));
  };

  // 1. Queue every language, in chunks, before polling.
  const jobLang = {};
  const langs = Object.keys(stringsByLanguage);

  for (const lang of langs) {
    if (modalClosed) return;

    const ids = (stringsByLanguage[lang] || [])
      .map((str) => parseInt(str.field_key, 10))
      .filter((id) => id > 0);
    let queuedAny = false;
    let lastError = "";

    for (let i = 0; i < ids.length; i += STRING_QUEUE_CHUNK) {
      try {
        const res = await queueStrings(
          ids.slice(i, i + STRING_QUEUE_CHUNK),
          lang,
          activeProvider,
        );
        (res.jobs || []).forEach((job) => {
          jobLang[job.job_id] = lang;
          queuedAny = true;
        });
      } catch (err) {
        lastError = err?.message || "";
      }
    }

    if (!queuedAny) {
      finishLang(lang, errorRow(lastError));
    }
  }

  const jobIds = Object.keys(jobLang).map(Number);

  if (!jobIds.length || modalClosed) {
    return;
  }

  // 2. Poll and mirror progress into the modal.
  const totalStrings = Object.values(totalPerLanguage).reduce(
    (a, b) => a + b,
    0,
  );
  const closedLangs = {};
  let reportedProgress = 0;

  const onUpdate = (status) => {
    const jobs = Array.isArray(status?.jobs) ? status.jobs : [];
    const perLang = {};

    jobs.forEach((job) => {
      const lang = jobLang[job.job_id];
      if (!lang) return;

      if (!perLang[lang]) {
        perLang[lang] = { done: 0, total: 0, chars: 0, closed: true, errors: [] };
      }

      const entry = perLang[lang];
      entry.total += Number(job.string_count) || 0;
      entry.done += jobStringsDone(job);

      if (job.state === "done") {
        entry.chars += Number(job.char_count) || 0;
      }
      if (!job.closed) {
        entry.closed = false;
      }
      if (job.state === "failed" || job.state === "stopped") {
        entry.errors.push(job.error || job.label || "");
      }
    });

    let doneAll = 0;
    let charsAll = 0;

    Object.keys(perLang).forEach((lang) => {
      const entry = perLang[lang];
      const langKey = `strings_${lang}`;
      const total = entry.total || totalPerLanguage[lang] || 0;

      doneAll += entry.done;
      charsAll += entry.chars;

      if (closedLangs[lang]) return;

      if (!entry.closed) {
        storeDispatch(
          updateTranslatePostInfo({
            [langKey]: {
              status: "in-progress",
              messageClass: "in-progress",
              completed: entry.done,
              total,
            },
          }),
        );
        return;
      }

      closedLangs[lang] = true;

      storeDispatch(
        updateParentPostsInfo({
          postId: langKey,
          data: { charactersCount: entry.chars, stringsCount: entry.done },
        }),
      );
      storeDispatch(
        updateTranslatePostInfo({
          [`${langKey}_${lang}`]: {
            stringsTranslated: entry.done,
            charactersTranslated: entry.chars,
            duration: Date.now() - startedAt,
          },
        }),
      );

      if (entry.errors.length) {
        finishLang(lang, {
          ...errorRow(entry.errors.filter(Boolean).join(" | ")),
          completed: entry.done,
          total,
        });
      } else {
        finishLang(lang, {
          status: "completed",
          messageClass: "success",
          completed: total,
          total,
        });
      }
    });

    storeDispatch(
      updateCountInfo({
        stringsTranslated: doneAll,
        charactersTranslated: charsAll,
        timeTaken: (Date.now() - startedAt) / 1000,
        sourceLanguage: sourceLang,
        serviceProvider: serviceSlug,
      }),
    );

    // The reducer adds to progressStatus, so dispatch only the delta.
    const target = totalStrings > 0 ? Math.min(100, (100 * doneAll) / totalStrings) : 0;
    if (target > reportedProgress) {
      storeDispatch(updateProgressStatus(target - reportedProgress));
      reportedProgress = target;
    }
  };

  try {
    await pollUntilDone({
      jobIds,
      onUpdate,
      shouldStop: () => modalClosed,
    });
  } catch (err) {
    // Timed out: work continues on the server. Report it on open rows.
    langs.forEach((lang) => {
      if (!closedLangs[lang] && store.getState().pendingPosts.includes(`strings_${lang}`)) {
        finishLang(lang, errorRow(err?.message));
      }
    });
    return;
  }

  if (modalClosed) return;

  // Make sure the bar ends at 100% once every job closed.
  if (reportedProgress < 100) {
    storeDispatch(updateProgressStatus(100 - reportedProgress));
  }
};

export {
  bulkTranslateEntries,
  bulkTranslateStrings,
  initBulkTranslateStrings,
};
