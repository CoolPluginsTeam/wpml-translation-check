import { AITranslationRequest } from "./helper";
import ChromeAiTranslator from "./components/translate-provider/local-ai/local-ai-translate";
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
import {
  updateTranslateData,
  getContentCount,
} from "./helper";
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
  const nonce = automl_wpml_bulk_translate_object.nonce;
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
      automl_wpml_bulk_translate_object.languageObject[lang]?.flag || "";
    const languageName =
      automl_wpml_bulk_translate_object.languageObject[lang]?.name || lang;
  
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
        "automl-ai-translation-for-wpml",
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
 * Translate an array of plain strings using Chrome built-in AI (client-side).
 * Returns Promise<string[]> with same order as input, or rejects on error.
 */
const translateStringsWithChromeAI = (strings, sourceLang, targetLang) => {
  const languageObject =
    automl_wpml_bulk_translate_object?.languageObject || {};
  const textContentObject = strings.reduce((acc, text, i) => {
    acc[i] = text || "";
    return acc;
  }, {});

  return new Promise((resolve, reject) => {
    const translations = [];
    ChromeAiTranslator.Object({
      sourceLanguage: sourceLang,
      targetLanguage: targetLang,
      sourceLanguageLabel: languageObject[sourceLang]?.name || sourceLang,
      targetLanguageLabel: languageObject[targetLang]?.name || targetLang,
      onAfterTranslate: (key, translated) => {
        const index = parseInt(key, 10);
        if (!Number.isNaN(index)) {
          translations[index] = translated || "";
        }
      },
      onComplete: () => resolve(translations),
      onLanguageError: (err) =>
        reject(
          err?.message
            ? new Error(err.message)
            : new Error("Chrome AI translation failed"),
        ),
    })
      .then((translatorObj) => {
        if (!translatorObj?.init || !translatorObj?.startTranslation) {
          reject(
            new Error(
              __(
                "Chrome AI is not available. Use Chrome and enable the Translation API.",
                "automl-ai-translation-for-wpml",
              ),
            ),
          );
          return;
        }
        translatorObj.init(textContentObject);
        translatorObj.startTranslation();
      })
      .catch(reject);
  });
};

