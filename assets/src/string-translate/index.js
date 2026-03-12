import App from "./App";
import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom";
import "./index.css";
import { store } from "./redux-store/store";
import { Provider } from "react-redux";
import { __ } from "@wordpress/i18n";
import LoopCallback from "./components/loop-callback";
import LocalAITranslate from "./components/translate-provider/local-ai/local-ai-translate";

(() => {
  const BulkTranslate = (props) => {
    const [modalVisible, setModalVisible] = useState(false);
    const [postIds, setPostIds] = useState([]);
    const prefix = props.prefix;
    const wrapper = document.getElementById(`${prefix}-wrapper`);
    let localAiCheckInProgres = false;

    const handleModalVisibility = (e) => {
      e.preventDefault();

      setModalVisible((prev) => !prev);
      destroyGoogleWidget();
    };

    // 1️⃣ Clear old cached data on page load
    const clearOldTranslatorCacheOnLoad = () => {
      const loadKey = "automlp_wpml_LOCAL_AI_PAGE_LOADED";

      if (sessionStorage.getItem(loadKey)) {
        return;
      }

      localStorage.removeItem(
        "automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES",
      );
      sessionStorage.setItem(loadKey, "1");
    };

    // 2️⃣ Language pack availability check (gesture-based)
    const checkLanguagePackAvailability = async () => {
      const languagesObj = {
        ...automlp_wpml_bulk_translate_object.languageObject,
      };
      const supportedLanguages = LocalAITranslate.supportedLanguages || [];

      delete languagesObj.en;

      let savedLanguages = [];
      try {
        savedLanguages =
          JSON.parse(
            localStorage.getItem(
              "automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES",
            ),
          ) || [];
      } catch {
        savedLanguages = [];
      }

      savedLanguages.forEach((lang) => delete languagesObj[lang]);

      if (!Object.keys(languagesObj).length) return;

      const processNextLanguage = async () => {
        if (localAiCheckInProgres || Object.keys(languagesObj).length === 0)
          return;

        localAiCheckInProgres = true;
        const targetLang = Object.keys(languagesObj)[0];

        if (supportedLanguages.includes(targetLang)) {
          try {
            const status = await LocalAITranslate.languagePairAvality(
              "en",
              targetLang,
            );

            if (["available", "readily"].includes(status)) {
              delete languagesObj[targetLang];
              savedLanguages.push(targetLang);
              localStorage.setItem(
                "automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES",
                JSON.stringify(savedLanguages),
              );
            }
          } catch (err) {
            console.error(
              "Language availability check failed:",
              targetLang,
              err,
            );
          }
        } else {
          delete languagesObj[targetLang];
        }

        localAiCheckInProgres = false;

        if (Object.keys(languagesObj).length === 0) {
          document.removeEventListener("mousemove", onMouseMove);

          const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
          doActionsBtn.forEach((btn) => {
            btn.removeEventListener("mousemove", checkLanguagePackAvailability);
            btn.removeEventListener(
              "mouseleave",
              checkLanguagePackAvailability,
            );
            btn.removeEventListener(
              "mouseenter",
              checkLanguagePackAvailability,
            );
          });
        }
      };

      const onMouseMove = () => {
        processNextLanguage();
      };

      document.addEventListener("mousemove", onMouseMove);
    };

    const bulkTranslationHandler = (e) => {
        e.preventDefault();
      
        // Check if we're on the String Translation page
        const isStringTranslationPage =
          window.location.href.indexOf("wpml-string-translation") !== -1;
      
        let postIds = [];
        let stringFilters = {};
      
        if (isStringTranslationPage) {
  const stringTable =
  document.querySelector("#icl_string_translations") ||
  document.querySelector("table.js-wpml-st-table");

// All checkboxes in the table (whether checked or not)
const allCheckboxes = stringTable
  ? stringTable.querySelectorAll(
      "input.wpml-checkbox-native, input.js-icl-st-row-cb",
    )
  : [];

// Only the checked ones
const checked = stringTable
  ? stringTable.querySelectorAll(
      "input.wpml-checkbox-native:checked, input.js-icl-st-row-cb:checked",
    )
  : [];

// If nothing is checked, treat ALL rows as selected
const effectiveSelection =
  checked && checked.length > 0 ? checked : allCheckboxes;

  const selectedStringIds = [];
  const selectedStringsMap = {};
  const stringLanguageStatus = {};
  
  Array.from(effectiveSelection).forEach((el) => {
    const id = el.value;
    if (!id) return;
  
    selectedStringIds.push(id);
  
    const row = el.closest('tr[data-string]');
    if (!row) return;
  
    if (row.dataset.string) {
      try {
        const parsed = JSON.parse(row.dataset.string);
        selectedStringsMap[String(parsed.string_id)] = parsed;
      } catch (err) {}
    }
  
    const langCell = row.querySelector('.wpml-col-languages, .languages-status');
    if (langCell) {
      stringLanguageStatus[id] = {};
      langCell.querySelectorAll('i[id]').forEach((icon) => {
        const idAttr = icon.getAttribute('id') || '';
        const dash = idAttr.indexOf('-');
        if (dash > 0) {
          const lang = idAttr.slice(dash + 1);
          stringLanguageStatus[id][lang] = icon.classList.contains('otgs-ico-edit') ? 'edit' : 'add';
        }
      });
    }
  });
      
  stringFilters =
  selectedStringIds.length > 0
    ? { selected_string_ids: selectedStringIds }
    : {};

// Store selection + row data globally for use in StatusModal/bulk-translate
window.wpmlStringFilters = stringFilters;
                   window.wpmlSelectedStrings = selectedStringsMap;
                   window.wpmlStringLanguageStatus = stringLanguageStatus;
                   window.wpmlIsStringTranslationPage = true;
      
          // For strings, we don't need postIds
          postIds = [];
        }
      
        checkLanguagePackAvailability();
      
        setPostIds(postIds);
        handleModalVisibility(e);
      };

    const destroyGoogleWidget = () => {
      const googleWidget = document.querySelector(
        '.skiptranslate iframe[id=":1.container"]',
      );
      document.body.classList.remove(prefix + "-google-translate");

      if (googleWidget) {
        const closeButton = googleWidget.contentDocument.querySelector(
          'a[id=":1.close"][title="Close"] img',
        );
        if (closeButton) {
          closeButton.click();
        }
      }
    };

    useEffect(() => {
      const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);

      if (doActionsBtn) {
        clearOldTranslatorCacheOnLoad();
        doActionsBtn.forEach((btn) => {
          btn.addEventListener("click", bulkTranslationHandler);
          btn.addEventListener("mousemove", checkLanguagePackAvailability);
          btn.addEventListener("mouseleave", checkLanguagePackAvailability);
          btn.addEventListener("mouseenter", checkLanguagePackAvailability);
        });
      }
    }, []);

    useEffect(() => {
      const mainWrapper = document.getElementById(`${prefix}-wrapper`);
      if (mainWrapper) {
        mainWrapper.classList.toggle(`${prefix}-active`, modalVisible);
      }
    }, [modalVisible]);

    return modalVisible ? (
      <App
        onDestory={handleModalVisibility}
        prefix={prefix}
        postIds={postIds}
      />
    ) : null;
  };

  window.addEventListener("load", async () => {
    const prefix = "automlp-wpml-bulk-translate";

    // Move bulk translate button to correct position on string translation page
    const bulkTranslateBtn = document.querySelector(`.${prefix}-btn`);
    bulkTranslateBtn.style.display = "block";
    const stringFilterDiv = document.querySelector(
      ".wpml-string-translation-filter",
    );
    const filterButton = document.querySelector("#icl_st_filter_search_sb");

    if (bulkTranslateBtn && stringFilterDiv && filterButton) {
      // Insert the button after the filter button
      filterButton.insertAdjacentElement("afterend", bulkTranslateBtn);
    }

    ReactDOM.createRoot(document.getElementById(`${prefix}-wrapper`)).render(
      <Provider store={store}>
        <BulkTranslate prefix={prefix} />
      </Provider>,
    );
  });
})();
