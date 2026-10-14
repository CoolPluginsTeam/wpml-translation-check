import {
  queueStrings,
  pollUntilDone,
  retryJob,
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
// String translation only. Posts and terms use bulk-translate/status-modal.
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

/**
 * Row data for a failed language.
 *
 * aiError shows the "Translate" button in the error popup. retry holds what
 * that button needs: failed job ids (sent to /queue/retry, like the Queue
 * tab) and string ids that never got a job (queued again).
 */
const errorRow = (message, retry = {}) => ({
  status: "error",
  messageClass: "error",
  errorMessage: __("Translation failed", "wpml-translation-check"),
  errorHtml:
    '<div class="automlp-wpml-error-html">' +
    escapeHtml(message || __("Translation failed", "wpml-translation-check")) +
    "</div>",
  aiError: Boolean(
    (retry.failedJobIds && retry.failedJobIds.length) ||
      (retry.stringIds && retry.stringIds.length),
  ),
  retry,
});

/**
 * Queue string ids for one language, in chunks.
 *
 * @param {number[]} ids      String ids.
 * @param {string}   lang     Target language.
 * @param {string}   provider Queue provider slug.
 * @return {Promise<{jobIds: number[], error: string}>}
 */
const queueStringIds = async (ids, lang, provider) => {
  const jobIds = [];
  let error = "";

  for (let i = 0; i < ids.length; i += STRING_QUEUE_CHUNK) {
    try {
      const res = await queueStrings(
        ids.slice(i, i + STRING_QUEUE_CHUNK),
        lang,
        provider,
      );
      (res.jobs || []).forEach((job) => jobIds.push(Number(job.job_id)));
    } catch (err) {
      error = err?.message || "";
    }
  }

  return { jobIds, error };
};

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
    const queued = await queueStringIds(ids, lang, activeProvider);

    queued.jobIds.forEach((jobId) => {
      jobLang[jobId] = lang;
    });

    if (!queued.jobIds.length) {
      finishLang(
        lang,
        errorRow(queued.error, { provider: activeProvider, stringIds: ids }),
      );
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
        perLang[lang] = {
          done: 0,
          doneOk: 0,
          total: 0,
          chars: 0,
          closed: true,
          errors: [],
          failedJobIds: [],
        };
      }

      const entry = perLang[lang];
      entry.total += Number(job.string_count) || 0;
      entry.done += jobStringsDone(job);

      if (job.state === "done") {
        entry.chars += Number(job.char_count) || 0;
        entry.doneOk += Number(job.string_count) || 0;
      }
      if (!job.closed) {
        entry.closed = false;
      }
      if (job.state === "failed" || job.state === "stopped") {
        entry.errors.push(job.error || job.label || "");
        entry.failedJobIds.push(Number(job.job_id));
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
          ...errorRow(entry.errors.filter(Boolean).join(" | "), {
            provider: activeProvider,
            failedJobIds: entry.failedJobIds,
            stringsDone: entry.doneOk,
            charsDone: entry.chars,
          }),
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

/**
 * "Translate" button of the strings error popup.
 *
 * Failed jobs go back through POST /queue/retry, exactly like Retry in the
 * Queue tab (REST nonce + capability checked on the server); strings that
 * never got a job are queued again.
 *
 * @param {Object}   args
 * @param {Object}   args.info                 The failed row (translatePostInfo entry).
 * @param {Function} args.storeDispatch        Redux dispatch.
 * @param {Function} args.updateDestoryHandler Registers a modal-close callback.
 */
const retryStringTranslation = async ({ info, storeDispatch, updateDestoryHandler }) => {
  const retry = info?.retry || {};
  const lang = info?.targetLanguage;
  const provider = retry.provider || "";

  if (!lang || !provider) {
    return;
  }

  const langKey = `strings_${lang}`;
  const total = Number(info.total) || 0;
  const baseDone = Number(retry.stringsDone) || 0;
  const baseChars = Number(retry.charsDone) || 0;
  let closed = false;

  updateDestoryHandler(() => {
    closed = true;
  });

  storeDispatch(
    updateTranslatePostInfo({
      [langKey]: {
        status: "in-progress",
        messageClass: "in-progress",
        aiError: false,
        completed: baseDone,
        total,
      },
    }),
  );

  const jobIds = [];
  let lastError = "";

  for (const jobId of retry.failedJobIds || []) {
    try {
      const res = await retryJob(jobId);
      if (res && res.retried) {
        jobIds.push(Number(jobId));
      }
    } catch (err) {
      lastError = err?.message || "";
    }
  }

  if (retry.stringIds && retry.stringIds.length) {
    const queued = await queueStringIds(retry.stringIds, lang, provider);
    jobIds.push(...queued.jobIds);
    lastError = queued.error || lastError;
  }

  if (!jobIds.length) {
    storeDispatch(
      updateTranslatePostInfo({
        [langKey]: { ...errorRow(lastError, retry), completed: baseDone, total },
      }),
    );
    return;
  }

  const startedAt = Date.now();
  const countBefore = store.getState().countInfo || {};

  const onUpdate = (status) => {
    const jobs = Array.isArray(status?.jobs) ? status.jobs : [];
    let done = 0;
    let doneOk = 0;
    let chars = 0;
    let open = false;
    const errors = [];
    const failedJobIds = [];

    jobs.forEach((job) => {
      done += jobStringsDone(job);
      if (job.state === "done") {
        doneOk += Number(job.string_count) || 0;
        chars += Number(job.char_count) || 0;
      }
      if (!job.closed) {
        open = true;
      }
      if (job.state === "failed" || job.state === "stopped") {
        errors.push(job.error || job.label || "");
        failedJobIds.push(Number(job.job_id));
      }
    });

    if (open) {
      storeDispatch(
        updateTranslatePostInfo({
          [langKey]: {
            status: "in-progress",
            messageClass: "in-progress",
            completed: Math.min(total, baseDone + done),
            total,
          },
        }),
      );
      return;
    }

    storeDispatch(
      updateParentPostsInfo({
        postId: langKey,
        data: {
          charactersCount: baseChars + chars,
          stringsCount: baseDone + doneOk,
        },
      }),
    );
    storeDispatch(
      updateCountInfo({
        stringsTranslated: (Number(countBefore.stringsTranslated) || 0) + doneOk,
        charactersTranslated: (Number(countBefore.charactersTranslated) || 0) + chars,
        timeTaken:
          (Number(countBefore.timeTaken) || 0) + (Date.now() - startedAt) / 1000,
      }),
    );

    storeDispatch(
      updateTranslatePostInfo({
        [langKey]: errors.length
          ? {
              ...errorRow(errors.filter(Boolean).join(" | "), {
                provider,
                failedJobIds,
                stringsDone: baseDone + doneOk,
                charsDone: baseChars + chars,
              }),
              completed: Math.min(total, baseDone + done),
              total,
            }
          : {
              status: "completed",
              messageClass: "success",
              aiError: false,
              completed: total,
              total,
            },
      }),
    );
  };

  try {
    await pollUntilDone({
      jobIds,
      onUpdate,
      shouldStop: () => closed,
    });
  } catch (err) {
    if (!closed) {
      storeDispatch(
        updateTranslatePostInfo({
          [langKey]: {
            ...errorRow(err?.message, { provider, failedJobIds: jobIds }),
            completed: baseDone,
            total,
          },
        }),
      );
    }
  }
};

export {
  bulkTranslateStrings,
  initBulkTranslateStrings,
  retryStringTranslation,
};