/**
 * Initialize bulk translation for strings.
 * Pipeline: fetch one page (500) → translate → save → fetch next page. Never holds more than 500 strings in memory.
 */
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
    automl_wpml_bulk_translate_object.default_language_slug || "en";
  const BATCH_SIZE = 500;

  const translateStringsForLanguage = async (lang, initialStrings) => {
    if (!initialStrings?.length || modalClosed) return;

    const activeProvider = store.getState().serviceProvider;
    let serviceSlug = activeProvider;
    if (serviceSlug?.endsWith("_ai"))
      serviceSlug = serviceSlug.replace("_ai", "");

    const controller = new AbortController();
    const totalForLang = totalPerLanguage[lang] || initialStrings.length;
    const langKey = `strings_${lang}`;

    let offset = 0;
    let strings = initialStrings;
    let batchStringsTranslated = 0;
    let batchCharsTranslated = 0;

    while (strings.length > 0 && !modalClosed) {
      const batch = strings.slice(0, BATCH_SIZE);
      const batchStartTime = Date.now();

      try {
        const stringsToTranslate = batch.map((str) => ({
          text: str.text || str.html || "",
          field_key: str.field_key,
        }));

        let translationResponse;
        if (activeProvider === "localAiTranslator") {
          const translations = await translateStringsWithChromeAI(
            stringsToTranslate.map((s) => s.text),
            sourceLang,
            lang,
          );
          translationResponse = { success: true, data: { translations } };
        } else {
          translationResponse = await AITranslationRequest({
            controller,
            Strings: stringsToTranslate.map((s) => s.text),
            slug: serviceSlug,
            source_language: sourceLang,
            target_language: lang,
          });
        }
        if (translationResponse?.success && translationResponse.data) {
          let translations = [];

          const data = translationResponse.data;

          // 1) AI SDK route: { translate_data: { "0": "…", "1": "…" } }
          if (data.translate_data && typeof data.translate_data === "object") {
            translations = Object.keys(data.translate_data)
              .sort((a, b) => Number(a) - Number(b))
              .map((key) => data.translate_data[key] || "");
          }
          // 2) Old shape: raw array
          else if (Array.isArray(data)) {
            translations = data;
          }
          // 3) Old shape: { translations: [...] }
          else if (Array.isArray(data.translations)) {
            translations = data.translations;
          }

          const batchToSave = [];
          batch.forEach((str, index) => {
            const sourceText = str.text || str.html || "";
            const translatedText = translations[index] || sourceText;
            batchToSave.push({
              field_key: str.field_key,
              translated: translatedText,
            });
            batchStringsTranslated += 1;
            batchCharsTranslated += sourceText.length;
          });
          let timeTakenSec = 0;
          if (batchToSave.length > 0) {
            timeTakenSec = (Date.now() - batchStartTime) / 1000;
            await saveStringTranslations(lang, batchToSave, nonce);
            if (automl_wpml_bulk_translate_object?.update_translate_data_nonce) {
              const batchWords = batch.reduce(
                (sum, s) =>
                  sum +
                  (typeof (s.text || s.html || "") === "string"
                    ? (s.text || s.html || "")
                        .trim()
                        .split(/\s+/)
                        .filter(Boolean).length
                    : 0),
                0,
              );
              const parentKey = `strings_${lang}`;
              const translateInfoKey = `${parentKey}_${lang}`;
              const saveId = `strings_${lang}_${Date.now()}`;

              storeDispatch(
                updateParentPostsInfo({
                  postId: parentKey,
                  data: {
                    wordsCount: batchWords,
                    charactersCount: batchCharsTranslated,
                    stringsCount: batchToSave.length,
                  },
                }),
              );
              storeDispatch(
                updateTranslatePostInfo({
                  [translateInfoKey]: {
                    stringsTranslated: batchToSave.length,
                    wordsTranslated: batchWords,
                    charactersTranslated: batchCharsTranslated,
                    duration: timeTakenSec * 1000,
                  },
                }),
              );

              updateTranslateData({
                provider: serviceSlug || activeProvider,
                sourceLang,
                targetLang: lang,
                parentPostId: parentKey,
                currentPostId: saveId,
                editorType: "strings",
                updateTranslateDataNonce:
                  automl_wpml_bulk_translate_object.update_translate_data_nonce,
                extraData: {},
              });
            }
          }

          offset += batch.length;
          const state = store.getState();
          const prev = state.translatePostInfo[langKey] || {};
          const completed = (prev.completed || 0) + batch.length;

          storeDispatch(
            updateTranslatePostInfo({
              [langKey]: {
                ...prev,
                status: offset >= totalForLang ? "completed" : "in-progress",
                messageClass:
                  offset >= totalForLang ? "success" : "in-progress",
                completed,
                total: totalForLang,
              },
            }),
          );

          const count = state.countInfo || {};
          storeDispatch(
            updateCountInfo({
              stringsTranslated:
                (count.stringsTranslated || 0) + batchStringsTranslated,
              charactersTranslated:
                (count.charactersTranslated || 0) + batchCharsTranslated,
              timeTaken: (count.timeTaken || 0) + timeTakenSec,
              sourceLanguage: sourceLang,
              serviceProvider: serviceSlug || activeProvider,
            }),
          );
          batchStringsTranslated = 0;
          batchCharsTranslated = 0;

          const totalStrings = Object.values(totalPerLanguage).reduce(
            (a, b) => a + b,
            0,
          );
          storeDispatch(
            updateProgressStatus((100 * batch.length) / totalStrings),
          );

          if (offset >= totalForLang) {
            storeDispatch(unsetPendingPost(langKey));
            storeDispatch(updateCompletedPosts([langKey]));
            return;
          }
        } else {
          const fallbackMsg = __(
            "Translation failed",
            "automl-ai-translation-for-wpml",
          );

          const raw = translationResponse?.data;
          let errorHtml = "";

          if (typeof raw === "string") {
            errorHtml = raw;
          } else if (raw && typeof raw === "object") {
            errorHtml = JSON.stringify(raw);
          } else {
            errorHtml = fallbackMsg;
          }

          storeDispatch(
            updateTranslatePostInfo({
              [langKey]: {
                status: "error",
                messageClass: "error",
                errorMessage: fallbackMsg,
                errorHtml:
                  '<div class="automl-wpml-error-html">' + errorHtml + "</div>",
              },
            }),
          );
          storeDispatch(unsetPendingPost(langKey));
          storeDispatch(updateCompletedPosts([langKey]));
          return;
        }
      } catch (err) {
        const fallbackMsg = __(
          "Translation failed",
          "automl-ai-translation-for-wpml",
        );

        let errorHtml = err?.message || "";

        if (err?.data && err.data.status) {
          errorHtml = "Error Code:" + err.data.status;

          if (typeof err.data.error === "string") {
            errorHtml += "<br>Error Message:" + err.data.error;
          } else if (typeof err.data.error === "object") {
            errorHtml +=
              "<br>Error Message:" + JSON.stringify(err.data.error);
          }
        } else if (!errorHtml) {
          errorHtml = fallbackMsg;
        }

        storeDispatch(
          updateTranslatePostInfo({
            [langKey]: {
              status: "error",
              messageClass: "error",
              errorMessage: fallbackMsg,
              errorHtml:
                '<div class="automl-wpml-error-html">' + errorHtml + "</div>",
            },
          }),
        );
        storeDispatch(unsetPendingPost(langKey));
        storeDispatch(updateCompletedPosts([langKey]));
        return;
      }
    }

    storeDispatch(unsetPendingPost(langKey));
    storeDispatch(updateCompletedPosts([langKey]));
  };

  for (const lang of Object.keys(stringsByLanguage)) {
    if (modalClosed) break;
    await translateStringsForLanguage(lang, stringsByLanguage[lang]);
  }
};

/**
 * Save translated strings via AJAX.
 * @param {string} targetLang - Target language code.
 * @param {Array} translatedStrings - Array of { field_key, translated }.
 * @param {string} nonce - Security nonce.
 * @param {Object} [stats] - Optional dashboard stats: source_lang, service_provider, string_count, character_count, time_taken.
 */
const saveStringTranslations = async (
  targetLang,
  translatedStrings,
  nonce,
  stats = null,
) => {
  const ajaxUrl = automl_wpml_bulk_translate_object.ajax_url;

  const body = {
    action: "automl_wpml_google_auto_translate_save_string_translations",
    nonce: nonce,
    target_lang: targetLang,
    translated_strings: translatedStrings,
  };
  if (stats && typeof stats === "object") {
    body.dashboard_stats = stats;
  }

  try {
    const response = await fetch(
      ajaxUrl +
        "?action=automl_wpml_google_auto_translate_save_string_translations",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          Accept: "application/json",
        },
        body: JSON.stringify(body),
      },
    );

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Error saving string translations:", error);
    throw error;
  }
};

export {
  bulkTranslateEntries,
  bulkTranslateStrings,
  initBulkTranslateStrings,
};
