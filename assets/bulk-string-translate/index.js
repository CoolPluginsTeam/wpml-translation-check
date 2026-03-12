/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/string-translate/App.js"
/*!********************************************!*\
  !*** ./assets/src/string-translate/App.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _status_modal__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./status-modal */ "./assets/src/string-translate/status-modal/index.js");
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react-redux */ "./node_modules/react-redux/dist/react-redux.mjs");
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");
/* harmony import */ var _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./components/translate-provider/local-ai/local-ai-translate */ "./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js");
/* harmony import */ var _components_error_modal_box__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./components/error-modal-box */ "./assets/src/string-translate/components/error-modal-box/index.js");
/* harmony import */ var _setting_modal__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./setting-modal */ "./assets/src/string-translate/setting-modal/index.js");
/* harmony import */ var dompurify__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! dompurify */ "./node_modules/dompurify/dist/purify.es.mjs");
/* harmony import */ var _components_notice__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./components/notice */ "./assets/src/string-translate/components/notice/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__);












const App = ({
  onDestory,
  prefix,
  postIds
}) => {
  const dispatch = (0,react_redux__WEBPACK_IMPORTED_MODULE_3__.useDispatch)();
  const {
    languageObject = {},
    selected_language_object = {}
  } = automlp_wpml_bulk_translate_object || {};
  const wizardSelectedCode = Object.keys(selected_language_object)[0] || '';
  const wizardLanguagesUrl = (automlp_wpml_bulk_translate_object?.admin_url || '').replace(/\/?$/, '') + '/admin.php?page=automlp_ai_wizard&step=languages';
  const emptyPostIdsErrorMessage = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Please select at least one %s for translation.", "automlp-ai-translation-for-wpml"), automlp_wpml_bulk_translate_object.post_label);
  const [selectedLanguages, setSelectedLanguages] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  // Don't show error for string translation page even if postIds is empty
  const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;
  const [errorMessage, setErrorMessage] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(postIds.length === 0 && !isStringTranslationPage ? emptyPostIdsErrorMessage : "");
  const [settingModalVisibility, setSettingModalVisibility] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [statusModalVisibility, setStatusModalVisibility] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const translatePostsCount = (0,react_redux__WEBPACK_IMPORTED_MODULE_3__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_5__.selectCountInfo).totalPosts;
  const [isLoading, setIsLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [errorModal, setErrorModal] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [localAiModalError, setLocalAiModalError] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const targetLanguages = JSON.parse(JSON.stringify(languageObject));
  delete targetLanguages[automlp_wpml_bulk_translate_object.default_language_slug];
  const destroyApp = e => {
    setStatusModalVisibility(false);
    setSettingModalVisibility(false);
    onDestory(e);
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const checkStatus = async () => {
      const status = await _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_6__["default"].languageSupportedStatus("en", "hi", "English", "Hindi");
      if (status.type === "browser-not-supported" || status.type === "translation-api-not-available" || status.type === "browser-not-supported") {
        setLocalAiModalError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(status.html[0].outerHTML, "automlp-ai-translation-for-wpml"));
      }
      setIsLoading(false);
    };
    checkStatus();
  }, [statusModalVisibility]);
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!statusModalVisibility && !settingModalVisibility) {
      dispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_4__.resetStore)());
    }
  }, [statusModalVisibility, settingModalVisibility, dispatch]);
  const settingModalVisibilityHandler = async () => {
    if (selectedLanguages.length === 0 && !settingModalVisibility) {
      setErrorMessage((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Please select at least one language", "automlp-ai-translation-for-wpml"));
      setErrorModal(true);
      return;
    }
    setSettingModalVisibility(prev => !prev);
  };
  const handleLanguageChange = e => {
    const {
      value
    } = e.target;
    const checked = e.target.checked;
    if (checked) {
      setSelectedLanguages([...selectedLanguages, value]);
    } else {
      setSelectedLanguages(selectedLanguages.filter(language => language !== value));
    }
  };
  const closeErrorModal = e => {
    setErrorModal(false);
  };
  const updateProviderHandler = services => {
    dispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_4__.updateServiceProvider)(services));
    setSettingModalVisibility(false);
    setStatusModalVisibility(true);
    setIsLoading(false);
  };
  const containerCls = () => {
    let cls = [];
    if (statusModalVisibility) {
      cls.push(`${prefix}-status-modal-active`);
    }
    if (settingModalVisibility) {
      cls.push(`${prefix}-setting-modal-active`);
    }
    if (!translatePostsCount && !settingModalVisibility && statusModalVisibility) {
      cls.push(`${prefix}-empty-posts`);
    }
    return cls.join(" ");
  };
  const SelectLanguageNotice = () => {
    const notices = [];
    const noticeLength = notices.length;
    if (notices.length > 0) {
      return notices.map((notice, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_components_notice__WEBPACK_IMPORTED_MODULE_10__["default"], {
        className: notice.className,
        lastNotice: index === noticeLength - 1,
        children: notice.message
      }, index));
    }
    return;
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
    id: `${prefix}-container`,
    className: containerCls(),
    children: [settingModalVisibility && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_setting_modal__WEBPACK_IMPORTED_MODULE_8__["default"], {
      prefix: prefix,
      onDestory: destroyApp,
      onCloseHandler: settingModalVisibilityHandler,
      updateProviderHandler: updateProviderHandler,
      localAiModalError: localAiModalError
    }), statusModalVisibility && !settingModalVisibility && (isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
      className: `${prefix}-skeleton-loader`
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_status_modal__WEBPACK_IMPORTED_MODULE_2__["default"], {
      postIds: postIds,
      selectedLanguages: selectedLanguages,
      prefix: prefix,
      onDestory: destroyApp
    })), !statusModalVisibility && !settingModalVisibility && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
      className: `${prefix}-language-container`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
        className: `${prefix}-header`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
          className: `${prefix}-modal-header-inner`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
            className: `${prefix}-step-label`,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("STEP 1 OF 3", "automlp-ai-translation-for-wpml")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("h2", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Select Languages", "automlp-ai-translation-for-wpml")
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("button", {
          type: "button",
          className: `${prefix}-modal-close`,
          onClick: destroyApp,
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Close", "automlp-ai-translation-for-wpml"),
          "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Close", "automlp-ai-translation-for-wpml"),
          children: "\xD7"
        })]
      }), errorMessage && errorMessage !== "" ? errorModal ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_components_error_modal_box__WEBPACK_IMPORTED_MODULE_7__["default"], {
        message: errorMessage,
        onClose: closeErrorModal
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
        className: `${prefix}-error-message`,
        dangerouslySetInnerHTML: {
          __html: dompurify__WEBPACK_IMPORTED_MODULE_9__["default"].sanitize(errorMessage)
        }
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
          className: `${prefix}-body`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(SelectLanguageNotice, {}), wizardSelectedCode ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
            className: `${prefix}-languages`,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
              className: `${prefix}-languages-enabled-list`,
              children: (() => {
                const defaultSlug = automlp_wpml_bulk_translate_object.default_language_slug;
                const allCodes = Object.keys(languageObject).filter(lang => !defaultSlug || defaultSlug !== lang);
                const selectedFirst = wizardSelectedCode && allCodes.includes(wizardSelectedCode) ? [wizardSelectedCode, ...allCodes.filter(l => l !== wizardSelectedCode)] : allCodes;
                return selectedFirst.map(language => {
                  if (!languageObject[language]) return null;
                  const isDisabled = !postIds.length && !isStringTranslationPage || wizardSelectedCode && language !== wizardSelectedCode;

                  // Only show enabled items in this column
                  if (isDisabled) return null;
                  const isSelected = selectedLanguages.includes(language);
                  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
                      className: `${prefix}-language ${isDisabled ? `${prefix}-language-item--disabled` : ''} ${isSelected ? `${prefix}-language-item--selected` : ''}`,
                      title: !postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : languageObject[language].name,
                      onClick: e => {
                        if (e.target.closest('input') || e.target.closest('label')) return;
                        if (isDisabled) return;
                        if (isSelected) {
                          setSelectedLanguages(selectedLanguages.filter(l => l !== language));
                        } else {
                          setSelectedLanguages([...selectedLanguages, language]);
                        }
                      },
                      role: "button",
                      tabIndex: isDisabled ? -1 : 0,
                      onKeyDown: e => {
                        if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                          e.preventDefault();
                          if (isSelected) {
                            setSelectedLanguages(selectedLanguages.filter(l => l !== language));
                          } else {
                            setSelectedLanguages([...selectedLanguages, language]);
                          }
                        }
                      },
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
                        className: `${prefix}-language-item`,
                        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("input", {
                          type: "checkbox",
                          name: "languages",
                          id: language,
                          value: language,
                          onChange: handleLanguageChange,
                          disabled: isDisabled,
                          checked: isSelected,
                          className: `${prefix}-language-checkbox-input`
                        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
                          className: `${prefix}-check-visual`,
                          "aria-hidden": "true"
                        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("label", {
                          htmlFor: language,
                          className: `${prefix}-language-label`,
                          title: languageObject[language].name,
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("img", {
                            src: languageObject[language].flag,
                            alt: languageObject[language].name
                          }), "\xA0 ", languageObject[language].name]
                        })]
                      })
                    })
                  }, language);
                });
              })()
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
              className: `${prefix}-languages-disabled-lists`,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("p", {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Multiple language translation available in Pro.', 'wpml-translation-check'), "\xA0", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("a", {
                  href: "#",
                  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Buy Pro Version to Unlock All Languages', 'wpml-translation-check'),
                  className: `${prefix}-buy-pro-version-link`,
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Upgrade now', 'wpml-translation-check')
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
                children: (() => {
                  const defaultSlug = automlp_wpml_bulk_translate_object.default_language_slug;
                  const allCodes = Object.keys(languageObject).filter(lang => !defaultSlug || defaultSlug !== lang);
                  const selectedFirst = wizardSelectedCode && allCodes.includes(wizardSelectedCode) ? [wizardSelectedCode, ...allCodes.filter(l => l !== wizardSelectedCode)] : allCodes;
                  return selectedFirst.map(language => {
                    if (!languageObject[language]) return null;
                    const isDisabled = !postIds.length && !isStringTranslationPage || wizardSelectedCode && language !== wizardSelectedCode;

                    // Only show disabled items in this column
                    if (!isDisabled) return null;
                    const isSelected = selectedLanguages.includes(language);
                    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), {
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
                        className: `${prefix}-language ${isDisabled ? `${prefix}-language-item--disabled` : ''} ${isSelected ? `${prefix}-language-item--selected` : ''}`,
                        title: !postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : languageObject[language].name,
                        onClick: e => {
                          if (e.target.closest('input') || e.target.closest('label')) return;
                          if (isDisabled) return;
                          if (isSelected) {
                            setSelectedLanguages(selectedLanguages.filter(l => l !== language));
                          } else {
                            setSelectedLanguages([...selectedLanguages, language]);
                          }
                        },
                        role: "button",
                        tabIndex: isDisabled ? -1 : 0,
                        onKeyDown: e => {
                          if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                            e.preventDefault();
                            if (isSelected) {
                              setSelectedLanguages(selectedLanguages.filter(l => l !== language));
                            } else {
                              setSelectedLanguages([...selectedLanguages, language]);
                            }
                          }
                        },
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
                          className: `${prefix}-language-item`,
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("input", {
                            type: "checkbox",
                            name: "languages",
                            id: language,
                            value: language,
                            onChange: handleLanguageChange,
                            disabled: isDisabled,
                            checked: isSelected,
                            className: `${prefix}-language-checkbox-input`
                          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
                            className: `${prefix}-check-visual`,
                            "aria-hidden": "true"
                          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("label", {
                            htmlFor: language,
                            className: `${prefix}-language-label`,
                            title: languageObject[language].name,
                            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("img", {
                              src: languageObject[language].flag,
                              alt: languageObject[language].name
                            }), "\xA0 ", languageObject[language].name]
                          })]
                        })
                      })
                    }, language);
                  });
                })()
              })]
            })]
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
            className: `${prefix}-wizard-language-notice`,
            style: {
              padding: '12px 16px',
              marginTop: 8,
              background: '#f0f6fc',
              border: '1px solid #c3c4c7',
              borderRadius: 4
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("p", {
              style: {
                margin: '0 0 8px',
                fontSize: 14
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please select a translation language first.', 'wpml-translation-check')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("a", {
              href: wizardLanguagesUrl,
              style: {
                fontSize: 14
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select language in Setup Wizard (Languages step)', 'wpml-translation-check')
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
          className: `${prefix}-footer`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("button", {
            className: `${prefix}-footer-button button button-primary`,
            onClick: destroyApp,
            title: !postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : "",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Cancel", "automlp-ai-translation-for-wpml")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("button", {
            className: `${prefix}-footer-button button button-primary`,
            onClick: settingModalVisibilityHandler,
            disabled: !postIds.length && !isStringTranslationPage || !selectedLanguages.length,
            title: !postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : !selectedLanguages.length ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Please select at least one language", "automlp-ai-translation-for-wpml") : "",
            children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Next", "automlp-ai-translation-for-wpml"), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
              className: `${prefix}-next-arrow`,
              children: "\u2192"
            })]
          })]
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (App);

/***/ },

/***/ "./assets/src/string-translate/bulk-translate.js"
/*!*******************************************************!*\
  !*** ./assets/src/string-translate/bulk-translate.js ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   bulkTranslateEntries: () => (/* binding */ bulkTranslateEntries),
/* harmony export */   bulkTranslateStrings: () => (/* binding */ bulkTranslateStrings),
/* harmony export */   initBulkTranslateStrings: () => (/* binding */ initBulkTranslateStrings)
/* harmony export */ });
/* harmony import */ var _helper__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./helper */ "./assets/src/string-translate/helper/index.js");
/* harmony import */ var _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/translate-provider/local-ai/local-ai-translate */ "./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js");
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);






// NOTE: The post/taxonomy bulk-translate pipeline (initBulkTranslate,
// translateContent, updateContent, bulkTranslateEntries) has been removed
// from this file. This module now only contains string-translation logic.
// Post bulk translation uses assets/src/bulk-translate/bulk-translate.js.

const bulkTranslateEntries = async ({
  ids,
  langs,
  storeDispatch
}) => {
  // This function is kept only for API compatibility but is no-op in the
  // string-translate context. Post/taxonomy bulk translation is handled by
  // assets/src/bulk-translate/bulk-translate.js.
  return {
    success: false,
    message: "bulkTranslateEntries is not used for strings"
  };
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
  stringLanguageStatus = {}
}) => {
  const nonce = automlp_wpml_bulk_translate_object.nonce;
  const stringsByLanguage = {};
  const totalPerLanguage = {};
  const stringKeys = []; // only used for count; we don't put 10k keys in Redux

  for (const lang of langs) {
    let strings = [];
    let total = 0;
    const hasLocalSelected = selectedStrings && Object.keys(selectedStrings).length > 0 && Array.isArray(stringFilters?.selected_string_ids) && stringFilters.selected_string_ids.length > 0;

    // Build rows from selected strings, then filter out already-translated for this language
    const rows = (stringFilters.selected_string_ids || []).map(id => selectedStrings[String(id)]).filter(Boolean);
    const rowsToTranslate = rows.filter(row => {
      const stringId = String(row.string_id);
      return stringLanguageStatus[stringId]?.[lang] !== "edit";
    });
    strings = rowsToTranslate.map(row => ({
      text: row.value || "",
      html: row.value || "",
      field_key: String(row.string_id),
      field_name: row.name || String(row.string_id),
      format: "html"
    }));
    total = strings.length;
    if (total === 0 || !strings.length) continue;
    totalPerLanguage[lang] = total;
    stringsByLanguage[lang] = strings;
    const flagUrl = automlp_wpml_bulk_translate_object.languageObject[lang]?.flag || "";
    const languageName = automlp_wpml_bulk_translate_object.languageObject[lang]?.name || lang;

    // One Redux entry per language
    const key = `strings_${lang}`;
    stringKeys.push(key);
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updatePendingPosts)([key]));
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateTranslatePostInfo)({
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
        completed: 0
      }
    }));
  }
  if (stringKeys.length === 0) {
    return {
      success: false,
      message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No strings found to translate.", "automlp-ai-translation-for-wpml")
    };
  }
  const totalStrings = Object.values(totalPerLanguage).reduce((a, b) => a + b, 0);
  storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCountInfo)({
    totalPosts: totalStrings
  }));
  return {
    success: true,
    stringKeys,
    stringsByLanguage,
    totalPerLanguage,
    nonce
  };
};
/**
 * Translate an array of plain strings using Chrome built-in AI (client-side).
 * Returns Promise<string[]> with same order as input, or rejects on error.
 */
const translateStringsWithChromeAI = (strings, sourceLang, targetLang) => {
  const languageObject = automlp_wpml_bulk_translate_object?.languageObject || {};
  const textContentObject = strings.reduce((acc, text, i) => {
    acc[i] = text || "";
    return acc;
  }, {});
  return new Promise((resolve, reject) => {
    const translations = [];
    _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_1__["default"].Object({
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
      onLanguageError: err => reject(err?.message ? new Error(err.message) : new Error("Chrome AI translation failed"))
    }).then(translatorObj => {
      if (!translatorObj?.init || !translatorObj?.startTranslation) {
        reject(new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Chrome AI is not available. Use Chrome and enable the Translation API.", "automlp-ai-translation-for-wpml")));
        return;
      }
      translatorObj.init(textContentObject);
      translatorObj.startTranslation();
    }).catch(reject);
  });
};

/**
 * Initialize bulk translation for strings.
 * Pipeline: fetch one page (500) → translate → save → fetch next page. Never holds more than 500 strings in memory.
 */
const initBulkTranslateStrings = async (stringKeys = [], stringsByLanguage = {}, nonce, storeDispatch, prefix, updateDestoryHandler, totalPerLanguage = {}) => {
  const pendingPosts = _redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState().pendingPosts;
  if (pendingPosts.length < 1) return;
  let modalClosed = false;
  updateDestoryHandler(() => {
    modalClosed = true;
  });
  const sourceLang = automlp_wpml_bulk_translate_object.default_language_slug || "en";
  const BATCH_SIZE = 500;
  const translateStringsForLanguage = async (lang, initialStrings) => {
    if (!initialStrings?.length || modalClosed) return;
    const activeProvider = _redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState().serviceProvider;
    let serviceSlug = activeProvider;
    if (serviceSlug?.endsWith("_ai")) serviceSlug = serviceSlug.replace("_ai", "");
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
        const stringsToTranslate = batch.map(str => ({
          text: str.text || str.html || "",
          field_key: str.field_key
        }));
        let translationResponse;
        if (activeProvider === "localAiTranslator") {
          const translations = await translateStringsWithChromeAI(stringsToTranslate.map(s => s.text), sourceLang, lang);
          translationResponse = {
            success: true,
            data: {
              translations
            }
          };
        } else {
          translationResponse = await (0,_helper__WEBPACK_IMPORTED_MODULE_0__.AITranslationRequest)({
            controller,
            Strings: stringsToTranslate.map(s => s.text),
            slug: serviceSlug,
            source_language: sourceLang,
            target_language: lang
          });
        }
        if (translationResponse?.success && translationResponse.data) {
          let translations = [];
          const data = translationResponse.data;

          // 1) AI SDK route: { translate_data: { "0": "…", "1": "…" } }
          if (data.translate_data && typeof data.translate_data === "object") {
            translations = Object.keys(data.translate_data).sort((a, b) => Number(a) - Number(b)).map(key => data.translate_data[key] || "");
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
              translated: translatedText
            });
            batchStringsTranslated += 1;
            batchCharsTranslated += sourceText.length;
          });
          let timeTakenSec = 0;
          if (batchToSave.length > 0) {
            timeTakenSec = (Date.now() - batchStartTime) / 1000;
            await saveStringTranslations(lang, batchToSave, nonce);
            if (automlp_wpml_bulk_translate_object?.update_translate_data_nonce) {
              const batchWords = batch.reduce((sum, s) => sum + (typeof (s.text || s.html || "") === "string" ? (s.text || s.html || "").trim().split(/\s+/).filter(Boolean).length : 0), 0);
              const parentKey = `strings_${lang}`;
              const translateInfoKey = `${parentKey}_${lang}`;
              const saveId = `strings_${lang}_${Date.now()}`;
              storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateParentPostsInfo)({
                postId: parentKey,
                data: {
                  wordsCount: batchWords,
                  charactersCount: batchCharsTranslated,
                  stringsCount: batchToSave.length
                }
              }));
              storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateTranslatePostInfo)({
                [translateInfoKey]: {
                  stringsTranslated: batchToSave.length,
                  wordsTranslated: batchWords,
                  charactersTranslated: batchCharsTranslated,
                  duration: timeTakenSec * 1000
                }
              }));
              (0,_helper__WEBPACK_IMPORTED_MODULE_0__.updateTranslateData)({
                provider: serviceSlug || activeProvider,
                sourceLang,
                targetLang: lang,
                parentPostId: parentKey,
                currentPostId: saveId,
                editorType: "strings",
                updateTranslateDataNonce: automlp_wpml_bulk_translate_object.update_translate_data_nonce,
                extraData: {}
              });
            }
          }
          offset += batch.length;
          const state = _redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState();
          const prev = state.translatePostInfo[langKey] || {};
          const completed = (prev.completed || 0) + batch.length;
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateTranslatePostInfo)({
            [langKey]: {
              ...prev,
              status: offset >= totalForLang ? "completed" : "in-progress",
              messageClass: offset >= totalForLang ? "success" : "in-progress",
              completed,
              total: totalForLang
            }
          }));
          const count = state.countInfo || {};
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCountInfo)({
            stringsTranslated: (count.stringsTranslated || 0) + batchStringsTranslated,
            charactersTranslated: (count.charactersTranslated || 0) + batchCharsTranslated,
            timeTaken: (count.timeTaken || 0) + timeTakenSec,
            sourceLanguage: sourceLang,
            serviceProvider: serviceSlug || activeProvider
          }));
          batchStringsTranslated = 0;
          batchCharsTranslated = 0;
          const totalStrings = Object.values(totalPerLanguage).reduce((a, b) => a + b, 0);
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateProgressStatus)(100 * batch.length / totalStrings));
          if (offset >= totalForLang) {
            storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.unsetPendingPost)(langKey));
            storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCompletedPosts)([langKey]));
            return;
          }
        } else {
          const fallbackMsg = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Translation failed", "automlp-ai-translation-for-wpml");
          const raw = translationResponse?.data;
          let errorHtml = "";
          if (typeof raw === "string") {
            errorHtml = raw;
          } else if (raw && typeof raw === "object") {
            errorHtml = JSON.stringify(raw);
          } else {
            errorHtml = fallbackMsg;
          }
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateTranslatePostInfo)({
            [langKey]: {
              status: "error",
              messageClass: "error",
              errorMessage: fallbackMsg,
              errorHtml: '<div class="automlp-wpml-error-html">' + errorHtml + "</div>"
            }
          }));
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.unsetPendingPost)(langKey));
          storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCompletedPosts)([langKey]));
          return;
        }
      } catch (err) {
        const fallbackMsg = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Translation failed", "automlp-ai-translation-for-wpml");
        let errorHtml = err?.message || "";
        if (err?.data && err.data.status) {
          errorHtml = "Error Code:" + err.data.status;
          if (typeof err.data.error === "string") {
            errorHtml += "<br>Error Message:" + err.data.error;
          } else if (typeof err.data.error === "object") {
            errorHtml += "<br>Error Message:" + JSON.stringify(err.data.error);
          }
        } else if (!errorHtml) {
          errorHtml = fallbackMsg;
        }
        storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateTranslatePostInfo)({
          [langKey]: {
            status: "error",
            messageClass: "error",
            errorMessage: fallbackMsg,
            errorHtml: '<div class="automlp-wpml-error-html">' + errorHtml + "</div>"
          }
        }));
        storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.unsetPendingPost)(langKey));
        storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCompletedPosts)([langKey]));
        return;
      }
    }
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.unsetPendingPost)(langKey));
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_2__.updateCompletedPosts)([langKey]));
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
const saveStringTranslations = async (targetLang, translatedStrings, nonce, stats = null) => {
  const ajaxUrl = automlp_wpml_bulk_translate_object.ajax_url;
  const body = {
    action: "automlp_wpml_google_auto_translate_save_string_translations",
    nonce: nonce,
    target_lang: targetLang,
    translated_strings: translatedStrings
  };
  if (stats && typeof stats === "object") {
    body.dashboard_stats = stats;
  }
  try {
    const response = await fetch(ajaxUrl + "?action=automlp_wpml_google_auto_translate_save_string_translations", {
      method: "POST",
      headers: {
        "Content-Type": "application/json; charset=utf-8",
        Accept: "application/json"
      },
      body: JSON.stringify(body)
    });
    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Error saving string translations:", error);
    throw error;
  }
};


/***/ },

/***/ "./assets/src/string-translate/components/copy-clipboard/index.js"
/*!************************************************************************!*\
  !*** ./assets/src/string-translate/components/copy-clipboard/index.js ***!
  \************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
const CopyClipboard = async ({
  text = false,
  startCopyStatus = () => {},
  endCopyStatus = () => {}
}) => {
  if (!text || text === "") return;
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      // Fallback method if Clipboard API is not supported
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      if (document.execCommand) {
        document.execCommand('copy');
      }
      document.body.removeChild(textArea);
    }
    startCopyStatus();
    setTimeout(() => endCopyStatus(), 800); // Reset to "Copy" after 2 seconds
  } catch (err) {
    console.error('Error copying text to clipboard:', err);
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CopyClipboard);

/***/ },

/***/ "./assets/src/string-translate/components/error-modal-box/index.js"
/*!*************************************************************************!*\
  !*** ./assets/src/string-translate/components/error-modal-box/index.js ***!
  \*************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _copy_clipboard__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../copy-clipboard */ "./assets/src/string-translate/components/copy-clipboard/index.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var dompurify__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! dompurify */ "./node_modules/dompurify/dist/purify.es.mjs");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





const ErrorModalBox = ({
  message,
  onClose,
  Title,
  prefix,
  children
}) => {
  let dummyElement = jQuery('<div>').append(message);
  const stringifiedMessage = dummyElement.html();
  dummyElement.remove();
  dummyElement = null;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const clipboardElements = document.querySelectorAll('.chrome-ai-translator-flags');
    if (clipboardElements.length > 0) {
      clipboardElements.forEach(element => {
        element.classList.add(`${prefix}-tooltip-element`);
        element.addEventListener('click', e => {
          e.preventDefault();
          const toolTipExists = element.querySelector(`.${prefix}-tooltip`);
          if (toolTipExists) {
            return;
          }
          let toolTipElement = document.createElement('span');
          toolTipElement.textContent = "Text to be copied.";
          toolTipElement.className = `${prefix}-tooltip`;
          element.appendChild(toolTipElement);
          (0,_copy_clipboard__WEBPACK_IMPORTED_MODULE_0__["default"])({
            text: element.getAttribute('data-clipboard-text'),
            startCopyStatus: () => {
              toolTipElement.classList.add(`${prefix}-tooltip-active`);
            },
            endCopyStatus: () => {
              setTimeout(() => {
                toolTipElement.remove();
              }, 800);
            }
          });
        });
      });
      return () => {
        clipboardElements.forEach(element => {
          element.removeEventListener('click', () => {});
        });
      };
    }
  }, []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
    className: `${prefix}-error-modal-box-container`,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: `${prefix}-error-modal-box`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: `${prefix}-error-modal-box-header`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          className: `${prefix}-error-modal-box-close`,
          onClick: onClose,
          children: "\xD7"
        }), Title && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
          children: Title
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: `${prefix}-error-modal-box-body`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          dangerouslySetInnerHTML: {
            __html: dompurify__WEBPACK_IMPORTED_MODULE_3__["default"].sanitize(stringifiedMessage)
          }
        }), children]
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ErrorModalBox);

/***/ },

/***/ "./assets/src/string-translate/components/loop-callback/index.js"
/*!***********************************************************************!*\
  !*** ./assets/src/string-translate/components/loop-callback/index.js ***!
  \***********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
const LoopCallback = async ({
  callback,
  loop,
  index
}) => {
  await callback(loop[index], index);
  index++;
  if (index < loop.length) {
    await LoopCallback({
      callback,
      loop,
      index
    });
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (LoopCallback);

/***/ },

/***/ "./assets/src/string-translate/components/notice/index.js"
/*!****************************************************************!*\
  !*** ./assets/src/string-translate/components/notice/index.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

const StringPopUpNotice = props => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("div", {
    className: props.className,
    children: props.children
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (StringPopUpNotice);

/***/ },

/***/ "./assets/src/string-translate/components/store-translate-strings/index.js"
/*!*********************************************************************************!*\
  !*** ./assets/src/string-translate/components/store-translate-strings/index.js ***!
  \*********************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _update_translation_count__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../update-translation-count */ "./assets/src/string-translate/components/update-translation-count/index.js");


const storeTranslateString = (postId, uniqueKey, key, value, provider, lang, storeDispatch) => {
  (0,_update_translation_count__WEBPACK_IMPORTED_MODULE_1__["default"])({
    postId,
    key: uniqueKey,
    lang,
    storeDispatch
  });
  storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_0__.updateTranslatedContent)({
    postId,
    uniqueKey,
    key,
    provider,
    value
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (storeTranslateString);

/***/ },

/***/ "./assets/src/string-translate/components/translate-provider/ai-services/index.js"
/*!****************************************************************************************!*\
  !*** ./assets/src/string-translate/components/translate-provider/ai-services/index.js ***!
  \****************************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _helper__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../helper/index */ "./assets/src/string-translate/helper/index.js");
/* harmony import */ var _store_translate_strings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../store-translate-strings */ "./assets/src/string-translate/components/store-translate-strings/index.js");
/* harmony import */ var ___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! .. */ "./assets/src/string-translate/components/translate-provider/index.js");
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _bulk_translate__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../bulk-translate */ "./assets/src/string-translate/bulk-translate.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../../redux-store/store */ "./assets/src/string-translate/redux-store/store.js");









class AIService {
  constructor({
    postId = '',
    sourceLang = '',
    targetLangs = [],
    totalPosts = 0,
    prefix = '',
    createTranslatePostNonce = '',
    updateContent = () => {},
    storeDispatch = () => {},
    updateDestoryHandler = () => {},
    previousCompletedStrings = 0
  }) {
    this.CONCURRENCY_LIMIT = window?.automlp_wpml_bulk_translate_object?.AIRequestBatchSize || 5;
    this.MAX_TOKENS = window?.automlp_wpml_bulk_translate_object?.AIRequestMaxTokens || 500;
    this.activePostId = postId;
    this.activeTargetLangs = '';
    this.sourceLang = sourceLang;
    this.targetLangs = targetLangs;
    this.totalPosts = totalPosts;
    this.prefix = prefix;
    this.storeDispatch = storeDispatch;
    this.updateContent = updateContent;
    this.createTranslatePostNonce = createTranslatePostNonce;
    this.APIcontroller = [];
    this.stopProcess = false;
    this.aiProviders = Object.keys((0,___WEBPACK_IMPORTED_MODULE_2__["default"])()).filter(provider => provider.endsWith('_ai'));
    this.previousCompletedStrings = previousCompletedStrings;
    this.uniqueIds = [];
    this.modalClosed = false;
    const aiModal = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectServiceProvider)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState());
    this.service = typeof aiModal === 'string' ? aiModal.replace('_ai', '') : aiModal;
    this.Entries = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectTargetContent)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState(), postId);
    this.availableContentTypes = (0,_helper__WEBPACK_IMPORTED_MODULE_0__.translateFieldNameSort)((0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.availableContentTypes)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState(), postId));
    updateDestoryHandler(() => {
      this.modalClosed = true;
      this.service && this.abortAllRequests('Modal Closed');
    });
  }
  initTranslation = async () => {
    this.abortAllRequests('Restart Translation');
    const currentPostInfo = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectPendingPosts)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState());
    const runLoop = async (lang, index) => {
      if (index >= this.targetLangs.length) return;
      this.completedPostStatus = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectProgressStatus)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState());
      const beforeInit = await this.beforeInit(lang);
      if (currentPostInfo.includes(this.activePostId + '_' + lang) && beforeInit) {
        this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_3__.updateTranslatePostInfo)({
          [this.activePostId + '_' + lang]: {
            status: 'running',
            messageClass: ''
          }
        }));
        this.activeTargetLangs = lang;
        this.uniqueIds = [];
        this.completedStrings = this.previousCompletedStrings;
        this.allStrings = this.allStringsFilter(this.activeTargetLangs);
        this.stringsBatches = this.calculateTokensInBatches(this.allStrings);
        this.totalStrings = Object.keys(this.allStrings).length + this.previousCompletedStrings;
        this.stopProcess = false;
        this.pendingStrings = false;
        this.errorCount = 0;
        this.requestIndex = 0;
        this.errorMessage = "";
        this.scrollDebounce = null;
        this.isProcessing = false;
        this.stringCount = 0;
        this.wordCount = 0;
        this.characterCount = 0;
        this.emptyBatches = 0;
        if (this.previousCompletedStrings > 0) {
          await new Promise(resolve => setTimeout(resolve, 100));
          this.updateProgressBar({
            totalStrings: this.totalStrings,
            completedStrings: this.completedStrings,
            previousCompletedStrings: this.previousCompletedStrings,
            completedPostStatus: this.completedPostStatus
          });
          await new Promise(resolve => setTimeout(resolve, 400));
        }
        await this.processChunkBatch();
      }
      if (!this.modalClosed) {
        index++;
        await runLoop(this.targetLangs[index], index);
      }
    };
    if (this.targetLangs.length > 0) {
      await runLoop(this.targetLangs[0], 0);
    }
  };
  allStringsFilter = activeLang => {
    const strings = {};
    const translatedEntries = _redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState().translatedContent[this.activePostId];
    Object.keys(this.Entries).forEach((key, index) => {
      const value = this.Entries[key];
      let shortcode = value.trim().startsWith('[') && value.trim().endsWith(']');
      this.uniqueIds.push(key);
      if (shortcode && !translatedEntries[key]?.translation?.[this.service + '_ai']?.[activeLang]) {
        const id = key;
        if (value && id && value !== '' && id !== '') {
          this.aiProviders.forEach(provider => {
            this.targetLangs.forEach(targetLang => {
              (0,_store_translate_strings__WEBPACK_IMPORTED_MODULE_1__["default"])(this.activePostId, key, targetLang, value, provider, targetLang, this.storeDispatch);
            });
          });
        }
      } else if (!translatedEntries[key]?.translation?.[this.service + '_ai']?.[activeLang]) {
        strings[index] = value;
      }
    });
    return strings;
  };
  calculateTokensInBatches = strings => {
    let selectedStringsBatch = {};
    let totalTokensBatch = 0;
    let selectedStringsBatches = [];
    const entries = Object.entries(strings);

    // Loop through each string to calculate tokens and organize them into batches
    for (let i = 0; i < entries.length; i++) {
      const [key, value] = entries[i];
      const length = value.length;
      const tokens = Math.ceil(length / 4);

      // Add the string to the current batch if it doesn't exceed the maximum tokens
      if (totalTokensBatch + tokens <= this.MAX_TOKENS) {
        selectedStringsBatch[key] = value;
        totalTokensBatch += tokens;
      } else {
        // If adding the string exceeds the maximum tokens, start a new batch
        selectedStringsBatches.push(selectedStringsBatch);
        selectedStringsBatch = {
          [key]: value
        };
        totalTokensBatch = tokens;
      }
    }

    // Add the last batch if it contains any strings
    if (Object.keys(selectedStringsBatch).length > 0) {
      selectedStringsBatches.push(selectedStringsBatch);
    }
    return selectedStringsBatches;
  };
  processChunkBatch = async () => {
    if (this.isProcessing) return;
    this.isProcessing = true;
    const stringsBatches = [...this.stringsBatches];
    if (stringsBatches.length === 0) {
      // this.translateStatusHandler(false);
      return;
    }
    const timeStart = new Date();
    try {
      await this.runRequest();
      this.processCompleteHandler(timeStart);
    } catch (error) {
      this.pendingStrings = true;
      this.processCompleteHandler(timeStart);
      if (error.name === 'AbortError') {
        const errorMessage = this.errorMessage && this.errorMessage.includes('You exceeded your current quota') ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('You have exceeded you current plan limit. that\'s why the request is aborted.', 'wpml-translation-check') : error;
        console.warn(errorMessage);
      } else {
        console.error('An error occurred during the AJAX processing:', error);
      }
    }
  };
  processCompleteHandler = async startTime => {
    const timeStart = startTime || new Date();
    const endTime = new Date();
    const duration = endTime - timeStart;
    const previousDuration = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectTranslatePostInfo)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState())?.[this.activePostId + '_' + this.activeTargetLangs]?.duration || 0;
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_3__.updateTranslatePostInfo)({
      [this.activePostId + '_' + this.activeTargetLangs]: {
        duration: previousDuration + duration
      }
    }));
    const pendingStrings = this.pendingStrings;
    this.isProcessing = false;
    if (pendingStrings) {
      this.updateTotalProgressStatus(this.totalStrings, this.completedStrings);
      this.pendingRequest();
      return;
    }
    this.updateTotalProgressStatus(this.totalStrings, this.completedStrings);
    this.updateContent(this.activeTargetLangs);
  };
  makeAjaxRequest = async batch => {
    const controller = new AbortController();
    this.APIcontroller.push(controller);
    const keys = Object.keys(batch);
    const strings = Object.assign({}, Object.values(batch));
    if (Object.values(batch).length === 0) {
      return;
    }
    const response = await (0,_helper__WEBPACK_IMPORTED_MODULE_0__.AITranslationRequest)({
      controller: controller,
      Strings: strings,
      slug: this.service,
      source_language: this.sourceLang,
      target_language: this.activeTargetLangs
    });
    if (response.success && response.data && response.data.translate_data) {
      if (Object.keys(response.data.translate_data).length > 0) {
        this.errorCount = 0;
        const firstKey = Object.keys(response.data.translate_data)[0];
        await this.updateData(firstKey, response.data.translate_data, keys);
      }
    } else {
      this.pendingStrings = true;
      if (response.success && !response.data.translate_data && response.data.text) {
        console.group('Automatic Translation Error');
        console.warn('Empty response');
        console.log(batch);
        console.groupEnd();
      }
      if (!response.success) {
        this.errorMessage = response.data;
        console.group('Automatic Translation Error');
        console.log('%c' + response.data, 'color: red; font-weight: bold; font-size: 1.2rem;');
        console.groupEnd();
        this.errorCount++;
        if (this.errorCount >= 5 || response.data.includes('service is not available.')) {
          this.stopProcess = true;
          this.abortAllRequests('Error Count Exceeded');
        }
        if (response.data.includes('You exceeded your current quota')) {
          this.stopProcess = true;
          this.abortAllRequests('Quota Exceeded');
        }
      }
    }
  };
  runRequest = async () => {
    const batch = this.stringsBatches.slice(0, this.CONCURRENCY_LIMIT);
    this.requestIndex = this.CONCURRENCY_LIMIT - 1;
    const processRequest = async item => {
      await this.makeAjaxRequest(item);
      if (this.requestIndex < this.stringsBatches.length - 1 && !this.stopProcess) {
        this.requestIndex++;
        return await processRequest(this.stringsBatches[this.requestIndex]);
      } else {
        return;
      }
    };
    await Promise.all(batch.map(async item => {
      await processRequest(item);
    }));
  };
  abortAllRequests = (reason = 'Unknown Reason') => {
    this.stopProcess = true;
    this.pendingStrings = true;
    try {
      this.APIcontroller.forEach(controller => {
        if (controller.signal && !controller.signal.aborted) {
          controller.abort(reason);
        }
      });
    } catch (error) {
      if (error.name === 'AbortError') {
        console.warn('Request aborted');
      } else {
        console.error('An error occurred during the AJAX processing:', error);
      }
      return;
    }
    this.scrollDebounce && clearTimeout(this.scrollDebounce);
    this.APIcontroller = [];
  };
  updateProgressBar = ({
    totalStrings,
    completedStrings,
    completedPostStatus
  }) => {
    const status = completedStrings / totalStrings * 100;
    let completedPercentage = (Math.round(status * 10) / 10).toFixed(1);
    completedPercentage = Math.min(completedPercentage, 100);
    let currentPostStatus = completedPercentage;
    currentPostStatus = Math.round(currentPostStatus);
    currentPostStatus = Math.min(currentPostStatus, 100);
    const progressBarCircular = document.querySelector(`.${this.prefix}-progress-bar-circular[data-id="${this.activePostId}_${this.activeTargetLangs}"]`);
    if (progressBarCircular) {
      progressBarCircular.querySelector(`.${this.prefix}-percentage`).innerHTML = currentPostStatus + '%';
      progressBarCircular.querySelector(`.${this.prefix}-progress`).style.strokeDasharray = currentPostStatus + ', 100';
    }
    let previousCompletedStatus = this.previousCompletedStrings / totalStrings * 100;
    previousCompletedStatus = (Math.round(previousCompletedStatus * 10) / 10).toFixed(1);
    previousCompletedStatus = Math.min(previousCompletedStatus, 100);
    let totalProgress = completedPostStatus + (completedPercentage - previousCompletedStatus) / this.totalPosts;
    const totalProgressBar = document.querySelector(`.${this.prefix}-overall-progress .${this.prefix}-progress`);
    if (totalProgressBar) {
      totalProgress = totalProgress.toFixed(2);
      totalProgress = Math.min(totalProgress, 100);
      totalProgressBar.style.width = totalProgress + '%';
      totalProgressBar.innerHTML = totalProgress + '%';
    }
  };
  updateTotalProgressStatus = (totalStrings, completedStrings) => {
    const status = completedStrings / totalStrings * 100;
    let completedPercentage = (Math.round(status * 10) / 10).toFixed(1);
    completedPercentage = Math.min(completedPercentage, 100);
    let previousCompletedStatus = this.previousCompletedStrings / totalStrings * 100;
    previousCompletedStatus = (Math.round(previousCompletedStatus * 10) / 10).toFixed(1);
    previousCompletedStatus = Math.min(previousCompletedStatus, 100);
    const singlePost = 100 / this.totalPosts;
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_3__.updateProgressStatus)((completedPercentage - previousCompletedStatus) / 100 * singlePost));
  };
  updateData = async (index, data, keys) => {
    if (this.stopProcess) return;
    const entry = data[index];
    const key = keys[index];
    if (entry && entry !== '') {
      const id = this.uniqueIds[key];
      const value = data[index];
      this.completedStrings = this.completedStrings;
      ++this.completedStrings;
      this.updateProgressBar({
        totalStrings: this.totalStrings,
        completedStrings: this.completedStrings,
        completedPostStatus: this.completedPostStatus
      });
      if (value && value !== '') {
        (0,_store_translate_strings__WEBPACK_IMPORTED_MODULE_1__["default"])(this.activePostId, id, this.activeTargetLangs, value, this.service + '_ai', this.activeTargetLangs, this.storeDispatch);
      }
    }
    delete data[index];
    if (Object.keys(data).length > 0) {
      const firstKey = Object.keys(data)[0];
      await this.updateData(firstKey, data, keys);
    }
  };
  pendingRequest = () => {
    const totalStrings = this.totalStrings;
    const completedStrings = this.completedStrings;
    const status = completedStrings / totalStrings * 100;
    let completedPercent = (Math.round(status * 10) / 10).toFixed(1);
    completedPercent = Math.min(completedPercent, 100);
    let notCompletedPercent = 100 - completedPercent;
    notCompletedPercent = (Math.round(notCompletedPercent * 10) / 10).toFixed(1);
    notCompletedPercent = Math.min(notCompletedPercent, 100);
    let errorMessage = '';
    let translateBtnMessage = '';
    const limitExceeded = this.errorMessage && this.errorMessage.includes('You exceeded your current quota');
    if (limitExceeded) {
      errorMessage = `<p class="${this.prefix}-ai-pending-request-heading">` + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('You’ve exceeded your current plan limit.', 'wpml-translation-check') + '</p> ' + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('To continue, please check your plan details and update your API key.', 'wpml-translation-check');
      translateBtnMessage = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Click "Translate" after updating your API key to re-translate the remaining strings.', 'wpml-translation-check');
    } else {
      errorMessage = `<p class="${this.prefix}-ai-pending-request-heading">` + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Oops! Something went wrong during translation', 'wpml-translation-check') + '</p>';
      translateBtnMessage = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Click "Translate" to re-translate the remaining strings.', 'wpml-translation-check');
    }
    const message = `<div class="${this.prefix}-ai-pending-request">
                    <div>${errorMessage}</div>
                    <p>${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('To see more details, open your browser’s developer console.', 'wpml-translation-check')}</p>

                <p>✅ ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('You’ve translated %s of the strings.', 'wpml-translation-check'), completedPercent + '%')}</p>
                <p>❌ ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('%s of the strings are still not translated.', 'wpml-translation-check'), notCompletedPercent + '%')}</p>

                <p><strong>${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Next Steps:', 'wpml-translation-check')}</strong></p>

                <p>${translateBtnMessage}</p>
                <p><strong>${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('OR', 'wpml-translation-check')}</strong></p>
                <p>${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Click "Continue" to proceed without translating the rest of the strings.', 'wpml-translation-check')}</p>
                </div>`;
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_3__.updateTranslatePostInfo)({
      [this.activePostId + '_' + this.activeTargetLangs]: {
        status: 'error',
        messageClass: 'error',
        errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Translation failed.', 'wpml-translation-check'),
        errorHtml: message,
        aiError: true,
        nonce: this.createTranslatePostNonce,
        completedStrings,
        totalPosts: this.totalPosts
      }
    }));
  };
  static translateComplete = async ({
    postId,
    targetLang,
    storeDispatch,
    prefix,
    updateDestoryHandler,
    nonce,
    closeErrorModal,
    totalPosts,
    completedStrings
  }) => {
    const postContent = _redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState().parentPostsInfo[postId];
    if (!postContent) return;
    const {
      originalContent: {
        title,
        content
      },
      editorType,
      sourceLanguage
    } = postContent;
    const source = {
      title: title,
      content: JSON.parse(JSON.stringify(content))
    };
    const updateContent = async lang => {
      await (0,_bulk_translate__WEBPACK_IMPORTED_MODULE_4__.updateContent)({
        source,
        postId,
        sourceLang: sourceLanguage,
        lang,
        editorType,
        createTranslatePostNonce: nonce,
        storeDispatch
      });
    };
    const aiService = new AIService({
      postId,
      targetLangs: [targetLang],
      sourceLang: sourceLanguage,
      totalPosts,
      storeDispatch,
      updateContent,
      createTranslatePostNonce: nonce,
      prefix,
      updateDestoryHandler,
      totalPosts,
      previousCompletedStrings: completedStrings
    });
    const allStrings = aiService.allStringsFilter(targetLang);
    const completedPostStatus = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_6__.selectProgressStatus)(_redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState());
    aiService.updateProgressBar({
      totalStrings: Object.keys(allStrings).length + completedStrings,
      completedStrings: Object.keys(allStrings).length + completedStrings,
      completedPostStatus: completedPostStatus
    });
    aiService.updateTotalProgressStatus(Object.keys(allStrings).length + completedStrings, Object.keys(allStrings).length + completedStrings);
    closeErrorModal();
    await new Promise(resolve => setTimeout(resolve, 400));
    updateContent(targetLang);
  };
  static translateAgain = async ({
    postId,
    targetLang,
    storeDispatch,
    prefix,
    updateDestoryHandler,
    nonce,
    closeErrorModal,
    completedStrings,
    totalPosts
  }) => {
    const postContent = _redux_store_store__WEBPACK_IMPORTED_MODULE_7__.store.getState().parentPostsInfo[postId];
    if (!postContent) return;
    const {
      originalContent: {
        title,
        content
      },
      languages,
      editorType,
      sourceLanguage
    } = postContent;
    const source = {
      title: title,
      content: JSON.parse(JSON.stringify(content))
    };
    const updateContent = async lang => {
      await (0,_bulk_translate__WEBPACK_IMPORTED_MODULE_4__.updateContent)({
        source,
        postId,
        sourceLang: sourceLanguage,
        lang,
        editorType,
        createTranslatePostNonce: nonce,
        storeDispatch
      });
    };
    closeErrorModal();
    await new Promise(resolve => setTimeout(resolve, 500));
    const aiService = new AIService({
      postId,
      targetLangs: [targetLang],
      sourceLang: sourceLanguage,
      totalPosts,
      storeDispatch,
      updateContent,
      createTranslatePostNonce: nonce,
      prefix,
      updateDestoryHandler,
      totalPosts,
      previousCompletedStrings: completedStrings
    });
    await aiService.initTranslation();
  };
  beforeInit = async targetLang => {
    return true;
  };
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AIService);

/***/ },

/***/ "./assets/src/string-translate/components/translate-provider/index.js"
/*!****************************************************************************!*\
  !*** ./assets/src/string-translate/components/translate-provider/index.js ***!
  \****************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _local_ai__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./local-ai */ "./assets/src/string-translate/components/translate-provider/local-ai/index.js");
/* harmony import */ var _ai_services__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./ai-services */ "./assets/src/string-translate/components/translate-provider/ai-services/index.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
// import YandexTranslater from "./yandex";




/**
 * Provides translation services using Yandex Translate.
 */

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (props => {
  props = props || {};
  const {
    Service = false,
    openErrorModalHandler = () => {},
    prefix = ''
  } = props;
  const adminUrl = window.automlp_wpml_bulk_translate_object.admin_url;
  const assetsUrl = window.automlp_wpml_bulk_translate_object.automlp_wpml_url + 'assets/images/';
  const errorIcon = assetsUrl + 'error-icon.svg';
  const Services = {
    localAiTranslator: {
      Provider: _local_ai__WEBPACK_IMPORTED_MODULE_0__["default"],
      title: "Chrome Built-in AI",
      SettingBtnText: "Translate",
      serviceLabel: "Chrome AI Translator",
      heading: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Translate Using %s", "automlp-ai-translation-for-wpml"), "Chrome built-in API"),
      Docs: "https://docs.coolplugins.net/doc/chrome-ai-translation-polylang/?utm_source=automlp_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_chrome",
      BetaEnabled: true,
      ButtonDisabled: props.localAiTranslatorButtonDisabled,
      ErrorMessage: props.localAiTranslatorButtonDisabled ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: `${prefix}-provider-error`,
        onClick: () => openErrorModalHandler(props.localAiTranslatorButtonDisabled),
        children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('View Error', 'wpml-translation-check')]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {}),
      Logo: 'chrome.png',
      filterHtmlContent: true
    },
    openai_ai: {
      Provider: _ai_services__WEBPACK_IMPORTED_MODULE_1__["default"],
      title: "OpenAI Model",
      SettingBtnText: "Translate",
      serviceLabel: "OpenAI",
      heading: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Translate Using %s Model", "automlp-ai-translation-for-wpml"), "OpenAI"),
      Docs: "https://docs.coolplugins.net/doc/translate-via-open-ai-polylang/?utm_source=automlp_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_openai",
      BetaEnabled: true,
      ButtonDisabled: props.openai_aiButtonDisabled,
      ErrorMessage: props.openai_aiButtonDisabled ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("a", {
        className: `${prefix}-provider-error`,
        href: adminUrl + 'admin.php?page=automlp_ai_dashboard&tab=settings',
        target: "_blank",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add API Key', 'wpml-translation-check')
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {}),
      Logo: 'openai.png',
      filterHtmlContent: true
    },
    google_ai: {
      Provider: _ai_services__WEBPACK_IMPORTED_MODULE_1__["default"],
      title: "Gemini Model",
      SettingBtnText: "Translate",
      serviceLabel: "Gemini",
      heading: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Translate Using %s Model", "automlp-ai-translation-for-wpml"), "Gemini"),
      Docs: "https://docs.coolplugins.net/doc/translate-via-gemini-ai-polylang/?utm_source=automlp_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_gemini",
      BetaEnabled: true,
      ButtonDisabled: props.google_aiButtonDisabled,
      ErrorMessage: props.google_aiButtonDisabled ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("a", {
        className: `${prefix}-provider-error`,
        href: adminUrl + 'admin.php?page=automlp_ai_dashboard&tab=settings',
        target: "_blank",
        children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add API Key', 'wpml-translation-check')]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {}),
      Logo: 'gemini.png',
      filterHtmlContent: true
    }
  };
  if (!Service) {
    return Services;
  }
  return Services[Service];
});

/***/ },

/***/ "./assets/src/string-translate/components/translate-provider/local-ai/index.js"
/*!*************************************************************************************!*\
  !*** ./assets/src/string-translate/components/translate-provider/local-ai/index.js ***!
  \*************************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _local_ai_translate__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./local-ai-translate */ "./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js");
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var _store_translate_strings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../store-translate-strings */ "./assets/src/string-translate/components/store-translate-strings/index.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _helper_index__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../helper/index */ "./assets/src/string-translate/helper/index.js");








// Define a class for LocalAiTranslate
class LocalAiTranslate {
  constructor({
    sourceLang = 'en',
    targetLangs = false,
    updateContent,
    totalPosts,
    storeDispatch,
    postId,
    prefix,
    updateDestoryHandler
  }) {
    this.textContentObject = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__.selectTargetContent)(_redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState(), postId);
    this.totalTranslatedLength = Object.keys(this.textContentObject).length;
    this.sourceLang = sourceLang;
    this.targetLangs = targetLangs;
    this.localAiTranslator = null;
    this.textContentObjectKeys = Object.keys(this.textContentObject);
    this.translateKeysLength = this.textContentObjectKeys.length;
    this.updateContent = updateContent;
    this.totalPosts = totalPosts;
    this.storeDispatch = storeDispatch;
    this.completedPostStatus = 0;
    this.postId = postId;
    this.activeTargetLangs = '';
    this.prefix = prefix;
    this.serviceProvider = _redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState().serviceProvider;
    this.availableContentTypes = (0,_helper_index__WEBPACK_IMPORTED_MODULE_6__.translateFieldNameSort)((0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__.availableContentTypes)(_redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState(), postId));
    updateDestoryHandler(() => {
      this.destroy();
    });
    this.stopTranslation = false;
  }
  destroy = () => {
    this.stopTranslation = true;
    if (this.localAiTranslator && this.localAiTranslator.hasOwnProperty('stopTranslation')) {
      this.localAiTranslator.stopTranslation();
    }
  };

  // Function to create Local AI Translator
  async createLocalAiTranslator(targetLang, index) {
    this.completedTranslateIndex = 0;
    this.localAiTranslator = null;
    if (this.stopTranslation) return;
    const languageObject = automlp_wpml_bulk_translate_object.languageObject;
    this.completedPostStatus = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__.selectProgressStatus)(_redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState());
    this.activeTargetLangs = targetLang;
    this.localAiTranslator = await _local_ai_translate__WEBPACK_IMPORTED_MODULE_0__["default"].Object({
      sourceLanguage: this.sourceLang,
      targetLanguage: targetLang,
      sourceLanguageLabel: languageObject[this.sourceLang].name,
      targetLanguageLabel: languageObject[targetLang].name,
      onAfterTranslate: this.onAfterTranslate,
      onComplete: this.onComplete,
      onLanguageError: this.onLanguageError
    });
    if (this.localAiTranslator.hasOwnProperty('init')) {
      this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateTranslatePostInfo)({
        [this.postId + '_' + targetLang]: {
          status: 'running',
          messageClass: ''
        }
      }));
      await this.translateContent(0);
      if (!this.stopTranslation) {
        this.updateContent(targetLang);
      }
    }
    if (index < this.targetLangs.length - 1 && !this.stopTranslation) {
      await this.createLocalAiTranslator(this.targetLangs[index + 1], index + 1);
    }
  }
  onLanguageError = data => {
    let html = false;
    if (data.html) {
      html = data.html[0]?.outerHTML;
    }
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.unsetPendingPost)(this.postId + '_' + this.activeTargetLangs));
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateProgressStatus)(100 / this.totalPosts));
    this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateTranslatePostInfo)({
      [this.postId + '_' + this.activeTargetLangs]: {
        status: 'error',
        messageClass: 'error',
        errorMessage: data.message,
        errorHtml: html
      }
    }));
  };
  onAfterTranslate = (key, value) => {
    if (this.stopTranslation) return;
    (0,_store_translate_strings__WEBPACK_IMPORTED_MODULE_4__["default"])(this.postId, key, this.activeTargetLangs, value, this.serviceProvider, this.activeTargetLangs, this.storeDispatch);
    this.completedTranslateIndex++;
    let completedPercentage = this.completedTranslateIndex / this.totalTranslatedLength * 100;
    completedPercentage = completedPercentage.toFixed(2);
    completedPercentage = Math.min(completedPercentage, 100);
    let completedPostStatus = completedPercentage;
    completedPostStatus = Math.round(completedPostStatus);
    completedPostStatus = Math.min(completedPostStatus, 100);
    const progressBarCircular = document.querySelector(`.${this.prefix}-progress-bar-circular[data-id="${this.postId}_${this.activeTargetLangs}"]`);
    if (progressBarCircular) {
      progressBarCircular.querySelector(`.${this.prefix}-percentage`).innerHTML = completedPostStatus + '%';
      progressBarCircular.querySelector(`.${this.prefix}-progress`).style.strokeDasharray = completedPostStatus + ', 100';
    }
    let totalProgress = this.completedPostStatus + completedPercentage / this.totalPosts;
    const totalProgressBar = document.querySelector(`.${this.prefix}-overall-progress .${this.prefix}-progress`);
    if (totalProgressBar) {
      totalProgress = totalProgress.toFixed(2);
      totalProgress = Math.min(totalProgress, 100);
      totalProgressBar.style.width = totalProgress + '%';
      totalProgressBar.innerHTML = totalProgress + '%';
    }
  };
  onComplete = () => {
    if (this.stopTranslation) return;
    if (this.completedTranslateIndex === this.totalTranslatedLength) {
      const endTime = new Date();
      const duration = endTime - this.startTime;
      const previousDuration = (0,_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__.selectTranslatePostInfo)(_redux_store_store__WEBPACK_IMPORTED_MODULE_3__.store.getState())?.[this.postId + '_' + this.activeTargetLangs]?.duration || 0;
      this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateTranslatePostInfo)({
        [this.postId + '_' + this.activeTargetLangs]: {
          duration: previousDuration + duration
        }
      }));
      this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateProgressStatus)(100 / this.totalPosts));
    }
  };

  // Function to translate content
  async translateContent(index) {
    const textObject = JSON.parse(JSON.stringify(this.textContentObject));
    if (Object.keys(textObject).length > 0 && !this.stopTranslation) {
      this.startTime = new Date();
      await this.localAiTranslator.init(textObject);
      await this.localAiTranslator.startTranslation();
    }
  }

  // Function to initialize translation if conditions are met
  async initTranslation() {
    if (this.textContentObject && Object.keys(this.textContentObject).length > 0 && this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
      await this.createLocalAiTranslator(this.targetLangs[0], 0);
    } else if (this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
      this.targetLangs.forEach(lang => {
        this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.unsetPendingPost)(this.postId + '_' + lang));
        this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateProgressStatus)(100 / this.totalPosts));
        this.storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_1__.updateTranslatePostInfo)({
          [this.postId + '_' + lang]: {
            status: 'error',
            messageClass: 'error',
            errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('No content to translate', 'wpml-translation-check'),
            errorHtml: false
          }
        }));
      });
    }
  }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (LocalAiTranslate);

/***/ },

/***/ "./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js"
/*!**************************************************************************************************!*\
  !*** ./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js ***!
  \**************************************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
class ChromeAiTranslator {
  // Static method to create an instance of ChromeAiTranslator and return extra data
  static Object = options => {
    const selfObject = new this(options);
    return selfObject.extraData();
  };

  // Constructor to initialize the translator with options
  constructor(options) {
    this.textContentObject = options.textContentObject;
    this.onStartTranslationProcess = options.onStartTranslationProcess || (() => {}); // Callback for when translation starts
    this.onComplete = options.onComplete || (() => {}); // Callback for when translation completes
    this.onLanguageError = options.onLanguageError || (() => {}); // Callback for language errors
    this.onBeforeTranslate = options.onBeforeTranslate || (() => {}); // Callback for before translation
    this.onAfterTranslate = options.onAfterTranslate || (() => {}); // Callback for after translation
    this.sourceLanguage = options.sourceLanguage || "en"; // Default source language
    this.targetLanguage = options.targetLanguage || "hi"; // Default target language
    this.sourceLanguageLabel = options.sourceLanguageLabel || "English"; // Default source language label
    this.targetLanguageLabel = options.targetLanguageLabel || "Hindi"; // Default target language label
  }

  // Method to check language support and return relevant data
  extraData = async () => {
    // Check if the language is supported
    const langSupportedStatus = await ChromeAiTranslator.languageSupportedStatus(this.sourceLanguage, this.targetLanguage, this.targetLanguageLabel, this.sourceLanguageLabel);
    if (langSupportedStatus !== true) {
      this.onLanguageError(langSupportedStatus); // Handle language error
      return {}; // Return empty object if language is not supported
    }
    this.defaultLang = this.targetLanguage; // Set default language

    // Return methods for translation control
    return {
      continueTranslation: this.continueTranslation,
      stopTranslation: this.stopTranslation,
      startTranslation: this.startTranslation,
      reInit: this.reInit,
      init: this.init
    };
  };
  static supportedLanguages = ['en', 'es', 'ja', 'ar', 'de', 'bn', 'fr', 'hi', 'it', 'ko', 'nl', 'pl', 'pt', 'ru', 'th', 'tr', 'vi', 'zh', 'zh-hant', 'bg', 'cs', 'da', 'el', 'fi', 'hr', 'hu', 'id', 'iw', 'lt', 'no', 'ro', 'sk', 'sl', 'sv', 'uk', 'kn', 'ta', 'te', 'mr'].map(lang => lang.toLowerCase());

  /**
   * Checks if the specified source and target languages are supported by the Local Translator AI modal.
   * 
   * @param {string} sourceLanguage - The language code for the source language (e.g., "en" for English).
   * @param {string} targetLanguage - The language code for the target language (e.g., "hi" for Hindi).
   * @param {string} targetLanguageLabel - The label for the target language (e.g., "Hindi").
   * @returns {Promise<boolean|jQuery>} - Returns true if the languages are supported, or a jQuery message if not.
   */
  static languageSupportedStatus = async (sourceLanguage, targetLanguage, targetLanguageLabel, sourceLanguageLabel) => {
    const supportedLanguages = ChromeAiTranslator.supportedLanguages;
    const safeBrowser = window.location.protocol === 'https:';
    const browserContentSecure = window?.isSecureContext;

    // Browser check
    if (!window.hasOwnProperty('chrome') || !navigator.userAgent.includes('Chrome') || navigator.userAgent.includes('Edg')) {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Important Notice:</strong>
                <ol>
                    <li>The Translator API, which leverages Chrome local AI models, is designed specifically for use with the Chrome browser.</li>
                    <li>For comprehensive information about the Translator API, <a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank">click here</a>.</li>
                </ol>
                <p>Please ensure you are using the Chrome browser for optimal performance and compatibility.</p>
            </span>`);
      return {
        html: message,
        message: 'Browser not supported',
        type: 'browser-not-supported'
      };
    }
    if (!('translation' in self && 'createTranslator' in self.translation) && !('ai' in self && 'translator' in self.ai) && !("Translator" in self && "create" in self.Translator) && !safeBrowser && !browserContentSecure) {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Important Notice:</strong>
                <ol>
                    <li>
                        The Translator API is not functioning due to an insecure connection.
                    </li>
                    <li>
                        Please switch to a secure connection (HTTPS) or add this URL to the list of insecure origins treated as secure by visiting 
                        <span data-clipboard-text="chrome://flags/#unsafely-treat-insecure-origin-as-secure" target="_blank" class="chrome-ai-translator-flags">
                            chrome://flags/#unsafely-treat-insecure-origin-as-secure ${ChromeAiTranslator.svgIcons('copy')}
                        </span>.
                        Click on the URL to copy it, then open a new window and paste this URL to access the settings.
                    </li>
                </ol>
            </span>`);
      return {
        html: message,
        message: 'Browser not supported',
        type: 'browser-not-supported'
      };
    }

    // Check if the translation API is available
    if (!('translation' in self && 'createTranslator' in self.translation) && !('ai' in self && 'translator' in self.ai) && !("Translator" in self && "create" in self.Translator)) {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Steps to Enable the Translator AI Modal:</h4>
                <ol>
                    <li>Open this URL in a new Chrome tab: <strong><span data-clipboard-text="chrome://flags/#translation-api" target="_blank" class="chrome-ai-translator-flags">chrome://flags/#translation-api ${ChromeAiTranslator.svgIcons('copy')}</span></strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure that the <strong>Experimental translation API</strong> option is set to <strong>Enabled</strong>.</li>
                    <li>Click on the <strong>Save</strong> button to apply the changes.</li>
                    <li>The Translator AI modal should now be enabled and ready for use.</li>
                </ol>
                <p>For more information, please refer to the <a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank">documentation</a>.</p>   
                <p>If the issue persists, please ensure that your browser is up to date and restart your browser.</p>
                <p>If you continue to experience issues after following the above steps, please <a href="https://my.coolplugins.net/account/support-tickets/" target="_blank" rel="noopener">open a support ticket</a> with our team. We are here to help you resolve any problems and ensure a smooth translation experience.</p>
            </span>`);
      return {
        html: message,
        message: 'Translation API not available',
        type: 'translation-api-not-available'
      };
    }

    // Check if the target language is supported
    if (!supportedLanguages.includes(targetLanguage.toLowerCase())) {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Language Support Information:</strong>
                <ol>
                    <li>The current version of Chrome AI Translator does not support the Target Language <strong>${targetLanguageLabel} (${targetLanguage})</strong></li>
                    <li>To view the list of supported languages, please <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure your Chrome browser is updated to the latest version for optimal performance.</li>
                </ol>
            </span>`);
      return {
        html: message,
        message: `Target Language not supported: ${targetLanguageLabel} (${targetLanguage})`,
        type: 'language-not-supported'
      };
    }

    // Check if the source language is supported
    if (!supportedLanguages.includes(sourceLanguage.toLowerCase())) {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Language Support Information:</strong>
                <ol>
                    <li>The current version of Chrome AI Translator does not support the Source Language <strong>${sourceLanguageLabel} (${sourceLanguage})</strong></li>
                    <li>To view the list of supported languages, please <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure your Chrome browser is updated to the latest version for optimal performance.</li>
                </ol>
            </span>`);
      return {
        html: message,
        message: `Source Language not supported: ${sourceLanguageLabel} (${sourceLanguage})`,
        type: 'language-not-supported'
      };
    }

    // Check if translation can be performed
    const status = await ChromeAiTranslator.languagePairAvality(sourceLanguage, targetLanguage);

    // Handle case for language pack after download
    if (status === "after-download" || status === "downloadable" || status === "unavailable") {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Installation Instructions for Language Packs:</h4>
                <ol>
                    <li>
                        To proceed, please install the language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong>.
                    </li>
                    <li>
                        After installing the language pack, add this language to your browser's system languages in Chrome settings.<br>
                        Go to <strong>Settings &gt; Languages &gt; Add languages</strong> and add <strong>${targetLanguageLabel}</strong> or <strong>${sourceLanguageLabel}</strong> to your preferred languages list & reload the page.
                    </li>
                    <li>
                        You can install it by visiting the following link: 
                        <strong>
                            <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">
                                chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}
                            </span>
                        </strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.
                    </li>
                    <li>
                        Please check if both your source <strong>(<span style="color:#2271b1">${sourceLanguage}</span>)</strong> and target <strong>(<span style="color:#2271b1">${targetLanguage}</span>)</strong> languages are available in the language packs list.
                    </li>
                    <li>
                        You need to install both language packs for translation to work. You can search for each language by its language code: <strong>${sourceLanguage}</strong> and <strong>${targetLanguage}</strong>.
                    </li>
                    <li>For more help, refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a>.</li>
                </ol>
            </span>`);
      return {
        html: message,
        message: `Language pack not installed: ${targetLanguageLabel} (${targetLanguage}) or ${sourceLanguageLabel} (${sourceLanguage})`,
        type: 'language-pack-not-installed'
      };
    }

    // Handle case for language pack downloading
    if (status === "downloading") {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Language Pack Download In Progress:</h4>
                <ol>
                    <li>
                        The language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong> is already being downloaded.
                    </li>
                    <li>
                        <strong>You do not need to start the download again.</strong> Please wait for the download to complete. Once finished, the translation feature will become available automatically.
                    </li>
                    <li>
                        You can check the download progress by opening:
                        <strong>
                            <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">
                                chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}
                            </span>
                        </strong>
                        . Click on the URL to copy it, then open a new window and paste this URL in Chrome to view the status.
                    </li>
                    <li>
                        <strong>What to do next:</strong>
                        <ul style="margin-top: .5em;">
                            <li>Wait for the download to finish. The status will change to <strong>Ready</strong> or <strong>Installed</strong> in the <strong>Language Packs</strong> section.</li>
                            <li>After the language pack is installed, you may need to <strong>reload</strong> or <strong>restart</strong> your browser for the changes to take effect.</li>
                        </ul>
                    </li>
                    <li>
                        For more help, refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a>.
                    </li>
                </ol>
                <div style="text-align: right;">
                    <button onclick="location.reload()" class="automlp-wpml-error-reload-btn">Reload Page</button>
                </div>
            </span>`);
      return {
        html: message,
        message: `Language pack downloading please wait or try again...`,
        type: 'language-pack-downloading'
      };
    }

    // Handle case for language pack not readily available
    if (status !== 'readily' && status !== 'available') {
      const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Language Pack Installation Required</h4>
                <ol>
                    <li>Please ensure that the language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong> is installed and set as a preferred language in your browser.</li>
                    <li>To install the language pack, visit <strong><span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span></strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>If you encounter any issues, please refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a> for further assistance.</li>
                </ol>
            </span>`);
      return {
        html: message,
        message: `Language pack missing for ${targetLanguageLabel} (${targetLanguage}) or ${sourceLanguageLabel} (${sourceLanguage})`,
        type: 'language-pack-missing'
      };
    }
    return true;
  };
  static languagePairAvality = async (source, target) => {
    if ('translation' in self && 'createTranslator' in self.translation) {
      const status = await self.translation.canTranslate({
        sourceLanguage: source,
        targetLanguage: target
      });
      return status;
    } else if ('ai' in self && 'translator' in self.ai) {
      const translatorCapabilities = await self.ai.translator.capabilities();
      const status = await translatorCapabilities.languagePairAvailable(source, target);
      return status;
    } else if ("Translator" in self && "create" in self.Translator) {
      let status = await self.Translator.availability({
        sourceLanguage: source,
        targetLanguage: target
      });
      if (status !== "available") {
        try {
          // MUST be triggered by a user gesture (button click)
          const translator = await self.Translator.create({
            sourceLanguage: source,
            targetLanguage: target,
            monitor(monitor) {
              monitor.addEventListener("downloadprogress", e => {
                console.log(`Downloaded ${Math.round(e.loaded * 100)}%`);
              });
            }
          });

          // Re-check availability AFTER model creation
          status = await self.Translator.availability({
            sourceLanguage: source,
            targetLanguage: target
          });
        } catch (err) {
          console.warn(`Translator init for ${source} to ${target} error:`, err);
          if (status.includes('Requires a user gesture when availability')) {
            return 'requires-user-gesture';
          }
          return status;
        }
      }
      return status;
    }
    return false;
  };
  AITranslator = async targetLanguage => {
    if ('translation' in self && 'createTranslator' in self.translation) {
      const translator = await self.translation.createTranslator({
        sourceLanguage: this.sourceLanguage,
        targetLanguage
      });
      return translator;
    } else if ('ai' in self && 'translator' in self.ai) {
      const translator = await self.ai.translator.create({
        sourceLanguage: this.sourceLanguage,
        targetLanguage
      });
      return translator;
    } else if ("Translator" in self && "create" in self.Translator) {
      const translator = await self.Translator.create({
        sourceLanguage: this.sourceLanguage,
        targetLanguage
      });
      return translator;
    }
    return false;
  };

  // Method to initialize the translation process
  init = async textContentArray => {
    this.textContent = textContentArray;
    this.textContentKeys = Object.keys(this.textContent);
    this.translationStart = false; // Flag to indicate if translation has started
    this.completedTranslateIndex = 0;
    this.completedCharacterCount = 0; // Count of characters translated
  };

  // Method to start the translation process
  startTranslationProcess = async () => {
    this.onStartTranslationProcess(); // Call the start translation callback
    const langCode = this.defaultLang; // Get the default language code

    this.translationStart = true; // Set translation start flag

    // Create a translator instance
    this.translator = await this.AITranslator(langCode);
    if (this.textContentKeys.length > 0 && this.textContentKeys.length > this.completedTranslateIndex) {
      await this.stringTranslation(this.completedTranslateIndex);
    }
  };

  // Method to translate a specific string at the given index
  stringTranslation = async index => {
    if (!this.translateStatus) return; // Exit if translation is stopped

    let ele = document.createElement('div'); // Get the element to translate
    ele.innerHTML = this.textContent[this.textContentKeys[index]];
    this.onBeforeTranslate(ele); // Call the before translation callback
    const orignalText = ele.innerText;
    let originalString = [];
    if (ele.childNodes.length > 0 && !ele.querySelector('.notranslate')) {
      ele.childNodes.forEach(child => {
        if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
          originalString.push(child);
        } else if (child.classList && !child.classList.contains('notranslate')) {
          this.filterInnerTextNodes(child, originalString);
        }
      });
    } else if (ele.querySelector('.notranslate')) {
      ele.childNodes.forEach(child => {
        if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
          originalString.push(child);
        } else if (child.classList && !child.classList.contains('notranslate')) {
          this.filterInnerTextNodes(child, originalString);
        }
      });
    }
    if (originalString.length > 0) {
      await this.stringTranslationBatch(originalString, 0);
    }
    this.completedTranslateIndex = index;
    this.completedCharacterCount += orignalText.length; // Update character count

    this.textContent[this.textContentKeys[index]] = ele.innerText;
    this.onAfterTranslate(this.textContentKeys[index], this.textContent[this.textContentKeys[index]]); // Call the after translation callback

    ele.remove();
    ele = null;
    if (this.textContentKeys.length > this.completedTranslateIndex + 1) {
      await this.stringTranslation(this.completedTranslateIndex + 1);
    }
    if (index === this.textContentKeys.length - 1) {
      this.onComplete({
        characterCount: this.completedCharacterCount
      }); // Call the complete callback
    }
  };
  filterInnerTextNodes = (ele, updatedArray) => {
    const childElements = ele.childNodes;
    if (ele.classList && ele.classList.contains('notranslate')) return;
    childElements.forEach(child => {
      if (child.nodeType === 3 && child.nodeValue.trim() !== '') {
        updatedArray.push(child);
      } else if (child.classList && !child.classList.contains('notranslate')) {
        this.filterInnerTextNodes(child, updatedArray);
      }
    });
  };
  translateChildNodes = async (ele, originalString) => {
    if (ele.childNodes.length > 0 && !ele.querySelector('.notranslate')) {
      ele.childNodes.forEach(child => {
        if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
          originalString.push(child);
        } else if (child.childNodes.length > 0) {
          this.translateChildNodes(child, originalString);
        }
      });
    } else if (ele.querySelector('.notranslate')) {
      ele.childNodes.forEach(child => {
        if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
          originalString.push(child);
        } else if (child.childNodes.length > 0) {
          this.translateChildNodes(child, originalString);
        }
      });
    }
  };
  stringTranslationBatch = async (originalString, index) => {
    const translatedString = await this.translator.translate(originalString[index].nodeValue); // Translate the string

    if (translatedString && '' !== translatedString) {
      originalString[index].nodeValue = translatedString; // Set the translated string
    }
    if (index < originalString.length - 1) {
      await this.stringTranslationBatch(originalString, index + 1);
    }
    return true;
  };

  // Method to stop the translation process
  stopTranslation = () => {
    this.translateStatus = false; // Set translation status to false
  };

  // Method to reinitialize button events
  reInit = () => {
    this.translateBtnEvents(); // Re-setup button events
  };

  // Method to start translation from the current index
  startTranslation = async () => {
    this.translateStatus = true; // Set translation status to true
    await this.startTranslationProcess(); // Start translation process
  };
  static svgIcons = iconName => {
    const Icons = {
      'copy': `<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" height="16px" width="16px" xmlns="http://www.w3.org/2000/svg" fill="#2271b1"><path d="M433.941 65.941l-51.882-51.882A48 48 0 0 0 348.118 0H176c-26.51 0-48 21.49-48 48v48H48c-26.51 0-48 21.49-48 48v320c0 26.51 21.49 48 48 48h224c26.51 0 48-21.49 48-48v-48h80c26.51 0 48-21.49 48-48V99.882a48 48 0 0 0-14.059-33.941zM266 464H54a6 6 0 0 1-6-6V150a6 6 0 0 1 6-6h74v224c0 26.51 21.49 48 48 48h96v42a6 6 0 0 1-6 6zm128-96H182a6 6 0 0 1-6-6V54a6 6 0 0 1 6-6h106v88c0 13.255 10.745 24 24 24h88v202a6 6 0 0 1-6 6zm6-256h-64V48h9.632c1.591 0 3.117.632 4.243 1.757l48.368 48.368a6 6 0 0 1 1.757 4.243V112z"></path></svg>`
    };
    return Icons[iconName] || '';
  };
}

/*
 * Example Usage of the ChromeAiTranslator.init method.
 * This method initializes the Chrome AI Translator with a comprehensive set of configuration options to facilitate the translation process.
 * 
 * Configuration Options:
 * 
 * - mainWrapperSelector: A CSS selector for the main wrapper element that encapsulates all translation-related elements.
 * - btnSelector: A CSS selector for the button that initiates the translation process.
 * - btnClass: A custom class for styling the translation button.
 * - btnText: The text displayed on the translation button.
 * - stringSelector: A CSS selector for the elements that contain the strings intended for translation.
 * - progressBarSelector: A CSS selector for the progress bar element that visually represents the translation progress.
 * - sourceLanguage: The language code representing the source language (e.g., "es" for Spanish).
 * - targetLanguage: The language code representing the target language (e.g., "fr" for French).
 * - onStartTranslationProcess: A callback function that is executed when the translation process begins.
 * - onBeforeTranslate: A callback function that is executed prior to each individual translation.
 * - onAfterTranslate: A callback function that is executed following each translation.
 * - onComplete: A callback function that is executed upon the completion of the translation process.
 * - onLanguageError: A callback function that is executed when a language-related error occurs.
 */

// Example for checking language support status
// ChromeAiTranslator.languageSupportedStatus("en", "fr", "French");

// const chromeAiTranslatorObject = ChromeAiTranslator.Object(
//     {
//         mainWrapperSelector: ".main-wrapper", // CSS selector for the main wrapper element
//         btnSelector: ".translator-container .translator-button", // CSS selector for the translation button
//         btnClass: "Btn_custom_class", // Custom class for button styling
//         btnText: "Translate To French", // Text displayed on the translation button
//         stringSelector: ".translator-body .translation-item", // CSS selector for translation string elements
//         progressBarSelector: ".translator-progress-bar", // CSS selector for the progress bar
//         sourceLanguage: "es", // Language code for the source language
//         targetLanguage: "fr", // Language code for the target language
//         onStartTranslationProcess: () => { console.log("Translation process started."); }, // Callback for translation start
//         onBeforeTranslate: () => { console.log("Before translation."); }, // Callback before each translation
//         onAfterTranslate: () => { console.log("After translation."); }, // Callback after each translation
//         onComplete: () => { console.log("Translation completed."); }, // Callback for completion
//         onLanguageError: () => { console.error("Language error occurred."); } // Callback for language errors
//     }
// );
// chromeAiTranslatorObject.init();

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ChromeAiTranslator);

/***/ },

/***/ "./assets/src/string-translate/components/update-translation-count/index.js"
/*!**********************************************************************************!*\
  !*** ./assets/src/string-translate/components/update-translation-count/index.js ***!
  \**********************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _redux_store_features_actions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../redux-store/features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");
/* harmony import */ var _helper__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../helper */ "./assets/src/string-translate/helper/index.js");




const updateTranslationCount = ({
  postId,
  key,
  lang,
  storeDispatch
}) => {
  const sourceText = _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().translatedContent[postId]?.[key]?.source;
  if (sourceText) {
    const contentCounts = (0,_helper__WEBPACK_IMPORTED_MODULE_3__.getContentCount)(sourceText);
    const stringCount = contentCounts.stringsCount;
    const wordCount = contentCounts.wordsCount;
    const characterCount = contentCounts.charactersCount;
    const previousPostInfo = _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().translatePostInfo[postId + '_' + lang];
    const previousStringCount = previousPostInfo?.stringsTranslated || 0;
    const previousCharacterCount = previousPostInfo?.charactersTranslated || 0;
    const previousWordCount = previousPostInfo?.wordsTranslated || 0;
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_0__.updateTranslatePostInfo)({
      [postId + '_' + lang]: {
        stringsTranslated: previousStringCount + stringCount,
        charactersTranslated: previousCharacterCount + characterCount,
        wordsTranslated: previousWordCount + wordCount
      }
    }));
    storeDispatch((0,_redux_store_features_actions__WEBPACK_IMPORTED_MODULE_0__.updateCountInfo)({
      stringsTranslated: _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().countInfo.stringsTranslated + stringCount,
      charactersTranslated: _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().countInfo.charactersTranslated + characterCount
    }));
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (updateTranslationCount);

/***/ },

/***/ "./assets/src/string-translate/helper/index.js"
/*!*****************************************************!*\
  !*** ./assets/src/string-translate/helper/index.js ***!
  \*****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AITranslationRequest: () => (/* binding */ AITranslationRequest),
/* harmony export */   getContentCount: () => (/* binding */ getContentCount),
/* harmony export */   reTranslationFieldMatch: () => (/* binding */ reTranslationFieldMatch),
/* harmony export */   translateFieldNameSort: () => (/* binding */ translateFieldNameSort),
/* harmony export */   updateTranslateData: () => (/* binding */ updateTranslateData)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");



const updateTranslateData = ({
  provider,
  sourceLang,
  targetLang,
  parentPostId,
  currentPostId,
  editorType,
  updateTranslateDataNonce,
  extraData = {}
}) => {
  if (!updateTranslateDataNonce || !currentPostId || !parentPostId || !provider || !sourceLang || !targetLang || !editorType) return;
  const parentPostInfo = _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().parentPostsInfo?.[parentPostId] || {};
  const translateData = _redux_store_store__WEBPACK_IMPORTED_MODULE_1__.store.getState().translatePostInfo?.[parentPostId + "_" + targetLang] || {};
  let sourceCount = {
    wordsCount: parentPostInfo.wordsCount || 0,
    charactersCount: parentPostInfo.charactersCount || 0,
    stringsCount: parentPostInfo.stringsCount || 0
  };
  const totalStringCount = translateData.stringsTranslated || 0;
  const totalWordCount = translateData.wordsTranslated || 0;
  const totalCharacterCount = translateData.charactersTranslated || 0;
  const timeTaken = (translateData.duration || 0) / 1000;
  const sourceWordCount = sourceCount.wordsCount;
  const sourceCharacterCount = sourceCount.charactersCount;
  const sourceStringCount = sourceCount.stringsCount;
  const date = new Date().toISOString();
  const ajaxUrl = automlp_wpml_bulk_translate_object.ajax_url;
  const data = {
    provider,
    totalStringCount,
    totalWordCount,
    totalCharacterCount,
    editorType,
    date,
    sourceStringCount,
    sourceWordCount,
    sourceCharacterCount,
    sourceLang,
    targetLang,
    timeTaken,
    action: automlp_wpml_bulk_translate_object.update_translate_data,
    automlp_wpml_nonce: updateTranslateDataNonce,
    post_id: currentPostId,
    ajax_url: ajaxUrl,
    extraData: JSON.stringify(extraData),
    bulk_translate: true
  };
  fetch(ajaxUrl, {
    method: "POST",
    headers: {
      "content-type": "application/x-www-form-urlencoded; charset=UTF-8",
      Accept: "application/json"
    },
    body: new URLSearchParams(data)
  }).then().catch(error => {
    console.error(error);
  });
};
const AITranslationRequest = async ({
  controller,
  Strings,
  slug,
  source_language,
  target_language
}) => {
  const data = {
    automlp_wpml_nonce: automlp_wpml_bulk_translate_object.ai_translate_nonce,
    action: "automlp_wpml_ai_translation",
    strings: JSON.stringify(Strings),
    source_language: source_language,
    target_language: target_language,
    service_slug: slug
  };
  const response = await fetch(`${automlp_wpml_bulk_translate_object.ai_translate_route_url}/${slug}/translate-text`, {
    method: "POST",
    headers: {
      "content-type": "application/x-www-form-urlencoded; charset=UTF-8",
      Accept: "application/json",
      "X-WP-Nonce": automlp_wpml_bulk_translate_object.ai_translate_route_nonce
    },
    signal: controller.signal,
    body: new URLSearchParams(data)
  });
  if (!response.ok) {
    const error = await response.json();
    console.error("WordPress Error:", error);
  }
  const responseData = await response.json();
  return responseData;
};
const getContentCount = content => {
  const data = {
    charactersCount: 0,
    wordsCount: 0,
    stringsCount: 0
  };
  if (content && content.trim() !== "") {
    data.charactersCount = typeof content === "string" ? content.length : 0;
    data.wordsCount = typeof content === "string" ? content.split(/\s+/).filter(word => /[^\p{L}\p{N}]/.test(word)).length : 0;
    data.stringsCount = typeof content === "string" ? content.split(/(?<=[.!?]+)\s+/).length : 0;
  }
  return data;
};
const translateFieldNameSort = (fieldNames = []) => {
  if (!fieldNames || fieldNames.length === 0) {
    return [];
  }
  const sortedFieldNames = ["title", "excerpt", "content"];
  return sortedFieldNames.filter(field => fieldNames.includes(field));
};
const reTranslationFieldMatch = (old_keys, reTranslationFieldTypes) => {
  return old_keys.join("_") === reTranslationFieldTypes.join("_");
};

/***/ },

/***/ "./assets/src/string-translate/redux-store/features/actions.js"
/*!*********************************************************************!*\
  !*** ./assets/src/string-translate/redux-store/features/actions.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__),
/* harmony export */   resetStore: () => (/* binding */ resetStore),
/* harmony export */   unsetPendingPost: () => (/* binding */ unsetPendingPost),
/* harmony export */   updateBlockParseRules: () => (/* binding */ updateBlockParseRules),
/* harmony export */   updateCompletedPosts: () => (/* binding */ updateCompletedPosts),
/* harmony export */   updateCountInfo: () => (/* binding */ updateCountInfo),
/* harmony export */   updateErrorPostsInfo: () => (/* binding */ updateErrorPostsInfo),
/* harmony export */   updateParentPostsInfo: () => (/* binding */ updateParentPostsInfo),
/* harmony export */   updatePendingPosts: () => (/* binding */ updatePendingPosts),
/* harmony export */   updateProgressStatus: () => (/* binding */ updateProgressStatus),
/* harmony export */   updateServiceProvider: () => (/* binding */ updateServiceProvider),
/* harmony export */   updateSourceContent: () => (/* binding */ updateSourceContent),
/* harmony export */   updateTargetContent: () => (/* binding */ updateTargetContent),
/* harmony export */   updateTargetLanguages: () => (/* binding */ updateTargetLanguages),
/* harmony export */   updateTranslatePostInfo: () => (/* binding */ updateTranslatePostInfo),
/* harmony export */   updateTranslatedContent: () => (/* binding */ updateTranslatedContent),
/* harmony export */   updateTranslationsLanguages: () => (/* binding */ updateTranslationsLanguages)
/* harmony export */ });
/* harmony import */ var _reduxjs_toolkit__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @reduxjs/toolkit */ "./node_modules/@reduxjs/toolkit/dist/redux-toolkit.modern.mjs");

const bulkTranslateStore = (0,_reduxjs_toolkit__WEBPACK_IMPORTED_MODULE_0__.createSlice)({
  name: 'automlp-wpml-bulk-translate',
  initialState: {
    completedPosts: [],
    pendingPosts: [],
    parentPostsInfo: {},
    translatePostInfo: [],
    progressStatus: 0,
    targetLanguages: [],
    countInfo: {
      totalPosts: 0,
      postsTranslated: 0,
      stringsTranslated: 0,
      charactersTranslated: 0,
      errorPosts: 0
    },
    translatedContent: {},
    serviceProvider: '',
    blockParseRules: {},
    errorPostsInfo: {}
  },
  reducers: {
    updateCompletedPosts: (state, action) => {
      state.completedPosts = [...(state.completedPosts || []), ...action.payload];
    },
    updatePendingPosts: (state, action) => {
      state.pendingPosts = [...(state.pendingPosts || []), ...action.payload];
    },
    unsetPendingPost: (state, action) => {
      state.pendingPosts = state.pendingPosts.filter(post => post !== action.payload);
    },
    updateTranslatePostInfo: (state, action) => {
      // Improved: Merge translatePostInfo with new info, supporting both single and multiple updates
      if (action.payload && typeof action.payload === 'object') {
        // If payload is a map of postId -> info
        if (!Array.isArray(action.payload)) {
          state.translatePostInfo = {
            ...state.translatePostInfo,
            ...Object.keys(action.payload).reduce((acc, postId) => {
              acc[postId] = {
                ...(state.translatePostInfo?.[postId] || {}),
                ...action.payload[postId]
              };
              return acc;
            }, {})
          };
        }
      }
    },
    updateProgressStatus: (state, action) => {
      state.progressStatus += action.payload;
    },
    updateCountInfo: (state, action) => {
      state.countInfo = {
        ...state.countInfo,
        ...action.payload
      };
    },
    updateSourceContent: (state, action) => {
      state.translatedContent = {
        ...state.translatedContent,
        [action.payload.postId]: {
          ...(state.translatedContent?.[action.payload.postId] || {}),
          [action.payload.uniqueKey]: {
            ...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}),
            ...{
              source: action.payload.value
            }
          }
        }
      };
    },
    updateTargetContent: (state, action) => {
      state.translatedContent = {
        ...state.translatedContent,
        [action.payload.postId]: {
          ...(state.translatedContent?.[action.payload.postId] || {}),
          [action.payload.uniqueKey]: {
            ...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}),
            ...{
              targetContent: action.payload.value
            }
          }
        }
      };
    },
    updateTranslatedContent: (state, action) => {
      state.translatedContent = {
        ...state.translatedContent,
        [action.payload.postId]: {
          ...(state.translatedContent?.[action.payload.postId] || {}),
          [action.payload.uniqueKey]: {
            ...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}),
            ...{
              translation: {
                ...(action.payload.translation || {}),
                [action.payload.provider]: {
                  ...(action.payload.translation?.[action.payload.provider] || {}),
                  [action.payload.key]: action.payload.value
                }
              }
            }
          }
        }
      };
    },
    updateParentPostsInfo: (state, action) => {
      state.parentPostsInfo = {
        ...state.parentPostsInfo,
        [action.payload.postId]: {
          ...(state.parentPostsInfo?.[action.payload.postId] || {}),
          ...action.payload.data
        }
      };
    },
    updateServiceProvider: (state, action) => {
      if (['localAiTranslator', 'google', 'google_ai', 'openai_ai'].includes(action.payload)) {
        state.serviceProvider = action.payload;
      }
    },
    updateTargetLanguages: (state, action) => {
      if (action.payload.lang && action.payload.lang.length > 0) {
        state.targetLanguages = [...state.targetLanguages, ...action.payload.lang];
      }
    },
    updateBlockParseRules: (state, action) => {
      state.blockParseRules = action.payload;
    },
    updateErrorPostsInfo: (state, action) => {
      state.errorPostsInfo = {
        ...state.errorPostsInfo,
        ...{
          [action.payload.postId]: action.payload.data
        }
      };
    },
    resetStore: state => {
      state.pendingPosts = [];
      state.progressStatus = 0;
      state.completedPosts = [];
      state.translatePostInfo = [];
      state.targetLanguages = [];
      state.serviceProvider = '';
      state.parentPostsInfo = {};
      state.countInfo = {
        totalPosts: 0,
        postsTranslated: 0,
        stringsTranslated: 0,
        charactersTranslated: 0,
        errorPosts: 0
      };
      state.translatedContent = {};
      state.blockParseRules = {};
      state.errorPostsInfo = {};
    }
  }
});
const {
  updateTranslationsLanguages,
  updateCompletedPosts,
  updatePendingPosts,
  unsetPendingPost,
  updateTranslatePostInfo,
  updateProgressStatus,
  updateCountInfo,
  updateSourceContent,
  updateTranslatedContent,
  resetStore,
  updateTargetContent,
  updateParentPostsInfo,
  updateServiceProvider,
  updateTargetLanguages,
  updateBlockParseRules,
  updateErrorPostsInfo
} = bulkTranslateStore.actions;

// Export reducer
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (bulkTranslateStore.reducer);

/***/ },

/***/ "./assets/src/string-translate/redux-store/features/selectors.js"
/*!***********************************************************************!*\
  !*** ./assets/src/string-translate/redux-store/features/selectors.js ***!
  \***********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   availableContentTypes: () => (/* binding */ availableContentTypes),
/* harmony export */   selectBlockParseRules: () => (/* binding */ selectBlockParseRules),
/* harmony export */   selectCompletedPosts: () => (/* binding */ selectCompletedPosts),
/* harmony export */   selectCountInfo: () => (/* binding */ selectCountInfo),
/* harmony export */   selectErrorPostsInfo: () => (/* binding */ selectErrorPostsInfo),
/* harmony export */   selectPendingPosts: () => (/* binding */ selectPendingPosts),
/* harmony export */   selectProgressStatus: () => (/* binding */ selectProgressStatus),
/* harmony export */   selectServiceProvider: () => (/* binding */ selectServiceProvider),
/* harmony export */   selectSourceContent: () => (/* binding */ selectSourceContent),
/* harmony export */   selectSourceEntries: () => (/* binding */ selectSourceEntries),
/* harmony export */   selectTargetContent: () => (/* binding */ selectTargetContent),
/* harmony export */   selectTargetLanguages: () => (/* binding */ selectTargetLanguages),
/* harmony export */   selectTranslatePostInfo: () => (/* binding */ selectTranslatePostInfo),
/* harmony export */   selectTranslatedContent: () => (/* binding */ selectTranslatedContent),
/* harmony export */   targetLanguages: () => (/* binding */ targetLanguages)
/* harmony export */ });
// selector.js — Pure selector functions
const selectServiceProvider = state => state.serviceProvider;
const selectPendingPosts = state => state.pendingPosts;
const selectCompletedPosts = state => state.completedPosts;
const selectTranslatePostInfo = state => state.translatePostInfo;
const selectProgressStatus = state => state.progressStatus;
const selectCountInfo = state => state.countInfo;
const selectBlockParseRules = state => state.blockParseRules;
const selectErrorPostsInfo = state => state.errorPostsInfo;
const selectTranslatedContent = (state, postId, uniqueKey, key, provider) => {
  return state.translatedContent[postId]?.[uniqueKey]?.translation?.[provider]?.[key] || state.translatedContent[postId]?.[uniqueKey]?.source;
};
const selectSourceContent = (state, postId, uniqueKey) => {
  return state.translatedContent[postId]?.[uniqueKey]?.source;
};
const targetLanguages = state => state.targetLanguages;

/**
 * @param {Object} state
 * @param {string} postId
 * @returns {Object}
 */
const selectSourceEntries = (state, postId) => {
  const source = {};
  Object.keys(state.translatedContent[postId]).forEach(key => {
    if (state.translatedContent[postId][key]?.source) {
      source[key] = state.translatedContent[postId][key]?.source;
    }
  });
  return source;
};
const selectTargetContent = (state, postId, fields = false) => {
  const filteredObject = {};
  if (state.translatedContent[postId] && Object.keys(state.translatedContent[postId]).length > 0) {
    Object.keys(state.translatedContent[postId]).forEach(key => {
      if (state.translatedContent[postId][key]?.targetContent && (!fields || fields && typeof fields === 'object' && fields.includes(key.split('_')[0]))) {
        filteredObject[key] = state.translatedContent[postId][key]?.targetContent;
      }
    });
  }
  return filteredObject;
};
const availableContentTypes = (state, postId) => {
  const content = state.parentPostsInfo[postId]?.originalContent;
  if (content) {
    const contentTypes = [];
    Object.keys(content).forEach(key => {
      if (typeof content[key] === 'object' && Object.keys(content[key]).length > 0 || typeof content[key] === 'string' && '' !== content[key]) {
        contentTypes.push(key);
      }
    });
    return contentTypes;
  }
  return [];
};
const selectTargetLanguages = (state, postId) => {
  return state.parentPostsInfo[postId]?.languages;
};

/***/ },

/***/ "./assets/src/string-translate/redux-store/store.js"
/*!**********************************************************!*\
  !*** ./assets/src/string-translate/redux-store/store.js ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   store: () => (/* binding */ store)
/* harmony export */ });
/* harmony import */ var _reduxjs_toolkit__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @reduxjs/toolkit */ "./node_modules/@reduxjs/toolkit/dist/redux-toolkit.modern.mjs");
/* harmony import */ var _features_actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./features/actions */ "./assets/src/string-translate/redux-store/features/actions.js");


const store = (0,_reduxjs_toolkit__WEBPACK_IMPORTED_MODULE_0__.configureStore)({
  reducer: _features_actions__WEBPACK_IMPORTED_MODULE_1__["default"]
});


/***/ },

/***/ "./assets/src/string-translate/setting-modal/body.js"
/*!***********************************************************!*\
  !*** ./assets/src/string-translate/setting-modal/body.js ***!
  \***********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _providers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./providers */ "./assets/src/string-translate/setting-modal/providers.js");
/* harmony import */ var _components_translate_provider__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../components/translate-provider */ "./assets/src/string-translate/components/translate-provider/index.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




const SettingModalBody = props => {
  const {
    prefix,
    localAiModalError
  } = props;
  const ServiceProviders = (0,_components_translate_provider__WEBPACK_IMPORTED_MODULE_1__["default"])();
  const openai_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('openai');
  const google_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('google');
  const deepl_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('deepl');
  const openrouter_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('openrouter');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
    className: `${prefix}-setting-modal-body`,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: `${prefix}-provider-cards`,
      children: Object.keys(ServiceProviders).map(provider => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_providers__WEBPACK_IMPORTED_MODULE_0__["default"], {
        ...props,
        layout: "card",
        selectedProvider: props.selectedProvider,
        onSelectProvider: props.onSelectProvider,
        openai_aiDisabled: openai_aiDisabled,
        google_aiDisabled: google_aiDisabled,
        openrouter_aiDisabled: openrouter_aiDisabled,
        deepl_aiDisabled: deepl_aiDisabled,
        localAiTranslatorDisabled: localAiModalError,
        localAiModalError: localAiModalError,
        openErrorModalHandler: props.errorModalHandler,
        Service: provider
      }, provider))
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SettingModalBody);

/***/ },

/***/ "./assets/src/string-translate/setting-modal/footer.js"
/*!*************************************************************!*\
  !*** ./assets/src/string-translate/setting-modal/footer.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


const SettingModalFooter = ({
  setSettingVisibility,
  prefix,
  selectedProvider,
  onStartTranslation
}) => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
    className: `${prefix}-setting-modal-footer`,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
      type: "button",
      className: `${prefix}-setting-close button`,
      onClick: () => setSettingVisibility(false),
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Back", 'wpml-translation-check')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("button", {
      type: "button",
      className: `${prefix}-setting-start-translation button button-primary`,
      disabled: !selectedProvider,
      onClick: () => {
        if (selectedProvider && onStartTranslation) onStartTranslation();
      },
      children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Start Translation", 'wpml-translation-check'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        className: `${prefix}-next-arrow`,
        children: "\u2192"
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SettingModalFooter);

/***/ },

/***/ "./assets/src/string-translate/setting-modal/header.js"
/*!*************************************************************!*\
  !*** ./assets/src/string-translate/setting-modal/header.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


const SettingModalHeader = ({
  setSettingVisibility,
  prefix
}) => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
    className: `${prefix}-header`,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: `${prefix}-modal-header-inner`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        className: `${prefix}-step-label`,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("STEP 2 OF 3", "automlp-ai-translation-for-wpml")
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("h2", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Select Translation Engine", 'wpml-translation-check')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("p", {
        className: `${prefix}-modal-desc`,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Choose the Ai provider you want to use for your this translation batch.", 'wpml-translation-check')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
      className: `${prefix}-modal-close`,
      onClick: e => setSettingVisibility(e),
      children: "\xD7"
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SettingModalHeader);

/***/ },

/***/ "./assets/src/string-translate/setting-modal/index.js"
/*!************************************************************!*\
  !*** ./assets/src/string-translate/setting-modal/index.js ***!
  \************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var _header__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./header */ "./assets/src/string-translate/setting-modal/header.js");
/* harmony import */ var _body__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./body */ "./assets/src/string-translate/setting-modal/body.js");
/* harmony import */ var _footer__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./footer */ "./assets/src/string-translate/setting-modal/footer.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _components_error_modal_box__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/error-modal-box */ "./assets/src/string-translate/components/error-modal-box/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);








const SettingModal = props => {
  const prefix = props.prefix || 'automlp-wpml-bulk-translate';
  const imgFolder = automlp_wpml_bulk_translate_object.automlp_wpml_url + 'assets/images/';
  const [errorModal, setErrorModal] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [selectedProvider, setSelectedProvider] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const onSelectProvider = service => {
    setSelectedProvider(service);
  };
  const onStartTranslation = () => {
    if (selectedProvider) {
      props.updateProviderHandler(selectedProvider);
    }
  };
  const errorModalHandler = msg => {
    setErrorModal(msg);
  };
  const closeErrorModal = () => {
    setErrorModal(false);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
    children: errorModal ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_components_error_modal_box__WEBPACK_IMPORTED_MODULE_6__["default"], {
      message: errorModal,
      onDestroy: props.onDestory,
      onClose: closeErrorModal,
      Title: "AutoPoly - AI Translation For Polylang (Pro)",
      prefix: prefix
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      id: `${prefix}-setting-modal-container`,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: `${prefix}-setting-modal-content`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_header__WEBPACK_IMPORTED_MODULE_2__["default"], {
          setSettingVisibility: props.onDestory,
          prefix: prefix
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_body__WEBPACK_IMPORTED_MODULE_3__["default"], {
          imgFolder: imgFolder,
          prefix: prefix,
          localAiModalError: props.localAiModalError,
          errorModalHandler: errorModalHandler,
          selectedProvider: selectedProvider,
          onSelectProvider: onSelectProvider
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_footer__WEBPACK_IMPORTED_MODULE_4__["default"], {
          setSettingVisibility: props.onCloseHandler,
          prefix: prefix,
          selectedProvider: selectedProvider,
          onStartTranslation: onStartTranslation
        })]
      })
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SettingModal);

/***/ },

/***/ "./assets/src/string-translate/setting-modal/providers.js"
/*!****************************************************************!*\
  !*** ./assets/src/string-translate/setting-modal/providers.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _components_translate_provider__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../components/translate-provider */ "./assets/src/string-translate/components/translate-provider/index.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



const Providers = props => {
  const service = props.Service;
  const prefix = props.prefix;
  const buttonDisable = props[service + "Disabled"];
  const ActiveService = (0,_components_translate_provider__WEBPACK_IMPORTED_MODULE_0__["default"])({
    Service: service,
    [service + "ButtonDisabled"]: buttonDisable,
    openErrorModalHandler: props.openErrorModalHandler,
    prefix
  });
  const isSelected = props.selectedProvider === service;
  const isDisabled = buttonDisable;
  const handleCardClick = () => {
    if (!isDisabled && props.onSelectProvider) {
      props.onSelectProvider(service);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    className: `${prefix}-provider-card ${isDisabled ? `${prefix}-provider-card--disabled` : ""} ${isSelected ? `${prefix}-provider-card--selected` : ""}`,
    "data-service": service,
    onClick: handleCardClick,
    onKeyDown: e => {
      if ((e.key === "Enter" || e.key === " ") && !isDisabled) {
        e.preventDefault();
        handleCardClick();
      }
    },
    role: "button",
    tabIndex: isDisabled ? -1 : 0,
    "aria-pressed": isSelected,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      className: `${prefix}-provider-card-body`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
        className: `${prefix}-provider-card-icon`,
        "aria-hidden": "true",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("img", {
          src: `${props.imgFolder}${ActiveService.Logo}`,
          alt: ""
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
        className: `${prefix}-provider-card-name`,
        children: ActiveService.title
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
        className: `${prefix}-provider-card-check`,
        "aria-hidden": "true"
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      className: `${prefix}-provider-card-actions`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("a", {
        href: ActiveService.Docs,
        target: "_blank",
        rel: "noopener noreferrer",
        className: `${prefix}-provider-card-docs`,
        title: ActiveService.Docs,
        onClick: e => e.stopPropagation(),
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Docs', 'wpml-translation-check')
      }), isDisabled && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
        className: `${prefix}-provider-card-error`,
        children: ActiveService.ErrorMessage
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Providers);

/***/ },

/***/ "./assets/src/string-translate/status-modal/index.js"
/*!***********************************************************!*\
  !*** ./assets/src/string-translate/status-modal/index.js ***!
  \***********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _bulk_translate__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../bulk-translate */ "./assets/src/string-translate/bulk-translate.js");
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-redux */ "./node_modules/react-redux/dist/react-redux.mjs");
/* harmony import */ var _redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../redux-store/features/selectors */ "./assets/src/string-translate/redux-store/features/selectors.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_error_modal_box__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/error-modal-box */ "./assets/src/string-translate/components/error-modal-box/index.js");
/* harmony import */ var _components_translate_provider_ai_services__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/translate-provider/ai-services */ "./assets/src/string-translate/components/translate-provider/ai-services/index.js");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var dompurify__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! dompurify */ "./node_modules/dompurify/dist/purify.es.mjs");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);










const StatusModal = ({
  postIds,
  selectedLanguages,
  prefix,
  onDestory
}) => {
  const storeDispatch = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useDispatch)();
  const [isLoading, setIsLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [errorModal, setErrorModal] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [errorModalData, setErrorModalData] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const translatePostInfo = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectTranslatePostInfo);
  const [destroyHandlers, setDestroyHandlers] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const errorPostsInfo = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectErrorPostsInfo);
  const pendingPosts = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectPendingPosts);
  const serviceProvider = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectServiceProvider);
  const [progressBarVisibility, setProgressBarVisibility] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [charactersCountVisibility, setCharactersCountVisibility] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [bulkStatus, setBulkStatus] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)("status");
  const countInfo = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectCountInfo);
  let [emptyPostMessage, setEmptyPostMessage] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Translations already exist for all selected %s in the chosen languages. There are no new %s to translate.", "automlp-ai-translation-for-wpml"), automlp_wpml_bulk_translate_object.post_label, automlp_wpml_bulk_translate_object.post_label));
  let progressStatus = (0,react_redux__WEBPACK_IMPORTED_MODULE_2__.useSelector)(_redux_store_features_selectors__WEBPACK_IMPORTED_MODULE_3__.selectProgressStatus);
  progressStatus = progressStatus.toFixed(1);
  progressStatus = Math.min(progressStatus, 100);
  const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const translateContent = async () => {
      const stringFilters = window.wpmlStringFilters || {};
      const selectedStrings = window.wpmlSelectedStrings || {};
      const stringLanguageStatus = window.wpmlStringLanguageStatus || {};

      // String translation flow only (no post/taxonomy flow in this component)
      const {
        bulkTranslateStrings
      } = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! ../bulk-translate */ "./assets/src/string-translate/bulk-translate.js"));
      const response = await bulkTranslateStrings({
        langs: selectedLanguages,
        storeDispatch,
        stringFilters,
        selectedStrings,
        stringLanguageStatus
      });
      setIsLoading(false);
      if (!response.success) {
        setEmptyPostMessage(response.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No strings found to translate.", "automlp-ai-translation-for-wpml"));
        return;
      }
      (0,_bulk_translate__WEBPACK_IMPORTED_MODULE_1__.initBulkTranslateStrings)(response.stringKeys, response.stringsByLanguage, response.nonce, storeDispatch, prefix, updateDestoryHandler, response.totalPerLanguage || {});
    };
    translateContent();
  }, []);
  const handleErrorModal = data => {
    setErrorModalData(data);
    setErrorModal(true);
  };
  const closeErrorModal = e => {
    setErrorModal(false);
    setErrorModalData(false);
  };
  const updateDestoryHandler = callback => {
    setDestroyHandlers(prev => [...prev, callback]);
  };
  const onModalClose = e => {
    destroyHandlers.forEach(callback => typeof callback === "function" && callback());
    onDestory(e);
    const hasStrings = countInfo.stringsTranslated > 0;
    const hasErrors = countInfo.errorPosts > 0 || translatePostInfo && Object.values(translatePostInfo).some(info => info?.status === "error");
    if (bulkStatus === "completed" && hasStrings && !hasErrors) {
      window.location.reload();
    }
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (countInfo.totalPosts < 1 && !isLoading && bulkStatus !== "status") {
      updateBulkStatus("status");
      return;
    }
    if (translatePostInfo && Object.keys(translatePostInfo).length > 0) {
      if (pendingPosts.length < 1) {
        updateBulkStatus("completed");
        return;
      }
      let error = false;
      let running = false;
      const runLoop = (items, index) => {
        const status = translatePostInfo[items[index]].status;
        if (status === "running" || status === "in-progress" || status === "pending") {
          running = true;
          bulkStatus !== "running" && updateBulkStatus("running");
          return;
        }
        if (status === "error") {
          error = true;
        }
        index++;
        if (index < items.length) {
          runLoop(items, index);
        }
      };
      runLoop(Object.keys(translatePostInfo), 0);
      if (running) return;
      if (error) {
        updateBulkStatus("pending");
      } else {
        updateBulkStatus("pending");
      }
    }
  }, [translatePostInfo]);
  const updateBulkStatus = status => {
    setBulkStatus(status);
  };
  const getBulkStatus = () => {
    switch (bulkStatus) {
      case "running":
        return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("In Progress", "automlp-ai-translation-for-wpml");
      case "pending":
        return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Pending", "automlp-ai-translation-for-wpml");
      case "completed":
        return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Completed", "automlp-ai-translation-for-wpml");
      default:
        return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Status", "automlp-ai-translation-for-wpml");
    }
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (progressStatus >= 100 && pendingPosts.length < 1) {
      if (countInfo.postsTranslated < 1 && countInfo.stringsTranslated < 1) {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
        return;
      }
      if (countInfo.stringsTranslated > 0) {
        setTimeout(() => {
          setCharactersCountVisibility(true);
        }, 1000);
      }
      setTimeout(() => {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
      }, 1500);
    }
  }, [pendingPosts]);
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (pendingPosts.length >= 1) return;
    const hasErrorPosts = countInfo.errorPosts > 0;
    const hasErrorInTranslateInfo = translatePostInfo && Object.values(translatePostInfo).some(info => info?.status === "error");
    if (hasErrorPosts || hasErrorInTranslateInfo) {
      setProgressBarVisibility(false);
    }
  }, [pendingPosts, countInfo.errorPosts, translatePostInfo]);
  const AIErrorBtnHandler = e => {
    const type = {
      translateAgain: _components_translate_provider_ai_services__WEBPACK_IMPORTED_MODULE_6__["default"].translateAgain,
      continue: _components_translate_provider_ai_services__WEBPACK_IMPORTED_MODULE_6__["default"].translateComplete
    };
    const btnType = e.target.dataset.status;
    type[btnType]({
      postId: errorModalData.parentPostId,
      targetLang: errorModalData.targetLanguage,
      storeDispatch,
      prefix,
      updateDestoryHandler,
      nonce: errorModalData.nonce,
      closeErrorModal,
      completedStrings: errorModalData.completedStrings,
      totalPosts: errorModalData.totalPosts
    });
  };
  const getServiceProviderLabel = () => {
    switch (serviceProvider) {
      case "google":
        return "Google Translate";
      case "localAiTranslator":
        return "Chrome AI Translator";
      case "openai_ai":
        return "OpenAI";
      case "google_ai":
        return "Gemini";
      case "openrouter_ai":
        return "OpenRouter";
      case "deepl_ai":
        return "DeepL";
      default:
        return "AI Translator";
    }
  };

  // When any string-aggregate row is completed, hide the header skeleton
  const hasCompletedStringAggregate = translatePostInfo && Object.entries(translatePostInfo).some(([key, info]) => key.startsWith("strings_") && info.status === "completed");
  return errorModal ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_components_error_modal_box__WEBPACK_IMPORTED_MODULE_5__["default"], {
    message: errorModalData.errorHtml,
    onClose: closeErrorModal,
    Title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Bulk Translation Error", "automlp-ai-translation-for-wpml"),
    prefix: prefix,
    children: errorModalData.aiError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: `${prefix}-ai-error-buttons`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("button", {
        className: `${prefix}-ai-error-button button`,
        "data-status": "translateAgain",
        onClick: AIErrorBtnHandler,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Translate", "automlp-ai-translation-for-wpml")
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("button", {
        className: `${prefix}-ai-error-button button`,
        "data-status": "continue",
        onClick: AIErrorBtnHandler,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Continue", "automlp-ai-translation-for-wpml")
      })]
    })
  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    id: `${prefix}-status-modal-container`,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: `${prefix}-header`,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: `${prefix}-modal-header-inner`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
          className: `${prefix}-step-label`,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("STEP 3 OF 3", "automlp-ai-translation-for-wpml")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("h2", {
          className: `${prefix}-bulk-status-heading ${bulkStatus}`,
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Bulk Translation %s", "automlp-ai-translation-for-wpml"), getBulkStatus()), bulkStatus === "running" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-bulk-status-running`
          })]
        }), bulkStatus !== "completed" && !(countInfo.totalPosts < 1 && countInfo.errorPosts < 1 && countInfo.stringsTranslated < 1 && !isLoading) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: `${prefix}-modal-desc`,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Your content is being translated. Please wait for a moment.", "automlp-ai-translation-for-wpml")
        }), bulkStatus === "completed" && countInfo.errorPosts < 1 && !(translatePostInfo && Object.values(translatePostInfo).some(info => info?.status === "error")) && countInfo.stringsTranslated > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: `${prefix}-modal-desc`,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Your content has been translated successfully.", "automlp-ai-translation-for-wpml")
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
        className: `${prefix}-modal-close`,
        onClick: e => onModalClose(e),
        children: "\xD7"
      })]
    }), countInfo.totalPosts < 1 && countInfo.errorPosts < 1 && countInfo.stringsTranslated < 1 && !isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      children: emptyPostMessage
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
      children: [isLoading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
        className: `${prefix}-progress-skeleton`
      }), countInfo.totalPosts >= 1 && progressBarVisibility && !isLoading && !(pendingPosts.length < 1 && (countInfo.errorPosts > 0 || translatePostInfo && Object.values(translatePostInfo).some(info => info?.status === "error"))) ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: `${prefix}-overall-progress`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
            className: `${prefix}-progress-bar`,
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
              className: `${prefix}-progress`,
              style: {
                width: progressStatus + "%"
              },
              children: progressStatus + "%"
            })
          }), !charactersCountVisibility && !hasCompletedStringAggregate && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            className: `${prefix}-td-pending`,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
              className: `${prefix}-status-flag`,
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
                className: `${prefix}-status-flag-inner`,
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                  className: `${prefix}-status-flag-inner-left`,
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                    className: `${prefix}-progress-skeleton`,
                    style: {
                      display: "inline-block",
                      width: 20,
                      height: 14
                    }
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                  className: `${prefix}-status-flag-inner-right`,
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                    className: `${prefix}-progress-skeleton`,
                    style: {
                      display: "inline-block",
                      width: 80,
                      height: 14
                    }
                  })
                })]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
              className: `${prefix}-status-content`,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                className: `${prefix}-status-content-inner`,
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                  className: `${prefix}-status`,
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                    className: `${prefix}-progress-skeleton`,
                    style: {
                      display: "inline-block",
                      width: 70,
                      height: 14
                    }
                  })
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                className: `${prefix}-count-stat-value`,
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                  className: `${prefix}-progress-skeleton`,
                  style: {
                    display: "inline-block",
                    width: 100,
                    height: 14,
                    marginTop: 4
                  }
                })
              })]
            })]
          })]
        })
      }) : countInfo.stringsTranslated > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: `${prefix}-count-container`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: `${prefix}-count-stat-cell`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-label`,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("STRINGS", "automlp-ai-translation-for-wpml")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-value`,
            children: countInfo.stringsTranslated
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: `${prefix}-count-stat-cell`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-label`,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("CHARACTERS", "automlp-ai-translation-for-wpml")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-value`,
            children: countInfo.charactersTranslated
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: `${prefix}-count-stat-cell ${prefix}-count-stat-cell--time`,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-label`,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("TIME TAKEN", "automlp-ai-translation-for-wpml")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: `${prefix}-count-stat-value`,
            children: (() => {
              const time = countInfo.timeTaken ?? 0;
              if (time > 0 && time < 1) {
                return `${Math.round(time * 1000)} ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("ms", "automlp-ai-translation-for-wpml")}`;
              }
              return `${Math.round(time)} ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("seconds", "automlp-ai-translation-for-wpml")}`;
            })()
          })]
        })]
      }), !isLoading && pendingPosts.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
        className: `${prefix}-status-table-container`,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
            className: `${prefix}-status-table`,
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
              className: `${prefix}-status-table-inner`,
              children: [isLoading && null, !isLoading && Object.keys(errorPostsInfo).length > 0 && Object.keys(errorPostsInfo).map((key, index) => {
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                    className: `${prefix}-group-title`,
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                      colSpan: "5",
                      children: errorPostsInfo[key]?.title || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Untitled", "automlp-ai-translation-for-wpml")
                    })
                  }, `group-title-${key}`), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                      colSpan: "4",
                      style: {
                        textAlign: "center",
                        width: "100%"
                      },
                      className: `${prefix}-error-message`,
                      dangerouslySetInnerHTML: {
                        __html: dompurify__WEBPACK_IMPORTED_MODULE_8__["default"].sanitize(errorPostsInfo[key].errorMessage)
                      }
                    })
                  }, key)]
                }, key);
              }), !isLoading && Object.keys(translatePostInfo).map(key => {
                const info = translatePostInfo[key];
                const isStringAggregate = key.startsWith("strings_");
                const isStringDashboardOnly = isStringAggregate && key.split("_").length === 3 && key.split("_")[1] === key.split("_")[2];
                if (!isStringAggregate || isStringDashboardOnly) {
                  return null;
                }
                const total = info.total || 0;
                const completed = info.completed || 0;
                const pct = total > 0 ? Math.min(100, Math.round(100 * completed / total)) : 0;
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
                  className: `${prefix}-td-${info.status}`,
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                    className: `${prefix}-status-flag`,
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
                      className: `${prefix}-status-flag-inner`,
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                        className: `${prefix}-status-flag-inner-left`,
                        children: info.flagUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("img", {
                          src: info.flagUrl,
                          width: "20",
                          alt: info.targetLanguage
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                        className: `${prefix}-status-flag-inner-right`,
                        children: info.languageName || info.targetLanguage
                      })]
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
                    className: `${prefix}-status-content`,
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
                      className: `${prefix}-status-content-inner`,
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
                        className: `${prefix}-status ${info.messageClass || ""} ${info.status || ""}`,
                        children: [info.status === "completed" && (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Completed", "automlp-ai-translation-for-wpml"), info.status === "pending" && (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Pending", "automlp-ai-translation-for-wpml"), info.status === "error" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
                          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("button", {
                            type: "button",
                            className: `${prefix}-status-error-button`,
                            onClick: () => handleErrorModal(info),
                            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Error Details", "automlp-ai-translation-for-wpml")
                          })
                        })]
                      })
                    }), info.status === "completed" ? `${total} ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Strings", "automlp-ai-translation-for-wpml")}` : `${completed} / ${total}`]
                  })]
                }, key);
              })]
            })
          })
        })
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (StatusModal);

/***/ },

/***/ "./assets/src/string-translate/index.css"
/*!***********************************************!*\
  !*** ./assets/src/string-translate/index.css ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/react-dom/client.js"
/*!******************************************!*\
  !*** ./node_modules/react-dom/client.js ***!
  \******************************************/
(__unused_webpack_module, exports, __webpack_require__) {



var m = __webpack_require__(/*! react-dom */ "react-dom");
if (false) // removed by dead control flow
{} else {
  var i = m.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  exports.createRoot = function(c, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.createRoot(c, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
  exports.hydrateRoot = function(c, h, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.hydrateRoot(c, h, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
}


/***/ },

/***/ "./node_modules/use-sync-external-store/cjs/use-sync-external-store-with-selector.development.js"
/*!*******************************************************************************************************!*\
  !*** ./node_modules/use-sync-external-store/cjs/use-sync-external-store-with-selector.development.js ***!
  \*******************************************************************************************************/
(__unused_webpack_module, exports, __webpack_require__) {

/**
 * @license React
 * use-sync-external-store-with-selector.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */


 true &&
  (function () {
    function is(x, y) {
      return (x === y && (0 !== x || 1 / x === 1 / y)) || (x !== x && y !== y);
    }
    "undefined" !== typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ &&
      "function" ===
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart &&
      __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart(Error());
    var React = __webpack_require__(/*! react */ "react"),
      objectIs = "function" === typeof Object.is ? Object.is : is,
      useSyncExternalStore = React.useSyncExternalStore,
      useRef = React.useRef,
      useEffect = React.useEffect,
      useMemo = React.useMemo,
      useDebugValue = React.useDebugValue;
    exports.useSyncExternalStoreWithSelector = function (
      subscribe,
      getSnapshot,
      getServerSnapshot,
      selector,
      isEqual
    ) {
      var instRef = useRef(null);
      if (null === instRef.current) {
        var inst = { hasValue: !1, value: null };
        instRef.current = inst;
      } else inst = instRef.current;
      instRef = useMemo(
        function () {
          function memoizedSelector(nextSnapshot) {
            if (!hasMemo) {
              hasMemo = !0;
              memoizedSnapshot = nextSnapshot;
              nextSnapshot = selector(nextSnapshot);
              if (void 0 !== isEqual && inst.hasValue) {
                var currentSelection = inst.value;
                if (isEqual(currentSelection, nextSnapshot))
                  return (memoizedSelection = currentSelection);
              }
              return (memoizedSelection = nextSnapshot);
            }
            currentSelection = memoizedSelection;
            if (objectIs(memoizedSnapshot, nextSnapshot))
              return currentSelection;
            var nextSelection = selector(nextSnapshot);
            if (void 0 !== isEqual && isEqual(currentSelection, nextSelection))
              return (memoizedSnapshot = nextSnapshot), currentSelection;
            memoizedSnapshot = nextSnapshot;
            return (memoizedSelection = nextSelection);
          }
          var hasMemo = !1,
            memoizedSnapshot,
            memoizedSelection,
            maybeGetServerSnapshot =
              void 0 === getServerSnapshot ? null : getServerSnapshot;
          return [
            function () {
              return memoizedSelector(getSnapshot());
            },
            null === maybeGetServerSnapshot
              ? void 0
              : function () {
                  return memoizedSelector(maybeGetServerSnapshot());
                }
          ];
        },
        [getSnapshot, getServerSnapshot, selector, isEqual]
      );
      var value = useSyncExternalStore(subscribe, instRef[0], instRef[1]);
      useEffect(
        function () {
          inst.hasValue = !0;
          inst.value = value;
        },
        [value]
      );
      useDebugValue(value);
      return value;
    };
    "undefined" !== typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ &&
      "function" ===
        typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop &&
      __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop(Error());
  })();


/***/ },

/***/ "./node_modules/use-sync-external-store/with-selector.js"
/*!***************************************************************!*\
  !*** ./node_modules/use-sync-external-store/with-selector.js ***!
  \***************************************************************/
(module, __unused_webpack_exports, __webpack_require__) {



if (false) // removed by dead control flow
{} else {
  module.exports = __webpack_require__(/*! ./cjs/use-sync-external-store-with-selector.development.js */ "./node_modules/use-sync-external-store/cjs/use-sync-external-store-with-selector.development.js");
}


/***/ },

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "react-dom"
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
(module) {

module.exports = window["ReactDOM"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "./node_modules/@reduxjs/toolkit/dist/redux-toolkit.modern.mjs"
/*!*********************************************************************!*\
  !*** ./node_modules/@reduxjs/toolkit/dist/redux-toolkit.modern.mjs ***!
  \*********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReducerType: () => (/* binding */ ReducerType),
/* harmony export */   SHOULD_AUTOBATCH: () => (/* binding */ SHOULD_AUTOBATCH),
/* harmony export */   TaskAbortError: () => (/* binding */ TaskAbortError),
/* harmony export */   Tuple: () => (/* binding */ Tuple),
/* harmony export */   __DO_NOT_USE__ActionTypes: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.__DO_NOT_USE__ActionTypes),
/* harmony export */   addListener: () => (/* binding */ addListener),
/* harmony export */   applyMiddleware: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.applyMiddleware),
/* harmony export */   asyncThunkCreator: () => (/* binding */ asyncThunkCreator),
/* harmony export */   autoBatchEnhancer: () => (/* binding */ autoBatchEnhancer),
/* harmony export */   bindActionCreators: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.bindActionCreators),
/* harmony export */   buildCreateSlice: () => (/* binding */ buildCreateSlice),
/* harmony export */   clearAllListeners: () => (/* binding */ clearAllListeners),
/* harmony export */   combineReducers: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.combineReducers),
/* harmony export */   combineSlices: () => (/* binding */ combineSlices),
/* harmony export */   compose: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.compose),
/* harmony export */   configureStore: () => (/* binding */ configureStore),
/* harmony export */   createAction: () => (/* binding */ createAction),
/* harmony export */   createActionCreatorInvariantMiddleware: () => (/* binding */ createActionCreatorInvariantMiddleware),
/* harmony export */   createAsyncThunk: () => (/* binding */ createAsyncThunk),
/* harmony export */   createDraftSafeSelector: () => (/* binding */ createDraftSafeSelector),
/* harmony export */   createDraftSafeSelectorCreator: () => (/* binding */ createDraftSafeSelectorCreator),
/* harmony export */   createDynamicMiddleware: () => (/* binding */ createDynamicMiddleware),
/* harmony export */   createEntityAdapter: () => (/* binding */ createEntityAdapter),
/* harmony export */   createImmutableStateInvariantMiddleware: () => (/* binding */ createImmutableStateInvariantMiddleware),
/* harmony export */   createListenerMiddleware: () => (/* binding */ createListenerMiddleware),
/* harmony export */   createNextState: () => (/* reexport safe */ immer__WEBPACK_IMPORTED_MODULE_1__.produce),
/* harmony export */   createReducer: () => (/* binding */ createReducer),
/* harmony export */   createSelector: () => (/* reexport safe */ reselect__WEBPACK_IMPORTED_MODULE_2__.createSelector),
/* harmony export */   createSelectorCreator: () => (/* reexport safe */ reselect__WEBPACK_IMPORTED_MODULE_2__.createSelectorCreator),
/* harmony export */   createSerializableStateInvariantMiddleware: () => (/* binding */ createSerializableStateInvariantMiddleware),
/* harmony export */   createSlice: () => (/* binding */ createSlice),
/* harmony export */   createStore: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.createStore),
/* harmony export */   current: () => (/* reexport safe */ immer__WEBPACK_IMPORTED_MODULE_1__.current),
/* harmony export */   findNonSerializableValue: () => (/* binding */ findNonSerializableValue),
/* harmony export */   formatProdErrorMessage: () => (/* binding */ formatProdErrorMessage),
/* harmony export */   freeze: () => (/* reexport safe */ immer__WEBPACK_IMPORTED_MODULE_1__.freeze),
/* harmony export */   isAction: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.isAction),
/* harmony export */   isActionCreator: () => (/* binding */ isActionCreator),
/* harmony export */   isAllOf: () => (/* binding */ isAllOf),
/* harmony export */   isAnyOf: () => (/* binding */ isAnyOf),
/* harmony export */   isAsyncThunkAction: () => (/* binding */ isAsyncThunkAction),
/* harmony export */   isDraft: () => (/* reexport safe */ immer__WEBPACK_IMPORTED_MODULE_1__.isDraft),
/* harmony export */   isFluxStandardAction: () => (/* binding */ isFSA),
/* harmony export */   isFulfilled: () => (/* binding */ isFulfilled),
/* harmony export */   isImmutableDefault: () => (/* binding */ isImmutableDefault),
/* harmony export */   isPending: () => (/* binding */ isPending),
/* harmony export */   isPlain: () => (/* binding */ isPlain),
/* harmony export */   isPlainObject: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.isPlainObject),
/* harmony export */   isRejected: () => (/* binding */ isRejected),
/* harmony export */   isRejectedWithValue: () => (/* binding */ isRejectedWithValue),
/* harmony export */   legacy_createStore: () => (/* reexport safe */ redux__WEBPACK_IMPORTED_MODULE_0__.legacy_createStore),
/* harmony export */   lruMemoize: () => (/* reexport safe */ reselect__WEBPACK_IMPORTED_MODULE_2__.lruMemoize),
/* harmony export */   miniSerializeError: () => (/* binding */ miniSerializeError),
/* harmony export */   nanoid: () => (/* binding */ nanoid),
/* harmony export */   original: () => (/* reexport safe */ immer__WEBPACK_IMPORTED_MODULE_1__.original),
/* harmony export */   prepareAutoBatched: () => (/* binding */ prepareAutoBatched),
/* harmony export */   removeListener: () => (/* binding */ removeListener),
/* harmony export */   unwrapResult: () => (/* binding */ unwrapResult),
/* harmony export */   weakMapMemoize: () => (/* reexport safe */ reselect__WEBPACK_IMPORTED_MODULE_2__.weakMapMemoize)
/* harmony export */ });
/* harmony import */ var redux__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! redux */ "./node_modules/redux/dist/redux.mjs");
/* harmony import */ var immer__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! immer */ "./node_modules/immer/dist/immer.mjs");
/* harmony import */ var reselect__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! reselect */ "./node_modules/reselect/dist/reselect.mjs");
/* harmony import */ var redux_thunk__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! redux-thunk */ "./node_modules/redux-thunk/dist/redux-thunk.mjs");
// src/index.ts



// src/immerImports.ts


// src/index.ts


// src/reselectImports.ts


// src/createDraftSafeSelector.ts
var createDraftSafeSelectorCreator = (...args) => {
  const createSelector2 = (0,reselect__WEBPACK_IMPORTED_MODULE_2__.createSelectorCreator)(...args);
  const createDraftSafeSelector2 = Object.assign((...args2) => {
    const selector = createSelector2(...args2);
    const wrappedSelector = (value, ...rest) => selector((0,immer__WEBPACK_IMPORTED_MODULE_1__.isDraft)(value) ? (0,immer__WEBPACK_IMPORTED_MODULE_1__.current)(value) : value, ...rest);
    Object.assign(wrappedSelector, selector);
    return wrappedSelector;
  }, {
    withTypes: () => createDraftSafeSelector2
  });
  return createDraftSafeSelector2;
};
var createDraftSafeSelector = /* @__PURE__ */ createDraftSafeSelectorCreator(reselect__WEBPACK_IMPORTED_MODULE_2__.weakMapMemoize);

// src/reduxImports.ts


// src/devtoolsExtension.ts
var composeWithDevTools = typeof window !== "undefined" && window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ ? window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ : function() {
  if (arguments.length === 0) return void 0;
  if (typeof arguments[0] === "object") return redux__WEBPACK_IMPORTED_MODULE_0__.compose;
  return redux__WEBPACK_IMPORTED_MODULE_0__.compose.apply(null, arguments);
};
var devToolsEnhancer = typeof window !== "undefined" && window.__REDUX_DEVTOOLS_EXTENSION__ ? window.__REDUX_DEVTOOLS_EXTENSION__ : function() {
  return function(noop3) {
    return noop3;
  };
};

// src/getDefaultMiddleware.ts


// src/tsHelpers.ts
var hasMatchFunction = (v) => {
  return v && typeof v.match === "function";
};

// src/createAction.ts
function createAction(type, prepareAction) {
  function actionCreator(...args) {
    if (prepareAction) {
      let prepared = prepareAction(...args);
      if (!prepared) {
        throw new Error( false ? 0 : "prepareAction did not return an object");
      }
      return {
        type,
        payload: prepared.payload,
        ..."meta" in prepared && {
          meta: prepared.meta
        },
        ..."error" in prepared && {
          error: prepared.error
        }
      };
    }
    return {
      type,
      payload: args[0]
    };
  }
  actionCreator.toString = () => `${type}`;
  actionCreator.type = type;
  actionCreator.match = (action) => (0,redux__WEBPACK_IMPORTED_MODULE_0__.isAction)(action) && action.type === type;
  return actionCreator;
}
function isActionCreator(action) {
  return typeof action === "function" && "type" in action && // hasMatchFunction only wants Matchers but I don't see the point in rewriting it
  hasMatchFunction(action);
}
function isFSA(action) {
  return (0,redux__WEBPACK_IMPORTED_MODULE_0__.isAction)(action) && Object.keys(action).every(isValidKey);
}
function isValidKey(key) {
  return ["type", "payload", "error", "meta"].indexOf(key) > -1;
}

// src/actionCreatorInvariantMiddleware.ts
function getMessage(type) {
  const splitType = type ? `${type}`.split("/") : [];
  const actionName = splitType[splitType.length - 1] || "actionCreator";
  return `Detected an action creator with type "${type || "unknown"}" being dispatched. 
Make sure you're calling the action creator before dispatching, i.e. \`dispatch(${actionName}())\` instead of \`dispatch(${actionName})\`. This is necessary even if the action has no payload.`;
}
function createActionCreatorInvariantMiddleware(options = {}) {
  if (false) // removed by dead control flow
{}
  const {
    isActionCreator: isActionCreator2 = isActionCreator
  } = options;
  return () => (next) => (action) => {
    if (isActionCreator2(action)) {
      console.warn(getMessage(action.type));
    }
    return next(action);
  };
}

// src/utils.ts
function getTimeMeasureUtils(maxDelay, fnName) {
  let elapsed = 0;
  return {
    measureTime(fn) {
      const started = Date.now();
      try {
        return fn();
      } finally {
        const finished = Date.now();
        elapsed += finished - started;
      }
    },
    warnIfExceeded() {
      if (elapsed > maxDelay) {
        console.warn(`${fnName} took ${elapsed}ms, which is more than the warning threshold of ${maxDelay}ms. 
If your state or actions are very large, you may want to disable the middleware as it might cause too much of a slowdown in development mode. See https://redux-toolkit.js.org/api/getDefaultMiddleware for instructions.
It is disabled in production builds, so you don't need to worry about that.`);
      }
    }
  };
}
var Tuple = class _Tuple extends Array {
  constructor(...items) {
    super(...items);
    Object.setPrototypeOf(this, _Tuple.prototype);
  }
  static get [Symbol.species]() {
    return _Tuple;
  }
  concat(...arr) {
    return super.concat.apply(this, arr);
  }
  prepend(...arr) {
    if (arr.length === 1 && Array.isArray(arr[0])) {
      return new _Tuple(...arr[0].concat(this));
    }
    return new _Tuple(...arr.concat(this));
  }
};
function freezeDraftable(val) {
  return (0,immer__WEBPACK_IMPORTED_MODULE_1__.isDraftable)(val) ? (0,immer__WEBPACK_IMPORTED_MODULE_1__.produce)(val, () => {
  }) : val;
}
function getOrInsertComputed(map, key, compute) {
  if (map.has(key)) return map.get(key);
  return map.set(key, compute(key)).get(key);
}

// src/immutableStateInvariantMiddleware.ts
function isImmutableDefault(value) {
  return typeof value !== "object" || value == null || Object.isFrozen(value);
}
function trackForMutations(isImmutable, ignoredPaths, obj) {
  const trackedProperties = trackProperties(isImmutable, ignoredPaths, obj);
  return {
    detectMutations() {
      return detectMutations(isImmutable, ignoredPaths, trackedProperties, obj);
    }
  };
}
function trackProperties(isImmutable, ignoredPaths = [], obj, path = "", checkedObjects = /* @__PURE__ */ new Set()) {
  const tracked = {
    value: obj
  };
  if (!isImmutable(obj) && !checkedObjects.has(obj)) {
    checkedObjects.add(obj);
    tracked.children = {};
    const hasIgnoredPaths = ignoredPaths.length > 0;
    for (const key in obj) {
      const nestedPath = path ? path + "." + key : key;
      if (hasIgnoredPaths) {
        const hasMatches = ignoredPaths.some((ignored) => {
          if (ignored instanceof RegExp) {
            return ignored.test(nestedPath);
          }
          return nestedPath === ignored;
        });
        if (hasMatches) {
          continue;
        }
      }
      tracked.children[key] = trackProperties(isImmutable, ignoredPaths, obj[key], nestedPath);
    }
  }
  return tracked;
}
function detectMutations(isImmutable, ignoredPaths = [], trackedProperty, obj, sameParentRef = false, path = "") {
  const prevObj = trackedProperty ? trackedProperty.value : void 0;
  const sameRef = prevObj === obj;
  if (sameParentRef && !sameRef && !Number.isNaN(obj)) {
    return {
      wasMutated: true,
      path
    };
  }
  if (isImmutable(prevObj) || isImmutable(obj)) {
    return {
      wasMutated: false
    };
  }
  const keysToDetect = {};
  for (let key in trackedProperty.children) {
    keysToDetect[key] = true;
  }
  for (let key in obj) {
    keysToDetect[key] = true;
  }
  const hasIgnoredPaths = ignoredPaths.length > 0;
  for (let key in keysToDetect) {
    const nestedPath = path ? path + "." + key : key;
    if (hasIgnoredPaths) {
      const hasMatches = ignoredPaths.some((ignored) => {
        if (ignored instanceof RegExp) {
          return ignored.test(nestedPath);
        }
        return nestedPath === ignored;
      });
      if (hasMatches) {
        continue;
      }
    }
    const result = detectMutations(isImmutable, ignoredPaths, trackedProperty.children[key], obj[key], sameRef, nestedPath);
    if (result.wasMutated) {
      return result;
    }
  }
  return {
    wasMutated: false
  };
}
function createImmutableStateInvariantMiddleware(options = {}) {
  if (false) // removed by dead control flow
{} else {
    let stringify2 = function(obj, serializer, indent, decycler) {
      return JSON.stringify(obj, getSerialize2(serializer, decycler), indent);
    }, getSerialize2 = function(serializer, decycler) {
      let stack = [], keys = [];
      if (!decycler) decycler = function(_, value) {
        if (stack[0] === value) return "[Circular ~]";
        return "[Circular ~." + keys.slice(0, stack.indexOf(value)).join(".") + "]";
      };
      return function(key, value) {
        if (stack.length > 0) {
          var thisPos = stack.indexOf(this);
          ~thisPos ? stack.splice(thisPos + 1) : stack.push(this);
          ~thisPos ? keys.splice(thisPos, Infinity, key) : keys.push(key);
          if (~stack.indexOf(value)) value = decycler.call(this, key, value);
        } else stack.push(value);
        return serializer == null ? value : serializer.call(this, key, value);
      };
    };
    var stringify = stringify2, getSerialize = getSerialize2;
    let {
      isImmutable = isImmutableDefault,
      ignoredPaths,
      warnAfter = 32
    } = options;
    const track = trackForMutations.bind(null, isImmutable, ignoredPaths);
    return ({
      getState
    }) => {
      let state = getState();
      let tracker = track(state);
      let result;
      return (next) => (action) => {
        const measureUtils = getTimeMeasureUtils(warnAfter, "ImmutableStateInvariantMiddleware");
        measureUtils.measureTime(() => {
          state = getState();
          result = tracker.detectMutations();
          tracker = track(state);
          if (result.wasMutated) {
            throw new Error( false ? 0 : `A state mutation was detected between dispatches, in the path '${result.path || ""}'.  This may cause incorrect behavior. (https://redux.js.org/style-guide/style-guide#do-not-mutate-state)`);
          }
        });
        const dispatchedAction = next(action);
        measureUtils.measureTime(() => {
          state = getState();
          result = tracker.detectMutations();
          tracker = track(state);
          if (result.wasMutated) {
            throw new Error( false ? 0 : `A state mutation was detected inside a dispatch, in the path: ${result.path || ""}. Take a look at the reducer(s) handling the action ${stringify2(action)}. (https://redux.js.org/style-guide/style-guide#do-not-mutate-state)`);
          }
        });
        measureUtils.warnIfExceeded();
        return dispatchedAction;
      };
    };
  }
}

// src/serializableStateInvariantMiddleware.ts
function isPlain(val) {
  const type = typeof val;
  return val == null || type === "string" || type === "boolean" || type === "number" || Array.isArray(val) || (0,redux__WEBPACK_IMPORTED_MODULE_0__.isPlainObject)(val);
}
function findNonSerializableValue(value, path = "", isSerializable = isPlain, getEntries, ignoredPaths = [], cache) {
  let foundNestedSerializable;
  if (!isSerializable(value)) {
    return {
      keyPath: path || "<root>",
      value
    };
  }
  if (typeof value !== "object" || value === null) {
    return false;
  }
  if (cache?.has(value)) return false;
  const entries = getEntries != null ? getEntries(value) : Object.entries(value);
  const hasIgnoredPaths = ignoredPaths.length > 0;
  for (const [key, nestedValue] of entries) {
    const nestedPath = path ? path + "." + key : key;
    if (hasIgnoredPaths) {
      const hasMatches = ignoredPaths.some((ignored) => {
        if (ignored instanceof RegExp) {
          return ignored.test(nestedPath);
        }
        return nestedPath === ignored;
      });
      if (hasMatches) {
        continue;
      }
    }
    if (!isSerializable(nestedValue)) {
      return {
        keyPath: nestedPath,
        value: nestedValue
      };
    }
    if (typeof nestedValue === "object") {
      foundNestedSerializable = findNonSerializableValue(nestedValue, nestedPath, isSerializable, getEntries, ignoredPaths, cache);
      if (foundNestedSerializable) {
        return foundNestedSerializable;
      }
    }
  }
  if (cache && isNestedFrozen(value)) cache.add(value);
  return false;
}
function isNestedFrozen(value) {
  if (!Object.isFrozen(value)) return false;
  for (const nestedValue of Object.values(value)) {
    if (typeof nestedValue !== "object" || nestedValue === null) continue;
    if (!isNestedFrozen(nestedValue)) return false;
  }
  return true;
}
function createSerializableStateInvariantMiddleware(options = {}) {
  if (false) // removed by dead control flow
{} else {
    const {
      isSerializable = isPlain,
      getEntries,
      ignoredActions = [],
      ignoredActionPaths = ["meta.arg", "meta.baseQueryMeta"],
      ignoredPaths = [],
      warnAfter = 32,
      ignoreState = false,
      ignoreActions = false,
      disableCache = false
    } = options;
    const cache = !disableCache && WeakSet ? /* @__PURE__ */ new WeakSet() : void 0;
    return (storeAPI) => (next) => (action) => {
      if (!(0,redux__WEBPACK_IMPORTED_MODULE_0__.isAction)(action)) {
        return next(action);
      }
      const result = next(action);
      const measureUtils = getTimeMeasureUtils(warnAfter, "SerializableStateInvariantMiddleware");
      if (!ignoreActions && !(ignoredActions.length && ignoredActions.indexOf(action.type) !== -1)) {
        measureUtils.measureTime(() => {
          const foundActionNonSerializableValue = findNonSerializableValue(action, "", isSerializable, getEntries, ignoredActionPaths, cache);
          if (foundActionNonSerializableValue) {
            const {
              keyPath,
              value
            } = foundActionNonSerializableValue;
            console.error(`A non-serializable value was detected in an action, in the path: \`${keyPath}\`. Value:`, value, "\nTake a look at the logic that dispatched this action: ", action, "\n(See https://redux.js.org/faq/actions#why-should-type-be-a-string-or-at-least-serializable-why-should-my-action-types-be-constants)", "\n(To allow non-serializable values see: https://redux-toolkit.js.org/usage/usage-guide#working-with-non-serializable-data)");
          }
        });
      }
      if (!ignoreState) {
        measureUtils.measureTime(() => {
          const state = storeAPI.getState();
          const foundStateNonSerializableValue = findNonSerializableValue(state, "", isSerializable, getEntries, ignoredPaths, cache);
          if (foundStateNonSerializableValue) {
            const {
              keyPath,
              value
            } = foundStateNonSerializableValue;
            console.error(`A non-serializable value was detected in the state, in the path: \`${keyPath}\`. Value:`, value, `
Take a look at the reducer(s) handling this action type: ${action.type}.
(See https://redux.js.org/faq/organizing-state#can-i-put-functions-promises-or-other-non-serializable-items-in-my-store-state)`);
          }
        });
        measureUtils.warnIfExceeded();
      }
      return result;
    };
  }
}

// src/getDefaultMiddleware.ts
function isBoolean(x) {
  return typeof x === "boolean";
}
var buildGetDefaultMiddleware = () => function getDefaultMiddleware(options) {
  const {
    thunk = true,
    immutableCheck = true,
    serializableCheck = true,
    actionCreatorCheck = true
  } = options ?? {};
  let middlewareArray = new Tuple();
  if (thunk) {
    if (isBoolean(thunk)) {
      middlewareArray.push(redux_thunk__WEBPACK_IMPORTED_MODULE_3__.thunk);
    } else {
      middlewareArray.push((0,redux_thunk__WEBPACK_IMPORTED_MODULE_3__.withExtraArgument)(thunk.extraArgument));
    }
  }
  if (true) {
    if (immutableCheck) {
      let immutableOptions = {};
      if (!isBoolean(immutableCheck)) {
        immutableOptions = immutableCheck;
      }
      middlewareArray.unshift(createImmutableStateInvariantMiddleware(immutableOptions));
    }
    if (serializableCheck) {
      let serializableOptions = {};
      if (!isBoolean(serializableCheck)) {
        serializableOptions = serializableCheck;
      }
      middlewareArray.push(createSerializableStateInvariantMiddleware(serializableOptions));
    }
    if (actionCreatorCheck) {
      let actionCreatorOptions = {};
      if (!isBoolean(actionCreatorCheck)) {
        actionCreatorOptions = actionCreatorCheck;
      }
      middlewareArray.unshift(createActionCreatorInvariantMiddleware(actionCreatorOptions));
    }
  }
  return middlewareArray;
};

// src/autoBatchEnhancer.ts
var SHOULD_AUTOBATCH = "RTK_autoBatch";
var prepareAutoBatched = () => (payload) => ({
  payload,
  meta: {
    [SHOULD_AUTOBATCH]: true
  }
});
var createQueueWithTimer = (timeout) => {
  return (notify) => {
    setTimeout(notify, timeout);
  };
};
var autoBatchEnhancer = (options = {
  type: "raf"
}) => (next) => (...args) => {
  const store = next(...args);
  let notifying = true;
  let shouldNotifyAtEndOfTick = false;
  let notificationQueued = false;
  const listeners = /* @__PURE__ */ new Set();
  const queueCallback = options.type === "tick" ? queueMicrotask : options.type === "raf" ? (
    // requestAnimationFrame won't exist in SSR environments. Fall back to a vague approximation just to keep from erroring.
    typeof window !== "undefined" && window.requestAnimationFrame ? window.requestAnimationFrame : createQueueWithTimer(10)
  ) : options.type === "callback" ? options.queueNotification : createQueueWithTimer(options.timeout);
  const notifyListeners = () => {
    notificationQueued = false;
    if (shouldNotifyAtEndOfTick) {
      shouldNotifyAtEndOfTick = false;
      listeners.forEach((l) => l());
    }
  };
  return Object.assign({}, store, {
    // Override the base `store.subscribe` method to keep original listeners
    // from running if we're delaying notifications
    subscribe(listener2) {
      const wrappedListener = () => notifying && listener2();
      const unsubscribe = store.subscribe(wrappedListener);
      listeners.add(listener2);
      return () => {
        unsubscribe();
        listeners.delete(listener2);
      };
    },
    // Override the base `store.dispatch` method so that we can check actions
    // for the `shouldAutoBatch` flag and determine if batching is active
    dispatch(action) {
      try {
        notifying = !action?.meta?.[SHOULD_AUTOBATCH];
        shouldNotifyAtEndOfTick = !notifying;
        if (shouldNotifyAtEndOfTick) {
          if (!notificationQueued) {
            notificationQueued = true;
            queueCallback(notifyListeners);
          }
        }
        return store.dispatch(action);
      } finally {
        notifying = true;
      }
    }
  });
};

// src/getDefaultEnhancers.ts
var buildGetDefaultEnhancers = (middlewareEnhancer) => function getDefaultEnhancers(options) {
  const {
    autoBatch = true
  } = options ?? {};
  let enhancerArray = new Tuple(middlewareEnhancer);
  if (autoBatch) {
    enhancerArray.push(autoBatchEnhancer(typeof autoBatch === "object" ? autoBatch : void 0));
  }
  return enhancerArray;
};

// src/configureStore.ts
function configureStore(options) {
  const getDefaultMiddleware = buildGetDefaultMiddleware();
  const {
    reducer = void 0,
    middleware,
    devTools = true,
    duplicateMiddlewareCheck = true,
    preloadedState = void 0,
    enhancers = void 0
  } = options || {};
  let rootReducer;
  if (typeof reducer === "function") {
    rootReducer = reducer;
  } else if ((0,redux__WEBPACK_IMPORTED_MODULE_0__.isPlainObject)(reducer)) {
    rootReducer = (0,redux__WEBPACK_IMPORTED_MODULE_0__.combineReducers)(reducer);
  } else {
    throw new Error( false ? 0 : "`reducer` is a required argument, and must be a function or an object of functions that can be passed to combineReducers");
  }
  if ( true && middleware && typeof middleware !== "function") {
    throw new Error( false ? 0 : "`middleware` field must be a callback");
  }
  let finalMiddleware;
  if (typeof middleware === "function") {
    finalMiddleware = middleware(getDefaultMiddleware);
    if ( true && !Array.isArray(finalMiddleware)) {
      throw new Error( false ? 0 : "when using a middleware builder function, an array of middleware must be returned");
    }
  } else {
    finalMiddleware = getDefaultMiddleware();
  }
  if ( true && finalMiddleware.some((item) => typeof item !== "function")) {
    throw new Error( false ? 0 : "each middleware provided to configureStore must be a function");
  }
  if ( true && duplicateMiddlewareCheck) {
    let middlewareReferences = /* @__PURE__ */ new Set();
    finalMiddleware.forEach((middleware2) => {
      if (middlewareReferences.has(middleware2)) {
        throw new Error( false ? 0 : "Duplicate middleware references found when creating the store. Ensure that each middleware is only included once.");
      }
      middlewareReferences.add(middleware2);
    });
  }
  let finalCompose = redux__WEBPACK_IMPORTED_MODULE_0__.compose;
  if (devTools) {
    finalCompose = composeWithDevTools({
      // Enable capture of stack traces for dispatched Redux actions
      trace: "development" !== "production",
      ...typeof devTools === "object" && devTools
    });
  }
  const middlewareEnhancer = (0,redux__WEBPACK_IMPORTED_MODULE_0__.applyMiddleware)(...finalMiddleware);
  const getDefaultEnhancers = buildGetDefaultEnhancers(middlewareEnhancer);
  if ( true && enhancers && typeof enhancers !== "function") {
    throw new Error( false ? 0 : "`enhancers` field must be a callback");
  }
  let storeEnhancers = typeof enhancers === "function" ? enhancers(getDefaultEnhancers) : getDefaultEnhancers();
  if ( true && !Array.isArray(storeEnhancers)) {
    throw new Error( false ? 0 : "`enhancers` callback must return an array");
  }
  if ( true && storeEnhancers.some((item) => typeof item !== "function")) {
    throw new Error( false ? 0 : "each enhancer provided to configureStore must be a function");
  }
  if ( true && finalMiddleware.length && !storeEnhancers.includes(middlewareEnhancer)) {
    console.error("middlewares were provided, but middleware enhancer was not included in final enhancers - make sure to call `getDefaultEnhancers`");
  }
  const composedEnhancer = finalCompose(...storeEnhancers);
  return (0,redux__WEBPACK_IMPORTED_MODULE_0__.createStore)(rootReducer, preloadedState, composedEnhancer);
}

// src/mapBuilders.ts
function executeReducerBuilderCallback(builderCallback) {
  const actionsMap = {};
  const actionMatchers = [];
  let defaultCaseReducer;
  const builder = {
    addCase(typeOrActionCreator, reducer) {
      if (true) {
        if (actionMatchers.length > 0) {
          throw new Error( false ? 0 : "`builder.addCase` should only be called before calling `builder.addMatcher`");
        }
        if (defaultCaseReducer) {
          throw new Error( false ? 0 : "`builder.addCase` should only be called before calling `builder.addDefaultCase`");
        }
      }
      const type = typeof typeOrActionCreator === "string" ? typeOrActionCreator : typeOrActionCreator.type;
      if (!type) {
        throw new Error( false ? 0 : "`builder.addCase` cannot be called with an empty action type");
      }
      if (type in actionsMap) {
        throw new Error( false ? 0 : `\`builder.addCase\` cannot be called with two reducers for the same action type '${type}'`);
      }
      actionsMap[type] = reducer;
      return builder;
    },
    addAsyncThunk(asyncThunk, reducers) {
      if (true) {
        if (defaultCaseReducer) {
          throw new Error( false ? 0 : "`builder.addAsyncThunk` should only be called before calling `builder.addDefaultCase`");
        }
      }
      if (reducers.pending) actionsMap[asyncThunk.pending.type] = reducers.pending;
      if (reducers.rejected) actionsMap[asyncThunk.rejected.type] = reducers.rejected;
      if (reducers.fulfilled) actionsMap[asyncThunk.fulfilled.type] = reducers.fulfilled;
      if (reducers.settled) actionMatchers.push({
        matcher: asyncThunk.settled,
        reducer: reducers.settled
      });
      return builder;
    },
    addMatcher(matcher, reducer) {
      if (true) {
        if (defaultCaseReducer) {
          throw new Error( false ? 0 : "`builder.addMatcher` should only be called before calling `builder.addDefaultCase`");
        }
      }
      actionMatchers.push({
        matcher,
        reducer
      });
      return builder;
    },
    addDefaultCase(reducer) {
      if (true) {
        if (defaultCaseReducer) {
          throw new Error( false ? 0 : "`builder.addDefaultCase` can only be called once");
        }
      }
      defaultCaseReducer = reducer;
      return builder;
    }
  };
  builderCallback(builder);
  return [actionsMap, actionMatchers, defaultCaseReducer];
}

// src/createReducer.ts
function isStateFunction(x) {
  return typeof x === "function";
}
function createReducer(initialState, mapOrBuilderCallback) {
  if (true) {
    if (typeof mapOrBuilderCallback === "object") {
      throw new Error( false ? 0 : "The object notation for `createReducer` has been removed. Please use the 'builder callback' notation instead: https://redux-toolkit.js.org/api/createReducer");
    }
  }
  let [actionsMap, finalActionMatchers, finalDefaultCaseReducer] = executeReducerBuilderCallback(mapOrBuilderCallback);
  let getInitialState;
  if (isStateFunction(initialState)) {
    getInitialState = () => freezeDraftable(initialState());
  } else {
    const frozenInitialState = freezeDraftable(initialState);
    getInitialState = () => frozenInitialState;
  }
  function reducer(state = getInitialState(), action) {
    let caseReducers = [actionsMap[action.type], ...finalActionMatchers.filter(({
      matcher
    }) => matcher(action)).map(({
      reducer: reducer2
    }) => reducer2)];
    if (caseReducers.filter((cr) => !!cr).length === 0) {
      caseReducers = [finalDefaultCaseReducer];
    }
    return caseReducers.reduce((previousState, caseReducer) => {
      if (caseReducer) {
        if ((0,immer__WEBPACK_IMPORTED_MODULE_1__.isDraft)(previousState)) {
          const draft = previousState;
          const result = caseReducer(draft, action);
          if (result === void 0) {
            return previousState;
          }
          return result;
        } else if (!(0,immer__WEBPACK_IMPORTED_MODULE_1__.isDraftable)(previousState)) {
          const result = caseReducer(previousState, action);
          if (result === void 0) {
            if (previousState === null) {
              return previousState;
            }
            throw Error("A case reducer on a non-draftable value must not return undefined");
          }
          return result;
        } else {
          return (0,immer__WEBPACK_IMPORTED_MODULE_1__.produce)(previousState, (draft) => {
            return caseReducer(draft, action);
          });
        }
      }
      return previousState;
    }, state);
  }
  reducer.getInitialState = getInitialState;
  return reducer;
}

// src/matchers.ts
var matches = (matcher, action) => {
  if (hasMatchFunction(matcher)) {
    return matcher.match(action);
  } else {
    return matcher(action);
  }
};
function isAnyOf(...matchers) {
  return (action) => {
    return matchers.some((matcher) => matches(matcher, action));
  };
}
function isAllOf(...matchers) {
  return (action) => {
    return matchers.every((matcher) => matches(matcher, action));
  };
}
function hasExpectedRequestMetadata(action, validStatus) {
  if (!action || !action.meta) return false;
  const hasValidRequestId = typeof action.meta.requestId === "string";
  const hasValidRequestStatus = validStatus.indexOf(action.meta.requestStatus) > -1;
  return hasValidRequestId && hasValidRequestStatus;
}
function isAsyncThunkArray(a) {
  return typeof a[0] === "function" && "pending" in a[0] && "fulfilled" in a[0] && "rejected" in a[0];
}
function isPending(...asyncThunks) {
  if (asyncThunks.length === 0) {
    return (action) => hasExpectedRequestMetadata(action, ["pending"]);
  }
  if (!isAsyncThunkArray(asyncThunks)) {
    return isPending()(asyncThunks[0]);
  }
  return isAnyOf(...asyncThunks.map((asyncThunk) => asyncThunk.pending));
}
function isRejected(...asyncThunks) {
  if (asyncThunks.length === 0) {
    return (action) => hasExpectedRequestMetadata(action, ["rejected"]);
  }
  if (!isAsyncThunkArray(asyncThunks)) {
    return isRejected()(asyncThunks[0]);
  }
  return isAnyOf(...asyncThunks.map((asyncThunk) => asyncThunk.rejected));
}
function isRejectedWithValue(...asyncThunks) {
  const hasFlag = (action) => {
    return action && action.meta && action.meta.rejectedWithValue;
  };
  if (asyncThunks.length === 0) {
    return isAllOf(isRejected(...asyncThunks), hasFlag);
  }
  if (!isAsyncThunkArray(asyncThunks)) {
    return isRejectedWithValue()(asyncThunks[0]);
  }
  return isAllOf(isRejected(...asyncThunks), hasFlag);
}
function isFulfilled(...asyncThunks) {
  if (asyncThunks.length === 0) {
    return (action) => hasExpectedRequestMetadata(action, ["fulfilled"]);
  }
  if (!isAsyncThunkArray(asyncThunks)) {
    return isFulfilled()(asyncThunks[0]);
  }
  return isAnyOf(...asyncThunks.map((asyncThunk) => asyncThunk.fulfilled));
}
function isAsyncThunkAction(...asyncThunks) {
  if (asyncThunks.length === 0) {
    return (action) => hasExpectedRequestMetadata(action, ["pending", "fulfilled", "rejected"]);
  }
  if (!isAsyncThunkArray(asyncThunks)) {
    return isAsyncThunkAction()(asyncThunks[0]);
  }
  return isAnyOf(...asyncThunks.flatMap((asyncThunk) => [asyncThunk.pending, asyncThunk.rejected, asyncThunk.fulfilled]));
}

// src/nanoid.ts
var urlAlphabet = "ModuleSymbhasOwnPr-0123456789ABCDEFGHNRVfgctiUvz_KqYTJkLxpZXIjQW";
var nanoid = (size = 21) => {
  let id = "";
  let i = size;
  while (i--) {
    id += urlAlphabet[Math.random() * 64 | 0];
  }
  return id;
};

// src/createAsyncThunk.ts
var commonProperties = ["name", "message", "stack", "code"];
var RejectWithValue = class {
  constructor(payload, meta) {
    this.payload = payload;
    this.meta = meta;
  }
  /*
  type-only property to distinguish between RejectWithValue and FulfillWithMeta
  does not exist at runtime
  */
  _type;
};
var FulfillWithMeta = class {
  constructor(payload, meta) {
    this.payload = payload;
    this.meta = meta;
  }
  /*
  type-only property to distinguish between RejectWithValue and FulfillWithMeta
  does not exist at runtime
  */
  _type;
};
var miniSerializeError = (value) => {
  if (typeof value === "object" && value !== null) {
    const simpleError = {};
    for (const property of commonProperties) {
      if (typeof value[property] === "string") {
        simpleError[property] = value[property];
      }
    }
    return simpleError;
  }
  return {
    message: String(value)
  };
};
var externalAbortMessage = "External signal was aborted";
var createAsyncThunk = /* @__PURE__ */ (() => {
  function createAsyncThunk2(typePrefix, payloadCreator, options) {
    const fulfilled = createAction(typePrefix + "/fulfilled", (payload, requestId, arg, meta) => ({
      payload,
      meta: {
        ...meta || {},
        arg,
        requestId,
        requestStatus: "fulfilled"
      }
    }));
    const pending = createAction(typePrefix + "/pending", (requestId, arg, meta) => ({
      payload: void 0,
      meta: {
        ...meta || {},
        arg,
        requestId,
        requestStatus: "pending"
      }
    }));
    const rejected = createAction(typePrefix + "/rejected", (error, requestId, arg, payload, meta) => ({
      payload,
      error: (options && options.serializeError || miniSerializeError)(error || "Rejected"),
      meta: {
        ...meta || {},
        arg,
        requestId,
        rejectedWithValue: !!payload,
        requestStatus: "rejected",
        aborted: error?.name === "AbortError",
        condition: error?.name === "ConditionError"
      }
    }));
    function actionCreator(arg, {
      signal
    } = {}) {
      return (dispatch, getState, extra) => {
        const requestId = options?.idGenerator ? options.idGenerator(arg) : nanoid();
        const abortController = new AbortController();
        let abortHandler;
        let abortReason;
        function abort(reason) {
          abortReason = reason;
          abortController.abort();
        }
        if (signal) {
          if (signal.aborted) {
            abort(externalAbortMessage);
          } else {
            signal.addEventListener("abort", () => abort(externalAbortMessage), {
              once: true
            });
          }
        }
        const promise = async function() {
          let finalAction;
          try {
            let conditionResult = options?.condition?.(arg, {
              getState,
              extra
            });
            if (isThenable(conditionResult)) {
              conditionResult = await conditionResult;
            }
            if (conditionResult === false || abortController.signal.aborted) {
              throw {
                name: "ConditionError",
                message: "Aborted due to condition callback returning false."
              };
            }
            const abortedPromise = new Promise((_, reject) => {
              abortHandler = () => {
                reject({
                  name: "AbortError",
                  message: abortReason || "Aborted"
                });
              };
              abortController.signal.addEventListener("abort", abortHandler, {
                once: true
              });
            });
            dispatch(pending(requestId, arg, options?.getPendingMeta?.({
              requestId,
              arg
            }, {
              getState,
              extra
            })));
            finalAction = await Promise.race([abortedPromise, Promise.resolve(payloadCreator(arg, {
              dispatch,
              getState,
              extra,
              requestId,
              signal: abortController.signal,
              abort,
              rejectWithValue: (value, meta) => {
                return new RejectWithValue(value, meta);
              },
              fulfillWithValue: (value, meta) => {
                return new FulfillWithMeta(value, meta);
              }
            })).then((result) => {
              if (result instanceof RejectWithValue) {
                throw result;
              }
              if (result instanceof FulfillWithMeta) {
                return fulfilled(result.payload, requestId, arg, result.meta);
              }
              return fulfilled(result, requestId, arg);
            })]);
          } catch (err) {
            finalAction = err instanceof RejectWithValue ? rejected(null, requestId, arg, err.payload, err.meta) : rejected(err, requestId, arg);
          } finally {
            if (abortHandler) {
              abortController.signal.removeEventListener("abort", abortHandler);
            }
          }
          const skipDispatch = options && !options.dispatchConditionRejection && rejected.match(finalAction) && finalAction.meta.condition;
          if (!skipDispatch) {
            dispatch(finalAction);
          }
          return finalAction;
        }();
        return Object.assign(promise, {
          abort,
          requestId,
          arg,
          unwrap() {
            return promise.then(unwrapResult);
          }
        });
      };
    }
    return Object.assign(actionCreator, {
      pending,
      rejected,
      fulfilled,
      settled: isAnyOf(rejected, fulfilled),
      typePrefix
    });
  }
  createAsyncThunk2.withTypes = () => createAsyncThunk2;
  return createAsyncThunk2;
})();
function unwrapResult(action) {
  if (action.meta && action.meta.rejectedWithValue) {
    throw action.payload;
  }
  if (action.error) {
    throw action.error;
  }
  return action.payload;
}
function isThenable(value) {
  return value !== null && typeof value === "object" && typeof value.then === "function";
}

// src/createSlice.ts
var asyncThunkSymbol = /* @__PURE__ */ Symbol.for("rtk-slice-createasyncthunk");
var asyncThunkCreator = {
  [asyncThunkSymbol]: createAsyncThunk
};
var ReducerType = /* @__PURE__ */ ((ReducerType2) => {
  ReducerType2["reducer"] = "reducer";
  ReducerType2["reducerWithPrepare"] = "reducerWithPrepare";
  ReducerType2["asyncThunk"] = "asyncThunk";
  return ReducerType2;
})(ReducerType || {});
function getType(slice, actionKey) {
  return `${slice}/${actionKey}`;
}
function buildCreateSlice({
  creators
} = {}) {
  const cAT = creators?.asyncThunk?.[asyncThunkSymbol];
  return function createSlice2(options) {
    const {
      name,
      reducerPath = name
    } = options;
    if (!name) {
      throw new Error( false ? 0 : "`name` is a required option for createSlice");
    }
    if (typeof process !== "undefined" && "development" === "development") {
      if (options.initialState === void 0) {
        console.error("You must provide an `initialState` value that is not `undefined`. You may have misspelled `initialState`");
      }
    }
    const reducers = (typeof options.reducers === "function" ? options.reducers(buildReducerCreators()) : options.reducers) || {};
    const reducerNames = Object.keys(reducers);
    const context = {
      sliceCaseReducersByName: {},
      sliceCaseReducersByType: {},
      actionCreators: {},
      sliceMatchers: []
    };
    const contextMethods = {
      addCase(typeOrActionCreator, reducer2) {
        const type = typeof typeOrActionCreator === "string" ? typeOrActionCreator : typeOrActionCreator.type;
        if (!type) {
          throw new Error( false ? 0 : "`context.addCase` cannot be called with an empty action type");
        }
        if (type in context.sliceCaseReducersByType) {
          throw new Error( false ? 0 : "`context.addCase` cannot be called with two reducers for the same action type: " + type);
        }
        context.sliceCaseReducersByType[type] = reducer2;
        return contextMethods;
      },
      addMatcher(matcher, reducer2) {
        context.sliceMatchers.push({
          matcher,
          reducer: reducer2
        });
        return contextMethods;
      },
      exposeAction(name2, actionCreator) {
        context.actionCreators[name2] = actionCreator;
        return contextMethods;
      },
      exposeCaseReducer(name2, reducer2) {
        context.sliceCaseReducersByName[name2] = reducer2;
        return contextMethods;
      }
    };
    reducerNames.forEach((reducerName) => {
      const reducerDefinition = reducers[reducerName];
      const reducerDetails = {
        reducerName,
        type: getType(name, reducerName),
        createNotation: typeof options.reducers === "function"
      };
      if (isAsyncThunkSliceReducerDefinition(reducerDefinition)) {
        handleThunkCaseReducerDefinition(reducerDetails, reducerDefinition, contextMethods, cAT);
      } else {
        handleNormalReducerDefinition(reducerDetails, reducerDefinition, contextMethods);
      }
    });
    function buildReducer() {
      if (true) {
        if (typeof options.extraReducers === "object") {
          throw new Error( false ? 0 : "The object notation for `createSlice.extraReducers` has been removed. Please use the 'builder callback' notation instead: https://redux-toolkit.js.org/api/createSlice");
        }
      }
      const [extraReducers = {}, actionMatchers = [], defaultCaseReducer = void 0] = typeof options.extraReducers === "function" ? executeReducerBuilderCallback(options.extraReducers) : [options.extraReducers];
      const finalCaseReducers = {
        ...extraReducers,
        ...context.sliceCaseReducersByType
      };
      return createReducer(options.initialState, (builder) => {
        for (let key in finalCaseReducers) {
          builder.addCase(key, finalCaseReducers[key]);
        }
        for (let sM of context.sliceMatchers) {
          builder.addMatcher(sM.matcher, sM.reducer);
        }
        for (let m of actionMatchers) {
          builder.addMatcher(m.matcher, m.reducer);
        }
        if (defaultCaseReducer) {
          builder.addDefaultCase(defaultCaseReducer);
        }
      });
    }
    const selectSelf = (state) => state;
    const injectedSelectorCache = /* @__PURE__ */ new Map();
    const injectedStateCache = /* @__PURE__ */ new WeakMap();
    let _reducer;
    function reducer(state, action) {
      if (!_reducer) _reducer = buildReducer();
      return _reducer(state, action);
    }
    function getInitialState() {
      if (!_reducer) _reducer = buildReducer();
      return _reducer.getInitialState();
    }
    function makeSelectorProps(reducerPath2, injected = false) {
      function selectSlice(state) {
        let sliceState = state[reducerPath2];
        if (typeof sliceState === "undefined") {
          if (injected) {
            sliceState = getOrInsertComputed(injectedStateCache, selectSlice, getInitialState);
          } else if (true) {
            throw new Error( false ? 0 : "selectSlice returned undefined for an uninjected slice reducer");
          }
        }
        return sliceState;
      }
      function getSelectors(selectState = selectSelf) {
        const selectorCache = getOrInsertComputed(injectedSelectorCache, injected, () => /* @__PURE__ */ new WeakMap());
        return getOrInsertComputed(selectorCache, selectState, () => {
          const map = {};
          for (const [name2, selector] of Object.entries(options.selectors ?? {})) {
            map[name2] = wrapSelector(selector, selectState, () => getOrInsertComputed(injectedStateCache, selectState, getInitialState), injected);
          }
          return map;
        });
      }
      return {
        reducerPath: reducerPath2,
        getSelectors,
        get selectors() {
          return getSelectors(selectSlice);
        },
        selectSlice
      };
    }
    const slice = {
      name,
      reducer,
      actions: context.actionCreators,
      caseReducers: context.sliceCaseReducersByName,
      getInitialState,
      ...makeSelectorProps(reducerPath),
      injectInto(injectable, {
        reducerPath: pathOpt,
        ...config
      } = {}) {
        const newReducerPath = pathOpt ?? reducerPath;
        injectable.inject({
          reducerPath: newReducerPath,
          reducer
        }, config);
        return {
          ...slice,
          ...makeSelectorProps(newReducerPath, true)
        };
      }
    };
    return slice;
  };
}
function wrapSelector(selector, selectState, getInitialState, injected) {
  function wrapper(rootState, ...args) {
    let sliceState = selectState(rootState);
    if (typeof sliceState === "undefined") {
      if (injected) {
        sliceState = getInitialState();
      } else if (true) {
        throw new Error( false ? 0 : "selectState returned undefined for an uninjected slice reducer");
      }
    }
    return selector(sliceState, ...args);
  }
  wrapper.unwrapped = selector;
  return wrapper;
}
var createSlice = /* @__PURE__ */ buildCreateSlice();
function buildReducerCreators() {
  function asyncThunk(payloadCreator, config) {
    return {
      _reducerDefinitionType: "asyncThunk" /* asyncThunk */,
      payloadCreator,
      ...config
    };
  }
  asyncThunk.withTypes = () => asyncThunk;
  return {
    reducer(caseReducer) {
      return Object.assign({
        // hack so the wrapping function has the same name as the original
        // we need to create a wrapper so the `reducerDefinitionType` is not assigned to the original
        [caseReducer.name](...args) {
          return caseReducer(...args);
        }
      }[caseReducer.name], {
        _reducerDefinitionType: "reducer" /* reducer */
      });
    },
    preparedReducer(prepare, reducer) {
      return {
        _reducerDefinitionType: "reducerWithPrepare" /* reducerWithPrepare */,
        prepare,
        reducer
      };
    },
    asyncThunk
  };
}
function handleNormalReducerDefinition({
  type,
  reducerName,
  createNotation
}, maybeReducerWithPrepare, context) {
  let caseReducer;
  let prepareCallback;
  if ("reducer" in maybeReducerWithPrepare) {
    if (createNotation && !isCaseReducerWithPrepareDefinition(maybeReducerWithPrepare)) {
      throw new Error( false ? 0 : "Please use the `create.preparedReducer` notation for prepared action creators with the `create` notation.");
    }
    caseReducer = maybeReducerWithPrepare.reducer;
    prepareCallback = maybeReducerWithPrepare.prepare;
  } else {
    caseReducer = maybeReducerWithPrepare;
  }
  context.addCase(type, caseReducer).exposeCaseReducer(reducerName, caseReducer).exposeAction(reducerName, prepareCallback ? createAction(type, prepareCallback) : createAction(type));
}
function isAsyncThunkSliceReducerDefinition(reducerDefinition) {
  return reducerDefinition._reducerDefinitionType === "asyncThunk" /* asyncThunk */;
}
function isCaseReducerWithPrepareDefinition(reducerDefinition) {
  return reducerDefinition._reducerDefinitionType === "reducerWithPrepare" /* reducerWithPrepare */;
}
function handleThunkCaseReducerDefinition({
  type,
  reducerName
}, reducerDefinition, context, cAT) {
  if (!cAT) {
    throw new Error( false ? 0 : "Cannot use `create.asyncThunk` in the built-in `createSlice`. Use `buildCreateSlice({ creators: { asyncThunk: asyncThunkCreator } })` to create a customised version of `createSlice`.");
  }
  const {
    payloadCreator,
    fulfilled,
    pending,
    rejected,
    settled,
    options
  } = reducerDefinition;
  const thunk = cAT(type, payloadCreator, options);
  context.exposeAction(reducerName, thunk);
  if (fulfilled) {
    context.addCase(thunk.fulfilled, fulfilled);
  }
  if (pending) {
    context.addCase(thunk.pending, pending);
  }
  if (rejected) {
    context.addCase(thunk.rejected, rejected);
  }
  if (settled) {
    context.addMatcher(thunk.settled, settled);
  }
  context.exposeCaseReducer(reducerName, {
    fulfilled: fulfilled || noop,
    pending: pending || noop,
    rejected: rejected || noop,
    settled: settled || noop
  });
}
function noop() {
}

// src/entities/entity_state.ts
function getInitialEntityState() {
  return {
    ids: [],
    entities: {}
  };
}
function createInitialStateFactory(stateAdapter) {
  function getInitialState(additionalState = {}, entities) {
    const state = Object.assign(getInitialEntityState(), additionalState);
    return entities ? stateAdapter.setAll(state, entities) : state;
  }
  return {
    getInitialState
  };
}

// src/entities/state_selectors.ts
function createSelectorsFactory() {
  function getSelectors(selectState, options = {}) {
    const {
      createSelector: createSelector2 = createDraftSafeSelector
    } = options;
    const selectIds = (state) => state.ids;
    const selectEntities = (state) => state.entities;
    const selectAll = createSelector2(selectIds, selectEntities, (ids, entities) => ids.map((id) => entities[id]));
    const selectId = (_, id) => id;
    const selectById = (entities, id) => entities[id];
    const selectTotal = createSelector2(selectIds, (ids) => ids.length);
    if (!selectState) {
      return {
        selectIds,
        selectEntities,
        selectAll,
        selectTotal,
        selectById: createSelector2(selectEntities, selectId, selectById)
      };
    }
    const selectGlobalizedEntities = createSelector2(selectState, selectEntities);
    return {
      selectIds: createSelector2(selectState, selectIds),
      selectEntities: selectGlobalizedEntities,
      selectAll: createSelector2(selectState, selectAll),
      selectTotal: createSelector2(selectState, selectTotal),
      selectById: createSelector2(selectGlobalizedEntities, selectId, selectById)
    };
  }
  return {
    getSelectors
  };
}

// src/entities/state_adapter.ts
var isDraftTyped = immer__WEBPACK_IMPORTED_MODULE_1__.isDraft;
function createSingleArgumentStateOperator(mutator) {
  const operator = createStateOperator((_, state) => mutator(state));
  return function operation(state) {
    return operator(state, void 0);
  };
}
function createStateOperator(mutator) {
  return function operation(state, arg) {
    function isPayloadActionArgument(arg2) {
      return isFSA(arg2);
    }
    const runMutator = (draft) => {
      if (isPayloadActionArgument(arg)) {
        mutator(arg.payload, draft);
      } else {
        mutator(arg, draft);
      }
    };
    if (isDraftTyped(state)) {
      runMutator(state);
      return state;
    }
    return (0,immer__WEBPACK_IMPORTED_MODULE_1__.produce)(state, runMutator);
  };
}

// src/entities/utils.ts
function selectIdValue(entity, selectId) {
  const key = selectId(entity);
  if ( true && key === void 0) {
    console.warn("The entity passed to the `selectId` implementation returned undefined.", "You should probably provide your own `selectId` implementation.", "The entity that was passed:", entity, "The `selectId` implementation:", selectId.toString());
  }
  return key;
}
function ensureEntitiesArray(entities) {
  if (!Array.isArray(entities)) {
    entities = Object.values(entities);
  }
  return entities;
}
function getCurrent(value) {
  return (0,immer__WEBPACK_IMPORTED_MODULE_1__.isDraft)(value) ? (0,immer__WEBPACK_IMPORTED_MODULE_1__.current)(value) : value;
}
function splitAddedUpdatedEntities(newEntities, selectId, state) {
  newEntities = ensureEntitiesArray(newEntities);
  const existingIdsArray = getCurrent(state.ids);
  const existingIds = new Set(existingIdsArray);
  const added = [];
  const addedIds = /* @__PURE__ */ new Set([]);
  const updated = [];
  for (const entity of newEntities) {
    const id = selectIdValue(entity, selectId);
    if (existingIds.has(id) || addedIds.has(id)) {
      updated.push({
        id,
        changes: entity
      });
    } else {
      addedIds.add(id);
      added.push(entity);
    }
  }
  return [added, updated, existingIdsArray];
}

// src/entities/unsorted_state_adapter.ts
function createUnsortedStateAdapter(selectId) {
  function addOneMutably(entity, state) {
    const key = selectIdValue(entity, selectId);
    if (key in state.entities) {
      return;
    }
    state.ids.push(key);
    state.entities[key] = entity;
  }
  function addManyMutably(newEntities, state) {
    newEntities = ensureEntitiesArray(newEntities);
    for (const entity of newEntities) {
      addOneMutably(entity, state);
    }
  }
  function setOneMutably(entity, state) {
    const key = selectIdValue(entity, selectId);
    if (!(key in state.entities)) {
      state.ids.push(key);
    }
    ;
    state.entities[key] = entity;
  }
  function setManyMutably(newEntities, state) {
    newEntities = ensureEntitiesArray(newEntities);
    for (const entity of newEntities) {
      setOneMutably(entity, state);
    }
  }
  function setAllMutably(newEntities, state) {
    newEntities = ensureEntitiesArray(newEntities);
    state.ids = [];
    state.entities = {};
    addManyMutably(newEntities, state);
  }
  function removeOneMutably(key, state) {
    return removeManyMutably([key], state);
  }
  function removeManyMutably(keys, state) {
    let didMutate = false;
    keys.forEach((key) => {
      if (key in state.entities) {
        delete state.entities[key];
        didMutate = true;
      }
    });
    if (didMutate) {
      state.ids = state.ids.filter((id) => id in state.entities);
    }
  }
  function removeAllMutably(state) {
    Object.assign(state, {
      ids: [],
      entities: {}
    });
  }
  function takeNewKey(keys, update, state) {
    const original3 = state.entities[update.id];
    if (original3 === void 0) {
      return false;
    }
    const updated = Object.assign({}, original3, update.changes);
    const newKey = selectIdValue(updated, selectId);
    const hasNewKey = newKey !== update.id;
    if (hasNewKey) {
      keys[update.id] = newKey;
      delete state.entities[update.id];
    }
    ;
    state.entities[newKey] = updated;
    return hasNewKey;
  }
  function updateOneMutably(update, state) {
    return updateManyMutably([update], state);
  }
  function updateManyMutably(updates, state) {
    const newKeys = {};
    const updatesPerEntity = {};
    updates.forEach((update) => {
      if (update.id in state.entities) {
        updatesPerEntity[update.id] = {
          id: update.id,
          // Spreads ignore falsy values, so this works even if there isn't
          // an existing update already at this key
          changes: {
            ...updatesPerEntity[update.id]?.changes,
            ...update.changes
          }
        };
      }
    });
    updates = Object.values(updatesPerEntity);
    const didMutateEntities = updates.length > 0;
    if (didMutateEntities) {
      const didMutateIds = updates.filter((update) => takeNewKey(newKeys, update, state)).length > 0;
      if (didMutateIds) {
        state.ids = Object.values(state.entities).map((e) => selectIdValue(e, selectId));
      }
    }
  }
  function upsertOneMutably(entity, state) {
    return upsertManyMutably([entity], state);
  }
  function upsertManyMutably(newEntities, state) {
    const [added, updated] = splitAddedUpdatedEntities(newEntities, selectId, state);
    addManyMutably(added, state);
    updateManyMutably(updated, state);
  }
  return {
    removeAll: createSingleArgumentStateOperator(removeAllMutably),
    addOne: createStateOperator(addOneMutably),
    addMany: createStateOperator(addManyMutably),
    setOne: createStateOperator(setOneMutably),
    setMany: createStateOperator(setManyMutably),
    setAll: createStateOperator(setAllMutably),
    updateOne: createStateOperator(updateOneMutably),
    updateMany: createStateOperator(updateManyMutably),
    upsertOne: createStateOperator(upsertOneMutably),
    upsertMany: createStateOperator(upsertManyMutably),
    removeOne: createStateOperator(removeOneMutably),
    removeMany: createStateOperator(removeManyMutably)
  };
}

// src/entities/sorted_state_adapter.ts
function findInsertIndex(sortedItems, item, comparisonFunction) {
  let lowIndex = 0;
  let highIndex = sortedItems.length;
  while (lowIndex < highIndex) {
    let middleIndex = lowIndex + highIndex >>> 1;
    const currentItem = sortedItems[middleIndex];
    const res = comparisonFunction(item, currentItem);
    if (res >= 0) {
      lowIndex = middleIndex + 1;
    } else {
      highIndex = middleIndex;
    }
  }
  return lowIndex;
}
function insert(sortedItems, item, comparisonFunction) {
  const insertAtIndex = findInsertIndex(sortedItems, item, comparisonFunction);
  sortedItems.splice(insertAtIndex, 0, item);
  return sortedItems;
}
function createSortedStateAdapter(selectId, comparer) {
  const {
    removeOne,
    removeMany,
    removeAll
  } = createUnsortedStateAdapter(selectId);
  function addOneMutably(entity, state) {
    return addManyMutably([entity], state);
  }
  function addManyMutably(newEntities, state, existingIds) {
    newEntities = ensureEntitiesArray(newEntities);
    const existingKeys = new Set(existingIds ?? getCurrent(state.ids));
    const addedKeys = /* @__PURE__ */ new Set();
    const models = newEntities.filter((model) => {
      const modelId = selectIdValue(model, selectId);
      const notAdded = !addedKeys.has(modelId);
      if (notAdded) addedKeys.add(modelId);
      return !existingKeys.has(modelId) && notAdded;
    });
    if (models.length !== 0) {
      mergeFunction(state, models);
    }
  }
  function setOneMutably(entity, state) {
    return setManyMutably([entity], state);
  }
  function setManyMutably(newEntities, state) {
    let deduplicatedEntities = {};
    newEntities = ensureEntitiesArray(newEntities);
    if (newEntities.length !== 0) {
      for (const item of newEntities) {
        const entityId = selectId(item);
        deduplicatedEntities[entityId] = item;
        delete state.entities[entityId];
      }
      newEntities = ensureEntitiesArray(deduplicatedEntities);
      mergeFunction(state, newEntities);
    }
  }
  function setAllMutably(newEntities, state) {
    newEntities = ensureEntitiesArray(newEntities);
    state.entities = {};
    state.ids = [];
    addManyMutably(newEntities, state, []);
  }
  function updateOneMutably(update, state) {
    return updateManyMutably([update], state);
  }
  function updateManyMutably(updates, state) {
    let appliedUpdates = false;
    let replacedIds = false;
    for (let update of updates) {
      const entity = state.entities[update.id];
      if (!entity) {
        continue;
      }
      appliedUpdates = true;
      Object.assign(entity, update.changes);
      const newId = selectId(entity);
      if (update.id !== newId) {
        replacedIds = true;
        delete state.entities[update.id];
        const oldIndex = state.ids.indexOf(update.id);
        state.ids[oldIndex] = newId;
        state.entities[newId] = entity;
      }
    }
    if (appliedUpdates) {
      mergeFunction(state, [], appliedUpdates, replacedIds);
    }
  }
  function upsertOneMutably(entity, state) {
    return upsertManyMutably([entity], state);
  }
  function upsertManyMutably(newEntities, state) {
    const [added, updated, existingIdsArray] = splitAddedUpdatedEntities(newEntities, selectId, state);
    if (added.length) {
      addManyMutably(added, state, existingIdsArray);
    }
    if (updated.length) {
      updateManyMutably(updated, state);
    }
  }
  function areArraysEqual(a, b) {
    if (a.length !== b.length) {
      return false;
    }
    for (let i = 0; i < a.length; i++) {
      if (a[i] === b[i]) {
        continue;
      }
      return false;
    }
    return true;
  }
  const mergeFunction = (state, addedItems, appliedUpdates, replacedIds) => {
    const currentEntities = getCurrent(state.entities);
    const currentIds = getCurrent(state.ids);
    const stateEntities = state.entities;
    let ids = currentIds;
    if (replacedIds) {
      ids = new Set(currentIds);
    }
    let sortedEntities = [];
    for (const id of ids) {
      const entity = currentEntities[id];
      if (entity) {
        sortedEntities.push(entity);
      }
    }
    const wasPreviouslyEmpty = sortedEntities.length === 0;
    for (const item of addedItems) {
      stateEntities[selectId(item)] = item;
      if (!wasPreviouslyEmpty) {
        insert(sortedEntities, item, comparer);
      }
    }
    if (wasPreviouslyEmpty) {
      sortedEntities = addedItems.slice().sort(comparer);
    } else if (appliedUpdates) {
      sortedEntities.sort(comparer);
    }
    const newSortedIds = sortedEntities.map(selectId);
    if (!areArraysEqual(currentIds, newSortedIds)) {
      state.ids = newSortedIds;
    }
  };
  return {
    removeOne,
    removeMany,
    removeAll,
    addOne: createStateOperator(addOneMutably),
    updateOne: createStateOperator(updateOneMutably),
    upsertOne: createStateOperator(upsertOneMutably),
    setOne: createStateOperator(setOneMutably),
    setMany: createStateOperator(setManyMutably),
    setAll: createStateOperator(setAllMutably),
    addMany: createStateOperator(addManyMutably),
    updateMany: createStateOperator(updateManyMutably),
    upsertMany: createStateOperator(upsertManyMutably)
  };
}

// src/entities/create_adapter.ts
function createEntityAdapter(options = {}) {
  const {
    selectId,
    sortComparer
  } = {
    sortComparer: false,
    selectId: (instance) => instance.id,
    ...options
  };
  const stateAdapter = sortComparer ? createSortedStateAdapter(selectId, sortComparer) : createUnsortedStateAdapter(selectId);
  const stateFactory = createInitialStateFactory(stateAdapter);
  const selectorsFactory = createSelectorsFactory();
  return {
    selectId,
    sortComparer,
    ...stateFactory,
    ...selectorsFactory,
    ...stateAdapter
  };
}

// src/listenerMiddleware/exceptions.ts
var task = "task";
var listener = "listener";
var completed = "completed";
var cancelled = "cancelled";
var taskCancelled = `task-${cancelled}`;
var taskCompleted = `task-${completed}`;
var listenerCancelled = `${listener}-${cancelled}`;
var listenerCompleted = `${listener}-${completed}`;
var TaskAbortError = class {
  constructor(code) {
    this.code = code;
    this.message = `${task} ${cancelled} (reason: ${code})`;
  }
  name = "TaskAbortError";
  message;
};

// src/listenerMiddleware/utils.ts
var assertFunction = (func, expected) => {
  if (typeof func !== "function") {
    throw new TypeError( false ? 0 : `${expected} is not a function`);
  }
};
var noop2 = () => {
};
var catchRejection = (promise, onError = noop2) => {
  promise.catch(onError);
  return promise;
};
var addAbortSignalListener = (abortSignal, callback) => {
  abortSignal.addEventListener("abort", callback, {
    once: true
  });
  return () => abortSignal.removeEventListener("abort", callback);
};

// src/listenerMiddleware/task.ts
var validateActive = (signal) => {
  if (signal.aborted) {
    throw new TaskAbortError(signal.reason);
  }
};
function raceWithSignal(signal, promise) {
  let cleanup = noop2;
  return new Promise((resolve, reject) => {
    const notifyRejection = () => reject(new TaskAbortError(signal.reason));
    if (signal.aborted) {
      notifyRejection();
      return;
    }
    cleanup = addAbortSignalListener(signal, notifyRejection);
    promise.finally(() => cleanup()).then(resolve, reject);
  }).finally(() => {
    cleanup = noop2;
  });
}
var runTask = async (task2, cleanUp) => {
  try {
    await Promise.resolve();
    const value = await task2();
    return {
      status: "ok",
      value
    };
  } catch (error) {
    return {
      status: error instanceof TaskAbortError ? "cancelled" : "rejected",
      error
    };
  } finally {
    cleanUp?.();
  }
};
var createPause = (signal) => {
  return (promise) => {
    return catchRejection(raceWithSignal(signal, promise).then((output) => {
      validateActive(signal);
      return output;
    }));
  };
};
var createDelay = (signal) => {
  const pause = createPause(signal);
  return (timeoutMs) => {
    return pause(new Promise((resolve) => setTimeout(resolve, timeoutMs)));
  };
};

// src/listenerMiddleware/index.ts
var {
  assign
} = Object;
var INTERNAL_NIL_TOKEN = {};
var alm = "listenerMiddleware";
var createFork = (parentAbortSignal, parentBlockingPromises) => {
  const linkControllers = (controller) => addAbortSignalListener(parentAbortSignal, () => controller.abort(parentAbortSignal.reason));
  return (taskExecutor, opts) => {
    assertFunction(taskExecutor, "taskExecutor");
    const childAbortController = new AbortController();
    linkControllers(childAbortController);
    const result = runTask(async () => {
      validateActive(parentAbortSignal);
      validateActive(childAbortController.signal);
      const result2 = await taskExecutor({
        pause: createPause(childAbortController.signal),
        delay: createDelay(childAbortController.signal),
        signal: childAbortController.signal
      });
      validateActive(childAbortController.signal);
      return result2;
    }, () => childAbortController.abort(taskCompleted));
    if (opts?.autoJoin) {
      parentBlockingPromises.push(result.catch(noop2));
    }
    return {
      result: createPause(parentAbortSignal)(result),
      cancel() {
        childAbortController.abort(taskCancelled);
      }
    };
  };
};
var createTakePattern = (startListening, signal) => {
  const take = async (predicate, timeout) => {
    validateActive(signal);
    let unsubscribe = () => {
    };
    const tuplePromise = new Promise((resolve, reject) => {
      let stopListening = startListening({
        predicate,
        effect: (action, listenerApi) => {
          listenerApi.unsubscribe();
          resolve([action, listenerApi.getState(), listenerApi.getOriginalState()]);
        }
      });
      unsubscribe = () => {
        stopListening();
        reject();
      };
    });
    const promises = [tuplePromise];
    if (timeout != null) {
      promises.push(new Promise((resolve) => setTimeout(resolve, timeout, null)));
    }
    try {
      const output = await raceWithSignal(signal, Promise.race(promises));
      validateActive(signal);
      return output;
    } finally {
      unsubscribe();
    }
  };
  return (predicate, timeout) => catchRejection(take(predicate, timeout));
};
var getListenerEntryPropsFrom = (options) => {
  let {
    type,
    actionCreator,
    matcher,
    predicate,
    effect
  } = options;
  if (type) {
    predicate = createAction(type).match;
  } else if (actionCreator) {
    type = actionCreator.type;
    predicate = actionCreator.match;
  } else if (matcher) {
    predicate = matcher;
  } else if (predicate) {
  } else {
    throw new Error( false ? 0 : "Creating or removing a listener requires one of the known fields for matching an action");
  }
  assertFunction(effect, "options.listener");
  return {
    predicate,
    type,
    effect
  };
};
var createListenerEntry = /* @__PURE__ */ assign((options) => {
  const {
    type,
    predicate,
    effect
  } = getListenerEntryPropsFrom(options);
  const entry = {
    id: nanoid(),
    effect,
    type,
    predicate,
    pending: /* @__PURE__ */ new Set(),
    unsubscribe: () => {
      throw new Error( false ? 0 : "Unsubscribe not initialized");
    }
  };
  return entry;
}, {
  withTypes: () => createListenerEntry
});
var findListenerEntry = (listenerMap, options) => {
  const {
    type,
    effect,
    predicate
  } = getListenerEntryPropsFrom(options);
  return Array.from(listenerMap.values()).find((entry) => {
    const matchPredicateOrType = typeof type === "string" ? entry.type === type : entry.predicate === predicate;
    return matchPredicateOrType && entry.effect === effect;
  });
};
var cancelActiveListeners = (entry) => {
  entry.pending.forEach((controller) => {
    controller.abort(listenerCancelled);
  });
};
var createClearListenerMiddleware = (listenerMap, executingListeners) => {
  return () => {
    for (const listener2 of executingListeners.keys()) {
      cancelActiveListeners(listener2);
    }
    listenerMap.clear();
  };
};
var safelyNotifyError = (errorHandler, errorToNotify, errorInfo) => {
  try {
    errorHandler(errorToNotify, errorInfo);
  } catch (errorHandlerError) {
    setTimeout(() => {
      throw errorHandlerError;
    }, 0);
  }
};
var addListener = /* @__PURE__ */ assign(/* @__PURE__ */ createAction(`${alm}/add`), {
  withTypes: () => addListener
});
var clearAllListeners = /* @__PURE__ */ createAction(`${alm}/removeAll`);
var removeListener = /* @__PURE__ */ assign(/* @__PURE__ */ createAction(`${alm}/remove`), {
  withTypes: () => removeListener
});
var defaultErrorHandler = (...args) => {
  console.error(`${alm}/error`, ...args);
};
var createListenerMiddleware = (middlewareOptions = {}) => {
  const listenerMap = /* @__PURE__ */ new Map();
  const executingListeners = /* @__PURE__ */ new Map();
  const trackExecutingListener = (entry) => {
    const count = executingListeners.get(entry) ?? 0;
    executingListeners.set(entry, count + 1);
  };
  const untrackExecutingListener = (entry) => {
    const count = executingListeners.get(entry) ?? 1;
    if (count === 1) {
      executingListeners.delete(entry);
    } else {
      executingListeners.set(entry, count - 1);
    }
  };
  const {
    extra,
    onError = defaultErrorHandler
  } = middlewareOptions;
  assertFunction(onError, "onError");
  const insertEntry = (entry) => {
    entry.unsubscribe = () => listenerMap.delete(entry.id);
    listenerMap.set(entry.id, entry);
    return (cancelOptions) => {
      entry.unsubscribe();
      if (cancelOptions?.cancelActive) {
        cancelActiveListeners(entry);
      }
    };
  };
  const startListening = (options) => {
    const entry = findListenerEntry(listenerMap, options) ?? createListenerEntry(options);
    return insertEntry(entry);
  };
  assign(startListening, {
    withTypes: () => startListening
  });
  const stopListening = (options) => {
    const entry = findListenerEntry(listenerMap, options);
    if (entry) {
      entry.unsubscribe();
      if (options.cancelActive) {
        cancelActiveListeners(entry);
      }
    }
    return !!entry;
  };
  assign(stopListening, {
    withTypes: () => stopListening
  });
  const notifyListener = async (entry, action, api, getOriginalState) => {
    const internalTaskController = new AbortController();
    const take = createTakePattern(startListening, internalTaskController.signal);
    const autoJoinPromises = [];
    try {
      entry.pending.add(internalTaskController);
      trackExecutingListener(entry);
      await Promise.resolve(entry.effect(
        action,
        // Use assign() rather than ... to avoid extra helper functions added to bundle
        assign({}, api, {
          getOriginalState,
          condition: (predicate, timeout) => take(predicate, timeout).then(Boolean),
          take,
          delay: createDelay(internalTaskController.signal),
          pause: createPause(internalTaskController.signal),
          extra,
          signal: internalTaskController.signal,
          fork: createFork(internalTaskController.signal, autoJoinPromises),
          unsubscribe: entry.unsubscribe,
          subscribe: () => {
            listenerMap.set(entry.id, entry);
          },
          cancelActiveListeners: () => {
            entry.pending.forEach((controller, _, set) => {
              if (controller !== internalTaskController) {
                controller.abort(listenerCancelled);
                set.delete(controller);
              }
            });
          },
          cancel: () => {
            internalTaskController.abort(listenerCancelled);
            entry.pending.delete(internalTaskController);
          },
          throwIfCancelled: () => {
            validateActive(internalTaskController.signal);
          }
        })
      ));
    } catch (listenerError) {
      if (!(listenerError instanceof TaskAbortError)) {
        safelyNotifyError(onError, listenerError, {
          raisedBy: "effect"
        });
      }
    } finally {
      await Promise.all(autoJoinPromises);
      internalTaskController.abort(listenerCompleted);
      untrackExecutingListener(entry);
      entry.pending.delete(internalTaskController);
    }
  };
  const clearListenerMiddleware = createClearListenerMiddleware(listenerMap, executingListeners);
  const middleware = (api) => (next) => (action) => {
    if (!(0,redux__WEBPACK_IMPORTED_MODULE_0__.isAction)(action)) {
      return next(action);
    }
    if (addListener.match(action)) {
      return startListening(action.payload);
    }
    if (clearAllListeners.match(action)) {
      clearListenerMiddleware();
      return;
    }
    if (removeListener.match(action)) {
      return stopListening(action.payload);
    }
    let originalState = api.getState();
    const getOriginalState = () => {
      if (originalState === INTERNAL_NIL_TOKEN) {
        throw new Error( false ? 0 : `${alm}: getOriginalState can only be called synchronously`);
      }
      return originalState;
    };
    let result;
    try {
      result = next(action);
      if (listenerMap.size > 0) {
        const currentState = api.getState();
        const listenerEntries = Array.from(listenerMap.values());
        for (const entry of listenerEntries) {
          let runListener = false;
          try {
            runListener = entry.predicate(action, currentState, originalState);
          } catch (predicateError) {
            runListener = false;
            safelyNotifyError(onError, predicateError, {
              raisedBy: "predicate"
            });
          }
          if (!runListener) {
            continue;
          }
          notifyListener(entry, action, api, getOriginalState);
        }
      }
    } finally {
      originalState = INTERNAL_NIL_TOKEN;
    }
    return result;
  };
  return {
    middleware,
    startListening,
    stopListening,
    clearListeners: clearListenerMiddleware
  };
};

// src/dynamicMiddleware/index.ts
var createMiddlewareEntry = (middleware) => ({
  middleware,
  applied: /* @__PURE__ */ new Map()
});
var matchInstance = (instanceId) => (action) => action?.meta?.instanceId === instanceId;
var createDynamicMiddleware = () => {
  const instanceId = nanoid();
  const middlewareMap = /* @__PURE__ */ new Map();
  const withMiddleware = Object.assign(createAction("dynamicMiddleware/add", (...middlewares) => ({
    payload: middlewares,
    meta: {
      instanceId
    }
  })), {
    withTypes: () => withMiddleware
  });
  const addMiddleware = Object.assign(function addMiddleware2(...middlewares) {
    middlewares.forEach((middleware2) => {
      getOrInsertComputed(middlewareMap, middleware2, createMiddlewareEntry);
    });
  }, {
    withTypes: () => addMiddleware
  });
  const getFinalMiddleware = (api) => {
    const appliedMiddleware = Array.from(middlewareMap.values()).map((entry) => getOrInsertComputed(entry.applied, api, entry.middleware));
    return (0,redux__WEBPACK_IMPORTED_MODULE_0__.compose)(...appliedMiddleware);
  };
  const isWithMiddleware = isAllOf(withMiddleware, matchInstance(instanceId));
  const middleware = (api) => (next) => (action) => {
    if (isWithMiddleware(action)) {
      addMiddleware(...action.payload);
      return api.dispatch;
    }
    return getFinalMiddleware(api)(next)(action);
  };
  return {
    middleware,
    addMiddleware,
    withMiddleware,
    instanceId
  };
};

// src/combineSlices.ts

var isSliceLike = (maybeSliceLike) => "reducerPath" in maybeSliceLike && typeof maybeSliceLike.reducerPath === "string";
var getReducers = (slices) => slices.flatMap((sliceOrMap) => isSliceLike(sliceOrMap) ? [[sliceOrMap.reducerPath, sliceOrMap.reducer]] : Object.entries(sliceOrMap));
var ORIGINAL_STATE = Symbol.for("rtk-state-proxy-original");
var isStateProxy = (value) => !!value && !!value[ORIGINAL_STATE];
var stateProxyMap = /* @__PURE__ */ new WeakMap();
var createStateProxy = (state, reducerMap, initialStateCache) => getOrInsertComputed(stateProxyMap, state, () => new Proxy(state, {
  get: (target, prop, receiver) => {
    if (prop === ORIGINAL_STATE) return target;
    const result = Reflect.get(target, prop, receiver);
    if (typeof result === "undefined") {
      const cached = initialStateCache[prop];
      if (typeof cached !== "undefined") return cached;
      const reducer = reducerMap[prop];
      if (reducer) {
        const reducerResult = reducer(void 0, {
          type: nanoid()
        });
        if (typeof reducerResult === "undefined") {
          throw new Error( false ? 0 : `The slice reducer for key "${prop.toString()}" returned undefined when called for selector(). If the state passed to the reducer is undefined, you must explicitly return the initial state. The initial state may not be undefined. If you don't want to set a value for this reducer, you can use null instead of undefined.`);
        }
        initialStateCache[prop] = reducerResult;
        return reducerResult;
      }
    }
    return result;
  }
}));
var original = (state) => {
  if (!isStateProxy(state)) {
    throw new Error( false ? 0 : "original must be used on state Proxy");
  }
  return state[ORIGINAL_STATE];
};
var emptyObject = {};
var noopReducer = (state = emptyObject) => state;
function combineSlices(...slices) {
  const reducerMap = Object.fromEntries(getReducers(slices));
  const getReducer = () => Object.keys(reducerMap).length ? (0,redux__WEBPACK_IMPORTED_MODULE_0__.combineReducers)(reducerMap) : noopReducer;
  let reducer = getReducer();
  function combinedReducer(state, action) {
    return reducer(state, action);
  }
  combinedReducer.withLazyLoadedSlices = () => combinedReducer;
  const initialStateCache = {};
  const inject = (slice, config = {}) => {
    const {
      reducerPath,
      reducer: reducerToInject
    } = slice;
    const currentReducer = reducerMap[reducerPath];
    if (!config.overrideExisting && currentReducer && currentReducer !== reducerToInject) {
      if (typeof process !== "undefined" && "development" === "development") {
        console.error(`called \`inject\` to override already-existing reducer ${reducerPath} without specifying \`overrideExisting: true\``);
      }
      return combinedReducer;
    }
    if (config.overrideExisting && currentReducer !== reducerToInject) {
      delete initialStateCache[reducerPath];
    }
    reducerMap[reducerPath] = reducerToInject;
    reducer = getReducer();
    return combinedReducer;
  };
  const selector = Object.assign(function makeSelector(selectorFn, selectState) {
    return function selector2(state, ...args) {
      return selectorFn(createStateProxy(selectState ? selectState(state, ...args) : state, reducerMap, initialStateCache), ...args);
    };
  }, {
    original
  });
  return Object.assign(combinedReducer, {
    inject,
    selector
  });
}

// src/formatProdErrorMessage.ts
function formatProdErrorMessage(code) {
  return `Minified Redux Toolkit error #${code}; visit https://redux-toolkit.js.org/Errors?code=${code} for the full message or use the non-minified dev environment for full errors. `;
}

//# sourceMappingURL=redux-toolkit.modern.mjs.map

/***/ },

/***/ "./node_modules/dompurify/dist/purify.es.mjs"
/*!***************************************************!*\
  !*** ./node_modules/dompurify/dist/purify.es.mjs ***!
  \***************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ purify)
/* harmony export */ });
/*! @license DOMPurify 3.3.2 | (c) Cure53 and other contributors | Released under the Apache license 2.0 and Mozilla Public License 2.0 | github.com/cure53/DOMPurify/blob/3.3.2/LICENSE */

const {
  entries,
  setPrototypeOf,
  isFrozen,
  getPrototypeOf,
  getOwnPropertyDescriptor
} = Object;
let {
  freeze,
  seal,
  create
} = Object; // eslint-disable-line import/no-mutable-exports
let {
  apply,
  construct
} = typeof Reflect !== 'undefined' && Reflect;
if (!freeze) {
  freeze = function freeze(x) {
    return x;
  };
}
if (!seal) {
  seal = function seal(x) {
    return x;
  };
}
if (!apply) {
  apply = function apply(func, thisArg) {
    for (var _len = arguments.length, args = new Array(_len > 2 ? _len - 2 : 0), _key = 2; _key < _len; _key++) {
      args[_key - 2] = arguments[_key];
    }
    return func.apply(thisArg, args);
  };
}
if (!construct) {
  construct = function construct(Func) {
    for (var _len2 = arguments.length, args = new Array(_len2 > 1 ? _len2 - 1 : 0), _key2 = 1; _key2 < _len2; _key2++) {
      args[_key2 - 1] = arguments[_key2];
    }
    return new Func(...args);
  };
}
const arrayForEach = unapply(Array.prototype.forEach);
const arrayLastIndexOf = unapply(Array.prototype.lastIndexOf);
const arrayPop = unapply(Array.prototype.pop);
const arrayPush = unapply(Array.prototype.push);
const arraySplice = unapply(Array.prototype.splice);
const stringToLowerCase = unapply(String.prototype.toLowerCase);
const stringToString = unapply(String.prototype.toString);
const stringMatch = unapply(String.prototype.match);
const stringReplace = unapply(String.prototype.replace);
const stringIndexOf = unapply(String.prototype.indexOf);
const stringTrim = unapply(String.prototype.trim);
const objectHasOwnProperty = unapply(Object.prototype.hasOwnProperty);
const regExpTest = unapply(RegExp.prototype.test);
const typeErrorCreate = unconstruct(TypeError);
/**
 * Creates a new function that calls the given function with a specified thisArg and arguments.
 *
 * @param func - The function to be wrapped and called.
 * @returns A new function that calls the given function with a specified thisArg and arguments.
 */
function unapply(func) {
  return function (thisArg) {
    if (thisArg instanceof RegExp) {
      thisArg.lastIndex = 0;
    }
    for (var _len3 = arguments.length, args = new Array(_len3 > 1 ? _len3 - 1 : 0), _key3 = 1; _key3 < _len3; _key3++) {
      args[_key3 - 1] = arguments[_key3];
    }
    return apply(func, thisArg, args);
  };
}
/**
 * Creates a new function that constructs an instance of the given constructor function with the provided arguments.
 *
 * @param func - The constructor function to be wrapped and called.
 * @returns A new function that constructs an instance of the given constructor function with the provided arguments.
 */
function unconstruct(Func) {
  return function () {
    for (var _len4 = arguments.length, args = new Array(_len4), _key4 = 0; _key4 < _len4; _key4++) {
      args[_key4] = arguments[_key4];
    }
    return construct(Func, args);
  };
}
/**
 * Add properties to a lookup table
 *
 * @param set - The set to which elements will be added.
 * @param array - The array containing elements to be added to the set.
 * @param transformCaseFunc - An optional function to transform the case of each element before adding to the set.
 * @returns The modified set with added elements.
 */
function addToSet(set, array) {
  let transformCaseFunc = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : stringToLowerCase;
  if (setPrototypeOf) {
    // Make 'in' and truthy checks like Boolean(set.constructor)
    // independent of any properties defined on Object.prototype.
    // Prevent prototype setters from intercepting set as a this value.
    setPrototypeOf(set, null);
  }
  let l = array.length;
  while (l--) {
    let element = array[l];
    if (typeof element === 'string') {
      const lcElement = transformCaseFunc(element);
      if (lcElement !== element) {
        // Config presets (e.g. tags.js, attrs.js) are immutable.
        if (!isFrozen(array)) {
          array[l] = lcElement;
        }
        element = lcElement;
      }
    }
    set[element] = true;
  }
  return set;
}
/**
 * Clean up an array to harden against CSPP
 *
 * @param array - The array to be cleaned.
 * @returns The cleaned version of the array
 */
function cleanArray(array) {
  for (let index = 0; index < array.length; index++) {
    const isPropertyExist = objectHasOwnProperty(array, index);
    if (!isPropertyExist) {
      array[index] = null;
    }
  }
  return array;
}
/**
 * Shallow clone an object
 *
 * @param object - The object to be cloned.
 * @returns A new object that copies the original.
 */
function clone(object) {
  const newObject = create(null);
  for (const [property, value] of entries(object)) {
    const isPropertyExist = objectHasOwnProperty(object, property);
    if (isPropertyExist) {
      if (Array.isArray(value)) {
        newObject[property] = cleanArray(value);
      } else if (value && typeof value === 'object' && value.constructor === Object) {
        newObject[property] = clone(value);
      } else {
        newObject[property] = value;
      }
    }
  }
  return newObject;
}
/**
 * This method automatically checks if the prop is function or getter and behaves accordingly.
 *
 * @param object - The object to look up the getter function in its prototype chain.
 * @param prop - The property name for which to find the getter function.
 * @returns The getter function found in the prototype chain or a fallback function.
 */
function lookupGetter(object, prop) {
  while (object !== null) {
    const desc = getOwnPropertyDescriptor(object, prop);
    if (desc) {
      if (desc.get) {
        return unapply(desc.get);
      }
      if (typeof desc.value === 'function') {
        return unapply(desc.value);
      }
    }
    object = getPrototypeOf(object);
  }
  function fallbackValue() {
    return null;
  }
  return fallbackValue;
}

const html$1 = freeze(['a', 'abbr', 'acronym', 'address', 'area', 'article', 'aside', 'audio', 'b', 'bdi', 'bdo', 'big', 'blink', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'content', 'data', 'datalist', 'dd', 'decorator', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt', 'element', 'em', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li', 'main', 'map', 'mark', 'marquee', 'menu', 'menuitem', 'meter', 'nav', 'nobr', 'ol', 'optgroup', 'option', 'output', 'p', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'search', 'section', 'select', 'shadow', 'slot', 'small', 'source', 'spacer', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr']);
const svg$1 = freeze(['svg', 'a', 'altglyph', 'altglyphdef', 'altglyphitem', 'animatecolor', 'animatemotion', 'animatetransform', 'circle', 'clippath', 'defs', 'desc', 'ellipse', 'enterkeyhint', 'exportparts', 'filter', 'font', 'g', 'glyph', 'glyphref', 'hkern', 'image', 'inputmode', 'line', 'lineargradient', 'marker', 'mask', 'metadata', 'mpath', 'part', 'path', 'pattern', 'polygon', 'polyline', 'radialgradient', 'rect', 'stop', 'style', 'switch', 'symbol', 'text', 'textpath', 'title', 'tref', 'tspan', 'view', 'vkern']);
const svgFilters = freeze(['feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence']);
// List of SVG elements that are disallowed by default.
// We still need to know them so that we can do namespace
// checks properly in case one wants to add them to
// allow-list.
const svgDisallowed = freeze(['animate', 'color-profile', 'cursor', 'discard', 'font-face', 'font-face-format', 'font-face-name', 'font-face-src', 'font-face-uri', 'foreignobject', 'hatch', 'hatchpath', 'mesh', 'meshgradient', 'meshpatch', 'meshrow', 'missing-glyph', 'script', 'set', 'solidcolor', 'unknown', 'use']);
const mathMl$1 = freeze(['math', 'menclose', 'merror', 'mfenced', 'mfrac', 'mglyph', 'mi', 'mlabeledtr', 'mmultiscripts', 'mn', 'mo', 'mover', 'mpadded', 'mphantom', 'mroot', 'mrow', 'ms', 'mspace', 'msqrt', 'mstyle', 'msub', 'msup', 'msubsup', 'mtable', 'mtd', 'mtext', 'mtr', 'munder', 'munderover', 'mprescripts']);
// Similarly to SVG, we want to know all MathML elements,
// even those that we disallow by default.
const mathMlDisallowed = freeze(['maction', 'maligngroup', 'malignmark', 'mlongdiv', 'mscarries', 'mscarry', 'msgroup', 'mstack', 'msline', 'msrow', 'semantics', 'annotation', 'annotation-xml', 'mprescripts', 'none']);
const text = freeze(['#text']);

const html = freeze(['accept', 'action', 'align', 'alt', 'autocapitalize', 'autocomplete', 'autopictureinpicture', 'autoplay', 'background', 'bgcolor', 'border', 'capture', 'cellpadding', 'cellspacing', 'checked', 'cite', 'class', 'clear', 'color', 'cols', 'colspan', 'controls', 'controlslist', 'coords', 'crossorigin', 'datetime', 'decoding', 'default', 'dir', 'disabled', 'disablepictureinpicture', 'disableremoteplayback', 'download', 'draggable', 'enctype', 'enterkeyhint', 'exportparts', 'face', 'for', 'headers', 'height', 'hidden', 'high', 'href', 'hreflang', 'id', 'inert', 'inputmode', 'integrity', 'ismap', 'kind', 'label', 'lang', 'list', 'loading', 'loop', 'low', 'max', 'maxlength', 'media', 'method', 'min', 'minlength', 'multiple', 'muted', 'name', 'nonce', 'noshade', 'novalidate', 'nowrap', 'open', 'optimum', 'part', 'pattern', 'placeholder', 'playsinline', 'popover', 'popovertarget', 'popovertargetaction', 'poster', 'preload', 'pubdate', 'radiogroup', 'readonly', 'rel', 'required', 'rev', 'reversed', 'role', 'rows', 'rowspan', 'spellcheck', 'scope', 'selected', 'shape', 'size', 'sizes', 'slot', 'span', 'srclang', 'start', 'src', 'srcset', 'step', 'style', 'summary', 'tabindex', 'title', 'translate', 'type', 'usemap', 'valign', 'value', 'width', 'wrap', 'xmlns', 'slot']);
const svg = freeze(['accent-height', 'accumulate', 'additive', 'alignment-baseline', 'amplitude', 'ascent', 'attributename', 'attributetype', 'azimuth', 'basefrequency', 'baseline-shift', 'begin', 'bias', 'by', 'class', 'clip', 'clippathunits', 'clip-path', 'clip-rule', 'color', 'color-interpolation', 'color-interpolation-filters', 'color-profile', 'color-rendering', 'cx', 'cy', 'd', 'dx', 'dy', 'diffuseconstant', 'direction', 'display', 'divisor', 'dur', 'edgemode', 'elevation', 'end', 'exponent', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'filterunits', 'flood-color', 'flood-opacity', 'font-family', 'font-size', 'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'fx', 'fy', 'g1', 'g2', 'glyph-name', 'glyphref', 'gradientunits', 'gradienttransform', 'height', 'href', 'id', 'image-rendering', 'in', 'in2', 'intercept', 'k', 'k1', 'k2', 'k3', 'k4', 'kerning', 'keypoints', 'keysplines', 'keytimes', 'lang', 'lengthadjust', 'letter-spacing', 'kernelmatrix', 'kernelunitlength', 'lighting-color', 'local', 'marker-end', 'marker-mid', 'marker-start', 'markerheight', 'markerunits', 'markerwidth', 'maskcontentunits', 'maskunits', 'max', 'mask', 'mask-type', 'media', 'method', 'mode', 'min', 'name', 'numoctaves', 'offset', 'operator', 'opacity', 'order', 'orient', 'orientation', 'origin', 'overflow', 'paint-order', 'path', 'pathlength', 'patterncontentunits', 'patterntransform', 'patternunits', 'points', 'preservealpha', 'preserveaspectratio', 'primitiveunits', 'r', 'rx', 'ry', 'radius', 'refx', 'refy', 'repeatcount', 'repeatdur', 'restart', 'result', 'rotate', 'scale', 'seed', 'shape-rendering', 'slope', 'specularconstant', 'specularexponent', 'spreadmethod', 'startoffset', 'stddeviation', 'stitchtiles', 'stop-color', 'stop-opacity', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke', 'stroke-width', 'style', 'surfacescale', 'systemlanguage', 'tabindex', 'tablevalues', 'targetx', 'targety', 'transform', 'transform-origin', 'text-anchor', 'text-decoration', 'text-rendering', 'textlength', 'type', 'u1', 'u2', 'unicode', 'values', 'viewbox', 'visibility', 'version', 'vert-adv-y', 'vert-origin-x', 'vert-origin-y', 'width', 'word-spacing', 'wrap', 'writing-mode', 'xchannelselector', 'ychannelselector', 'x', 'x1', 'x2', 'xmlns', 'y', 'y1', 'y2', 'z', 'zoomandpan']);
const mathMl = freeze(['accent', 'accentunder', 'align', 'bevelled', 'close', 'columnsalign', 'columnlines', 'columnspan', 'denomalign', 'depth', 'dir', 'display', 'displaystyle', 'encoding', 'fence', 'frame', 'height', 'href', 'id', 'largeop', 'length', 'linethickness', 'lspace', 'lquote', 'mathbackground', 'mathcolor', 'mathsize', 'mathvariant', 'maxsize', 'minsize', 'movablelimits', 'notation', 'numalign', 'open', 'rowalign', 'rowlines', 'rowspacing', 'rowspan', 'rspace', 'rquote', 'scriptlevel', 'scriptminsize', 'scriptsizemultiplier', 'selection', 'separator', 'separators', 'stretchy', 'subscriptshift', 'supscriptshift', 'symmetric', 'voffset', 'width', 'xmlns']);
const xml = freeze(['xlink:href', 'xml:id', 'xlink:title', 'xml:space', 'xmlns:xlink']);

// eslint-disable-next-line unicorn/better-regex
const MUSTACHE_EXPR = seal(/\{\{[\w\W]*|[\w\W]*\}\}/gm); // Specify template detection regex for SAFE_FOR_TEMPLATES mode
const ERB_EXPR = seal(/<%[\w\W]*|[\w\W]*%>/gm);
const TMPLIT_EXPR = seal(/\$\{[\w\W]*/gm); // eslint-disable-line unicorn/better-regex
const DATA_ATTR = seal(/^data-[\-\w.\u00B7-\uFFFF]+$/); // eslint-disable-line no-useless-escape
const ARIA_ATTR = seal(/^aria-[\-\w]+$/); // eslint-disable-line no-useless-escape
const IS_ALLOWED_URI = seal(/^(?:(?:(?:f|ht)tps?|mailto|tel|callto|sms|cid|xmpp|matrix):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i // eslint-disable-line no-useless-escape
);
const IS_SCRIPT_OR_DATA = seal(/^(?:\w+script|data):/i);
const ATTR_WHITESPACE = seal(/[\u0000-\u0020\u00A0\u1680\u180E\u2000-\u2029\u205F\u3000]/g // eslint-disable-line no-control-regex
);
const DOCTYPE_NAME = seal(/^html$/i);
const CUSTOM_ELEMENT = seal(/^[a-z][.\w]*(-[.\w]+)+$/i);

var EXPRESSIONS = /*#__PURE__*/Object.freeze({
  __proto__: null,
  ARIA_ATTR: ARIA_ATTR,
  ATTR_WHITESPACE: ATTR_WHITESPACE,
  CUSTOM_ELEMENT: CUSTOM_ELEMENT,
  DATA_ATTR: DATA_ATTR,
  DOCTYPE_NAME: DOCTYPE_NAME,
  ERB_EXPR: ERB_EXPR,
  IS_ALLOWED_URI: IS_ALLOWED_URI,
  IS_SCRIPT_OR_DATA: IS_SCRIPT_OR_DATA,
  MUSTACHE_EXPR: MUSTACHE_EXPR,
  TMPLIT_EXPR: TMPLIT_EXPR
});

/* eslint-disable @typescript-eslint/indent */
// https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
const NODE_TYPE = {
  element: 1,
  attribute: 2,
  text: 3,
  cdataSection: 4,
  entityReference: 5,
  // Deprecated
  entityNode: 6,
  // Deprecated
  progressingInstruction: 7,
  comment: 8,
  document: 9,
  documentType: 10,
  documentFragment: 11,
  notation: 12 // Deprecated
};
const getGlobal = function getGlobal() {
  return typeof window === 'undefined' ? null : window;
};
/**
 * Creates a no-op policy for internal use only.
 * Don't export this function outside this module!
 * @param trustedTypes The policy factory.
 * @param purifyHostElement The Script element used to load DOMPurify (to determine policy name suffix).
 * @return The policy created (or null, if Trusted Types
 * are not supported or creating the policy failed).
 */
const _createTrustedTypesPolicy = function _createTrustedTypesPolicy(trustedTypes, purifyHostElement) {
  if (typeof trustedTypes !== 'object' || typeof trustedTypes.createPolicy !== 'function') {
    return null;
  }
  // Allow the callers to control the unique policy name
  // by adding a data-tt-policy-suffix to the script element with the DOMPurify.
  // Policy creation with duplicate names throws in Trusted Types.
  let suffix = null;
  const ATTR_NAME = 'data-tt-policy-suffix';
  if (purifyHostElement && purifyHostElement.hasAttribute(ATTR_NAME)) {
    suffix = purifyHostElement.getAttribute(ATTR_NAME);
  }
  const policyName = 'dompurify' + (suffix ? '#' + suffix : '');
  try {
    return trustedTypes.createPolicy(policyName, {
      createHTML(html) {
        return html;
      },
      createScriptURL(scriptUrl) {
        return scriptUrl;
      }
    });
  } catch (_) {
    // Policy creation failed (most likely another DOMPurify script has
    // already run). Skip creating the policy, as this will only cause errors
    // if TT are enforced.
    console.warn('TrustedTypes policy ' + policyName + ' could not be created.');
    return null;
  }
};
const _createHooksMap = function _createHooksMap() {
  return {
    afterSanitizeAttributes: [],
    afterSanitizeElements: [],
    afterSanitizeShadowDOM: [],
    beforeSanitizeAttributes: [],
    beforeSanitizeElements: [],
    beforeSanitizeShadowDOM: [],
    uponSanitizeAttribute: [],
    uponSanitizeElement: [],
    uponSanitizeShadowNode: []
  };
};
function createDOMPurify() {
  let window = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : getGlobal();
  const DOMPurify = root => createDOMPurify(root);
  DOMPurify.version = '3.3.2';
  DOMPurify.removed = [];
  if (!window || !window.document || window.document.nodeType !== NODE_TYPE.document || !window.Element) {
    // Not running in a browser, provide a factory function
    // so that you can pass your own Window
    DOMPurify.isSupported = false;
    return DOMPurify;
  }
  let {
    document
  } = window;
  const originalDocument = document;
  const currentScript = originalDocument.currentScript;
  const {
    DocumentFragment,
    HTMLTemplateElement,
    Node,
    Element,
    NodeFilter,
    NamedNodeMap = window.NamedNodeMap || window.MozNamedAttrMap,
    HTMLFormElement,
    DOMParser,
    trustedTypes
  } = window;
  const ElementPrototype = Element.prototype;
  const cloneNode = lookupGetter(ElementPrototype, 'cloneNode');
  const remove = lookupGetter(ElementPrototype, 'remove');
  const getNextSibling = lookupGetter(ElementPrototype, 'nextSibling');
  const getChildNodes = lookupGetter(ElementPrototype, 'childNodes');
  const getParentNode = lookupGetter(ElementPrototype, 'parentNode');
  // As per issue #47, the web-components registry is inherited by a
  // new document created via createHTMLDocument. As per the spec
  // (http://w3c.github.io/webcomponents/spec/custom/#creating-and-passing-registries)
  // a new empty registry is used when creating a template contents owner
  // document, so we use that as our parent document to ensure nothing
  // is inherited.
  if (typeof HTMLTemplateElement === 'function') {
    const template = document.createElement('template');
    if (template.content && template.content.ownerDocument) {
      document = template.content.ownerDocument;
    }
  }
  let trustedTypesPolicy;
  let emptyHTML = '';
  const {
    implementation,
    createNodeIterator,
    createDocumentFragment,
    getElementsByTagName
  } = document;
  const {
    importNode
  } = originalDocument;
  let hooks = _createHooksMap();
  /**
   * Expose whether this browser supports running the full DOMPurify.
   */
  DOMPurify.isSupported = typeof entries === 'function' && typeof getParentNode === 'function' && implementation && implementation.createHTMLDocument !== undefined;
  const {
    MUSTACHE_EXPR,
    ERB_EXPR,
    TMPLIT_EXPR,
    DATA_ATTR,
    ARIA_ATTR,
    IS_SCRIPT_OR_DATA,
    ATTR_WHITESPACE,
    CUSTOM_ELEMENT
  } = EXPRESSIONS;
  let {
    IS_ALLOWED_URI: IS_ALLOWED_URI$1
  } = EXPRESSIONS;
  /**
   * We consider the elements and attributes below to be safe. Ideally
   * don't add any new ones but feel free to remove unwanted ones.
   */
  /* allowed element names */
  let ALLOWED_TAGS = null;
  const DEFAULT_ALLOWED_TAGS = addToSet({}, [...html$1, ...svg$1, ...svgFilters, ...mathMl$1, ...text]);
  /* Allowed attribute names */
  let ALLOWED_ATTR = null;
  const DEFAULT_ALLOWED_ATTR = addToSet({}, [...html, ...svg, ...mathMl, ...xml]);
  /*
   * Configure how DOMPurify should handle custom elements and their attributes as well as customized built-in elements.
   * @property {RegExp|Function|null} tagNameCheck one of [null, regexPattern, predicate]. Default: `null` (disallow any custom elements)
   * @property {RegExp|Function|null} attributeNameCheck one of [null, regexPattern, predicate]. Default: `null` (disallow any attributes not on the allow list)
   * @property {boolean} allowCustomizedBuiltInElements allow custom elements derived from built-ins if they pass CUSTOM_ELEMENT_HANDLING.tagNameCheck. Default: `false`.
   */
  let CUSTOM_ELEMENT_HANDLING = Object.seal(create(null, {
    tagNameCheck: {
      writable: true,
      configurable: false,
      enumerable: true,
      value: null
    },
    attributeNameCheck: {
      writable: true,
      configurable: false,
      enumerable: true,
      value: null
    },
    allowCustomizedBuiltInElements: {
      writable: true,
      configurable: false,
      enumerable: true,
      value: false
    }
  }));
  /* Explicitly forbidden tags (overrides ALLOWED_TAGS/ADD_TAGS) */
  let FORBID_TAGS = null;
  /* Explicitly forbidden attributes (overrides ALLOWED_ATTR/ADD_ATTR) */
  let FORBID_ATTR = null;
  /* Config object to store ADD_TAGS/ADD_ATTR functions (when used as functions) */
  const EXTRA_ELEMENT_HANDLING = Object.seal(create(null, {
    tagCheck: {
      writable: true,
      configurable: false,
      enumerable: true,
      value: null
    },
    attributeCheck: {
      writable: true,
      configurable: false,
      enumerable: true,
      value: null
    }
  }));
  /* Decide if ARIA attributes are okay */
  let ALLOW_ARIA_ATTR = true;
  /* Decide if custom data attributes are okay */
  let ALLOW_DATA_ATTR = true;
  /* Decide if unknown protocols are okay */
  let ALLOW_UNKNOWN_PROTOCOLS = false;
  /* Decide if self-closing tags in attributes are allowed.
   * Usually removed due to a mXSS issue in jQuery 3.0 */
  let ALLOW_SELF_CLOSE_IN_ATTR = true;
  /* Output should be safe for common template engines.
   * This means, DOMPurify removes data attributes, mustaches and ERB
   */
  let SAFE_FOR_TEMPLATES = false;
  /* Output should be safe even for XML used within HTML and alike.
   * This means, DOMPurify removes comments when containing risky content.
   */
  let SAFE_FOR_XML = true;
  /* Decide if document with <html>... should be returned */
  let WHOLE_DOCUMENT = false;
  /* Track whether config is already set on this instance of DOMPurify. */
  let SET_CONFIG = false;
  /* Decide if all elements (e.g. style, script) must be children of
   * document.body. By default, browsers might move them to document.head */
  let FORCE_BODY = false;
  /* Decide if a DOM `HTMLBodyElement` should be returned, instead of a html
   * string (or a TrustedHTML object if Trusted Types are supported).
   * If `WHOLE_DOCUMENT` is enabled a `HTMLHtmlElement` will be returned instead
   */
  let RETURN_DOM = false;
  /* Decide if a DOM `DocumentFragment` should be returned, instead of a html
   * string  (or a TrustedHTML object if Trusted Types are supported) */
  let RETURN_DOM_FRAGMENT = false;
  /* Try to return a Trusted Type object instead of a string, return a string in
   * case Trusted Types are not supported  */
  let RETURN_TRUSTED_TYPE = false;
  /* Output should be free from DOM clobbering attacks?
   * This sanitizes markups named with colliding, clobberable built-in DOM APIs.
   */
  let SANITIZE_DOM = true;
  /* Achieve full DOM Clobbering protection by isolating the namespace of named
   * properties and JS variables, mitigating attacks that abuse the HTML/DOM spec rules.
   *
   * HTML/DOM spec rules that enable DOM Clobbering:
   *   - Named Access on Window (§7.3.3)
   *   - DOM Tree Accessors (§3.1.5)
   *   - Form Element Parent-Child Relations (§4.10.3)
   *   - Iframe srcdoc / Nested WindowProxies (§4.8.5)
   *   - HTMLCollection (§4.2.10.2)
   *
   * Namespace isolation is implemented by prefixing `id` and `name` attributes
   * with a constant string, i.e., `user-content-`
   */
  let SANITIZE_NAMED_PROPS = false;
  const SANITIZE_NAMED_PROPS_PREFIX = 'user-content-';
  /* Keep element content when removing element? */
  let KEEP_CONTENT = true;
  /* If a `Node` is passed to sanitize(), then performs sanitization in-place instead
   * of importing it into a new Document and returning a sanitized copy */
  let IN_PLACE = false;
  /* Allow usage of profiles like html, svg and mathMl */
  let USE_PROFILES = {};
  /* Tags to ignore content of when KEEP_CONTENT is true */
  let FORBID_CONTENTS = null;
  const DEFAULT_FORBID_CONTENTS = addToSet({}, ['annotation-xml', 'audio', 'colgroup', 'desc', 'foreignobject', 'head', 'iframe', 'math', 'mi', 'mn', 'mo', 'ms', 'mtext', 'noembed', 'noframes', 'noscript', 'plaintext', 'script', 'style', 'svg', 'template', 'thead', 'title', 'video', 'xmp']);
  /* Tags that are safe for data: URIs */
  let DATA_URI_TAGS = null;
  const DEFAULT_DATA_URI_TAGS = addToSet({}, ['audio', 'video', 'img', 'source', 'image', 'track']);
  /* Attributes safe for values like "javascript:" */
  let URI_SAFE_ATTRIBUTES = null;
  const DEFAULT_URI_SAFE_ATTRIBUTES = addToSet({}, ['alt', 'class', 'for', 'id', 'label', 'name', 'pattern', 'placeholder', 'role', 'summary', 'title', 'value', 'style', 'xmlns']);
  const MATHML_NAMESPACE = 'http://www.w3.org/1998/Math/MathML';
  const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
  const HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';
  /* Document namespace */
  let NAMESPACE = HTML_NAMESPACE;
  let IS_EMPTY_INPUT = false;
  /* Allowed XHTML+XML namespaces */
  let ALLOWED_NAMESPACES = null;
  const DEFAULT_ALLOWED_NAMESPACES = addToSet({}, [MATHML_NAMESPACE, SVG_NAMESPACE, HTML_NAMESPACE], stringToString);
  let MATHML_TEXT_INTEGRATION_POINTS = addToSet({}, ['mi', 'mo', 'mn', 'ms', 'mtext']);
  let HTML_INTEGRATION_POINTS = addToSet({}, ['annotation-xml']);
  // Certain elements are allowed in both SVG and HTML
  // namespace. We need to specify them explicitly
  // so that they don't get erroneously deleted from
  // HTML namespace.
  const COMMON_SVG_AND_HTML_ELEMENTS = addToSet({}, ['title', 'style', 'font', 'a', 'script']);
  /* Parsing of strict XHTML documents */
  let PARSER_MEDIA_TYPE = null;
  const SUPPORTED_PARSER_MEDIA_TYPES = ['application/xhtml+xml', 'text/html'];
  const DEFAULT_PARSER_MEDIA_TYPE = 'text/html';
  let transformCaseFunc = null;
  /* Keep a reference to config to pass to hooks */
  let CONFIG = null;
  /* Ideally, do not touch anything below this line */
  /* ______________________________________________ */
  const formElement = document.createElement('form');
  const isRegexOrFunction = function isRegexOrFunction(testValue) {
    return testValue instanceof RegExp || testValue instanceof Function;
  };
  /**
   * _parseConfig
   *
   * @param cfg optional config literal
   */
  // eslint-disable-next-line complexity
  const _parseConfig = function _parseConfig() {
    let cfg = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    if (CONFIG && CONFIG === cfg) {
      return;
    }
    /* Shield configuration object from tampering */
    if (!cfg || typeof cfg !== 'object') {
      cfg = {};
    }
    /* Shield configuration object from prototype pollution */
    cfg = clone(cfg);
    PARSER_MEDIA_TYPE =
    // eslint-disable-next-line unicorn/prefer-includes
    SUPPORTED_PARSER_MEDIA_TYPES.indexOf(cfg.PARSER_MEDIA_TYPE) === -1 ? DEFAULT_PARSER_MEDIA_TYPE : cfg.PARSER_MEDIA_TYPE;
    // HTML tags and attributes are not case-sensitive, converting to lowercase. Keeping XHTML as is.
    transformCaseFunc = PARSER_MEDIA_TYPE === 'application/xhtml+xml' ? stringToString : stringToLowerCase;
    /* Set configuration parameters */
    ALLOWED_TAGS = objectHasOwnProperty(cfg, 'ALLOWED_TAGS') ? addToSet({}, cfg.ALLOWED_TAGS, transformCaseFunc) : DEFAULT_ALLOWED_TAGS;
    ALLOWED_ATTR = objectHasOwnProperty(cfg, 'ALLOWED_ATTR') ? addToSet({}, cfg.ALLOWED_ATTR, transformCaseFunc) : DEFAULT_ALLOWED_ATTR;
    ALLOWED_NAMESPACES = objectHasOwnProperty(cfg, 'ALLOWED_NAMESPACES') ? addToSet({}, cfg.ALLOWED_NAMESPACES, stringToString) : DEFAULT_ALLOWED_NAMESPACES;
    URI_SAFE_ATTRIBUTES = objectHasOwnProperty(cfg, 'ADD_URI_SAFE_ATTR') ? addToSet(clone(DEFAULT_URI_SAFE_ATTRIBUTES), cfg.ADD_URI_SAFE_ATTR, transformCaseFunc) : DEFAULT_URI_SAFE_ATTRIBUTES;
    DATA_URI_TAGS = objectHasOwnProperty(cfg, 'ADD_DATA_URI_TAGS') ? addToSet(clone(DEFAULT_DATA_URI_TAGS), cfg.ADD_DATA_URI_TAGS, transformCaseFunc) : DEFAULT_DATA_URI_TAGS;
    FORBID_CONTENTS = objectHasOwnProperty(cfg, 'FORBID_CONTENTS') ? addToSet({}, cfg.FORBID_CONTENTS, transformCaseFunc) : DEFAULT_FORBID_CONTENTS;
    FORBID_TAGS = objectHasOwnProperty(cfg, 'FORBID_TAGS') ? addToSet({}, cfg.FORBID_TAGS, transformCaseFunc) : clone({});
    FORBID_ATTR = objectHasOwnProperty(cfg, 'FORBID_ATTR') ? addToSet({}, cfg.FORBID_ATTR, transformCaseFunc) : clone({});
    USE_PROFILES = objectHasOwnProperty(cfg, 'USE_PROFILES') ? cfg.USE_PROFILES : false;
    ALLOW_ARIA_ATTR = cfg.ALLOW_ARIA_ATTR !== false; // Default true
    ALLOW_DATA_ATTR = cfg.ALLOW_DATA_ATTR !== false; // Default true
    ALLOW_UNKNOWN_PROTOCOLS = cfg.ALLOW_UNKNOWN_PROTOCOLS || false; // Default false
    ALLOW_SELF_CLOSE_IN_ATTR = cfg.ALLOW_SELF_CLOSE_IN_ATTR !== false; // Default true
    SAFE_FOR_TEMPLATES = cfg.SAFE_FOR_TEMPLATES || false; // Default false
    SAFE_FOR_XML = cfg.SAFE_FOR_XML !== false; // Default true
    WHOLE_DOCUMENT = cfg.WHOLE_DOCUMENT || false; // Default false
    RETURN_DOM = cfg.RETURN_DOM || false; // Default false
    RETURN_DOM_FRAGMENT = cfg.RETURN_DOM_FRAGMENT || false; // Default false
    RETURN_TRUSTED_TYPE = cfg.RETURN_TRUSTED_TYPE || false; // Default false
    FORCE_BODY = cfg.FORCE_BODY || false; // Default false
    SANITIZE_DOM = cfg.SANITIZE_DOM !== false; // Default true
    SANITIZE_NAMED_PROPS = cfg.SANITIZE_NAMED_PROPS || false; // Default false
    KEEP_CONTENT = cfg.KEEP_CONTENT !== false; // Default true
    IN_PLACE = cfg.IN_PLACE || false; // Default false
    IS_ALLOWED_URI$1 = cfg.ALLOWED_URI_REGEXP || IS_ALLOWED_URI;
    NAMESPACE = cfg.NAMESPACE || HTML_NAMESPACE;
    MATHML_TEXT_INTEGRATION_POINTS = cfg.MATHML_TEXT_INTEGRATION_POINTS || MATHML_TEXT_INTEGRATION_POINTS;
    HTML_INTEGRATION_POINTS = cfg.HTML_INTEGRATION_POINTS || HTML_INTEGRATION_POINTS;
    CUSTOM_ELEMENT_HANDLING = cfg.CUSTOM_ELEMENT_HANDLING || {};
    if (cfg.CUSTOM_ELEMENT_HANDLING && isRegexOrFunction(cfg.CUSTOM_ELEMENT_HANDLING.tagNameCheck)) {
      CUSTOM_ELEMENT_HANDLING.tagNameCheck = cfg.CUSTOM_ELEMENT_HANDLING.tagNameCheck;
    }
    if (cfg.CUSTOM_ELEMENT_HANDLING && isRegexOrFunction(cfg.CUSTOM_ELEMENT_HANDLING.attributeNameCheck)) {
      CUSTOM_ELEMENT_HANDLING.attributeNameCheck = cfg.CUSTOM_ELEMENT_HANDLING.attributeNameCheck;
    }
    if (cfg.CUSTOM_ELEMENT_HANDLING && typeof cfg.CUSTOM_ELEMENT_HANDLING.allowCustomizedBuiltInElements === 'boolean') {
      CUSTOM_ELEMENT_HANDLING.allowCustomizedBuiltInElements = cfg.CUSTOM_ELEMENT_HANDLING.allowCustomizedBuiltInElements;
    }
    if (SAFE_FOR_TEMPLATES) {
      ALLOW_DATA_ATTR = false;
    }
    if (RETURN_DOM_FRAGMENT) {
      RETURN_DOM = true;
    }
    /* Parse profile info */
    if (USE_PROFILES) {
      ALLOWED_TAGS = addToSet({}, text);
      ALLOWED_ATTR = create(null);
      if (USE_PROFILES.html === true) {
        addToSet(ALLOWED_TAGS, html$1);
        addToSet(ALLOWED_ATTR, html);
      }
      if (USE_PROFILES.svg === true) {
        addToSet(ALLOWED_TAGS, svg$1);
        addToSet(ALLOWED_ATTR, svg);
        addToSet(ALLOWED_ATTR, xml);
      }
      if (USE_PROFILES.svgFilters === true) {
        addToSet(ALLOWED_TAGS, svgFilters);
        addToSet(ALLOWED_ATTR, svg);
        addToSet(ALLOWED_ATTR, xml);
      }
      if (USE_PROFILES.mathMl === true) {
        addToSet(ALLOWED_TAGS, mathMl$1);
        addToSet(ALLOWED_ATTR, mathMl);
        addToSet(ALLOWED_ATTR, xml);
      }
    }
    /* Prevent function-based ADD_ATTR / ADD_TAGS from leaking across calls */
    if (!objectHasOwnProperty(cfg, 'ADD_TAGS')) {
      EXTRA_ELEMENT_HANDLING.tagCheck = null;
    }
    if (!objectHasOwnProperty(cfg, 'ADD_ATTR')) {
      EXTRA_ELEMENT_HANDLING.attributeCheck = null;
    }
    /* Merge configuration parameters */
    if (cfg.ADD_TAGS) {
      if (typeof cfg.ADD_TAGS === 'function') {
        EXTRA_ELEMENT_HANDLING.tagCheck = cfg.ADD_TAGS;
      } else {
        if (ALLOWED_TAGS === DEFAULT_ALLOWED_TAGS) {
          ALLOWED_TAGS = clone(ALLOWED_TAGS);
        }
        addToSet(ALLOWED_TAGS, cfg.ADD_TAGS, transformCaseFunc);
      }
    }
    if (cfg.ADD_ATTR) {
      if (typeof cfg.ADD_ATTR === 'function') {
        EXTRA_ELEMENT_HANDLING.attributeCheck = cfg.ADD_ATTR;
      } else {
        if (ALLOWED_ATTR === DEFAULT_ALLOWED_ATTR) {
          ALLOWED_ATTR = clone(ALLOWED_ATTR);
        }
        addToSet(ALLOWED_ATTR, cfg.ADD_ATTR, transformCaseFunc);
      }
    }
    if (cfg.ADD_URI_SAFE_ATTR) {
      addToSet(URI_SAFE_ATTRIBUTES, cfg.ADD_URI_SAFE_ATTR, transformCaseFunc);
    }
    if (cfg.FORBID_CONTENTS) {
      if (FORBID_CONTENTS === DEFAULT_FORBID_CONTENTS) {
        FORBID_CONTENTS = clone(FORBID_CONTENTS);
      }
      addToSet(FORBID_CONTENTS, cfg.FORBID_CONTENTS, transformCaseFunc);
    }
    if (cfg.ADD_FORBID_CONTENTS) {
      if (FORBID_CONTENTS === DEFAULT_FORBID_CONTENTS) {
        FORBID_CONTENTS = clone(FORBID_CONTENTS);
      }
      addToSet(FORBID_CONTENTS, cfg.ADD_FORBID_CONTENTS, transformCaseFunc);
    }
    /* Add #text in case KEEP_CONTENT is set to true */
    if (KEEP_CONTENT) {
      ALLOWED_TAGS['#text'] = true;
    }
    /* Add html, head and body to ALLOWED_TAGS in case WHOLE_DOCUMENT is true */
    if (WHOLE_DOCUMENT) {
      addToSet(ALLOWED_TAGS, ['html', 'head', 'body']);
    }
    /* Add tbody to ALLOWED_TAGS in case tables are permitted, see #286, #365 */
    if (ALLOWED_TAGS.table) {
      addToSet(ALLOWED_TAGS, ['tbody']);
      delete FORBID_TAGS.tbody;
    }
    if (cfg.TRUSTED_TYPES_POLICY) {
      if (typeof cfg.TRUSTED_TYPES_POLICY.createHTML !== 'function') {
        throw typeErrorCreate('TRUSTED_TYPES_POLICY configuration option must provide a "createHTML" hook.');
      }
      if (typeof cfg.TRUSTED_TYPES_POLICY.createScriptURL !== 'function') {
        throw typeErrorCreate('TRUSTED_TYPES_POLICY configuration option must provide a "createScriptURL" hook.');
      }
      // Overwrite existing TrustedTypes policy.
      trustedTypesPolicy = cfg.TRUSTED_TYPES_POLICY;
      // Sign local variables required by `sanitize`.
      emptyHTML = trustedTypesPolicy.createHTML('');
    } else {
      // Uninitialized policy, attempt to initialize the internal dompurify policy.
      if (trustedTypesPolicy === undefined) {
        trustedTypesPolicy = _createTrustedTypesPolicy(trustedTypes, currentScript);
      }
      // If creating the internal policy succeeded sign internal variables.
      if (trustedTypesPolicy !== null && typeof emptyHTML === 'string') {
        emptyHTML = trustedTypesPolicy.createHTML('');
      }
    }
    // Prevent further manipulation of configuration.
    // Not available in IE8, Safari 5, etc.
    if (freeze) {
      freeze(cfg);
    }
    CONFIG = cfg;
  };
  /* Keep track of all possible SVG and MathML tags
   * so that we can perform the namespace checks
   * correctly. */
  const ALL_SVG_TAGS = addToSet({}, [...svg$1, ...svgFilters, ...svgDisallowed]);
  const ALL_MATHML_TAGS = addToSet({}, [...mathMl$1, ...mathMlDisallowed]);
  /**
   * @param element a DOM element whose namespace is being checked
   * @returns Return false if the element has a
   *  namespace that a spec-compliant parser would never
   *  return. Return true otherwise.
   */
  const _checkValidNamespace = function _checkValidNamespace(element) {
    let parent = getParentNode(element);
    // In JSDOM, if we're inside shadow DOM, then parentNode
    // can be null. We just simulate parent in this case.
    if (!parent || !parent.tagName) {
      parent = {
        namespaceURI: NAMESPACE,
        tagName: 'template'
      };
    }
    const tagName = stringToLowerCase(element.tagName);
    const parentTagName = stringToLowerCase(parent.tagName);
    if (!ALLOWED_NAMESPACES[element.namespaceURI]) {
      return false;
    }
    if (element.namespaceURI === SVG_NAMESPACE) {
      // The only way to switch from HTML namespace to SVG
      // is via <svg>. If it happens via any other tag, then
      // it should be killed.
      if (parent.namespaceURI === HTML_NAMESPACE) {
        return tagName === 'svg';
      }
      // The only way to switch from MathML to SVG is via`
      // svg if parent is either <annotation-xml> or MathML
      // text integration points.
      if (parent.namespaceURI === MATHML_NAMESPACE) {
        return tagName === 'svg' && (parentTagName === 'annotation-xml' || MATHML_TEXT_INTEGRATION_POINTS[parentTagName]);
      }
      // We only allow elements that are defined in SVG
      // spec. All others are disallowed in SVG namespace.
      return Boolean(ALL_SVG_TAGS[tagName]);
    }
    if (element.namespaceURI === MATHML_NAMESPACE) {
      // The only way to switch from HTML namespace to MathML
      // is via <math>. If it happens via any other tag, then
      // it should be killed.
      if (parent.namespaceURI === HTML_NAMESPACE) {
        return tagName === 'math';
      }
      // The only way to switch from SVG to MathML is via
      // <math> and HTML integration points
      if (parent.namespaceURI === SVG_NAMESPACE) {
        return tagName === 'math' && HTML_INTEGRATION_POINTS[parentTagName];
      }
      // We only allow elements that are defined in MathML
      // spec. All others are disallowed in MathML namespace.
      return Boolean(ALL_MATHML_TAGS[tagName]);
    }
    if (element.namespaceURI === HTML_NAMESPACE) {
      // The only way to switch from SVG to HTML is via
      // HTML integration points, and from MathML to HTML
      // is via MathML text integration points
      if (parent.namespaceURI === SVG_NAMESPACE && !HTML_INTEGRATION_POINTS[parentTagName]) {
        return false;
      }
      if (parent.namespaceURI === MATHML_NAMESPACE && !MATHML_TEXT_INTEGRATION_POINTS[parentTagName]) {
        return false;
      }
      // We disallow tags that are specific for MathML
      // or SVG and should never appear in HTML namespace
      return !ALL_MATHML_TAGS[tagName] && (COMMON_SVG_AND_HTML_ELEMENTS[tagName] || !ALL_SVG_TAGS[tagName]);
    }
    // For XHTML and XML documents that support custom namespaces
    if (PARSER_MEDIA_TYPE === 'application/xhtml+xml' && ALLOWED_NAMESPACES[element.namespaceURI]) {
      return true;
    }
    // The code should never reach this place (this means
    // that the element somehow got namespace that is not
    // HTML, SVG, MathML or allowed via ALLOWED_NAMESPACES).
    // Return false just in case.
    return false;
  };
  /**
   * _forceRemove
   *
   * @param node a DOM node
   */
  const _forceRemove = function _forceRemove(node) {
    arrayPush(DOMPurify.removed, {
      element: node
    });
    try {
      // eslint-disable-next-line unicorn/prefer-dom-node-remove
      getParentNode(node).removeChild(node);
    } catch (_) {
      remove(node);
    }
  };
  /**
   * _removeAttribute
   *
   * @param name an Attribute name
   * @param element a DOM node
   */
  const _removeAttribute = function _removeAttribute(name, element) {
    try {
      arrayPush(DOMPurify.removed, {
        attribute: element.getAttributeNode(name),
        from: element
      });
    } catch (_) {
      arrayPush(DOMPurify.removed, {
        attribute: null,
        from: element
      });
    }
    element.removeAttribute(name);
    // We void attribute values for unremovable "is" attributes
    if (name === 'is') {
      if (RETURN_DOM || RETURN_DOM_FRAGMENT) {
        try {
          _forceRemove(element);
        } catch (_) {}
      } else {
        try {
          element.setAttribute(name, '');
        } catch (_) {}
      }
    }
  };
  /**
   * _initDocument
   *
   * @param dirty - a string of dirty markup
   * @return a DOM, filled with the dirty markup
   */
  const _initDocument = function _initDocument(dirty) {
    /* Create a HTML document */
    let doc = null;
    let leadingWhitespace = null;
    if (FORCE_BODY) {
      dirty = '<remove></remove>' + dirty;
    } else {
      /* If FORCE_BODY isn't used, leading whitespace needs to be preserved manually */
      const matches = stringMatch(dirty, /^[\r\n\t ]+/);
      leadingWhitespace = matches && matches[0];
    }
    if (PARSER_MEDIA_TYPE === 'application/xhtml+xml' && NAMESPACE === HTML_NAMESPACE) {
      // Root of XHTML doc must contain xmlns declaration (see https://www.w3.org/TR/xhtml1/normative.html#strict)
      dirty = '<html xmlns="http://www.w3.org/1999/xhtml"><head></head><body>' + dirty + '</body></html>';
    }
    const dirtyPayload = trustedTypesPolicy ? trustedTypesPolicy.createHTML(dirty) : dirty;
    /*
     * Use the DOMParser API by default, fallback later if needs be
     * DOMParser not work for svg when has multiple root element.
     */
    if (NAMESPACE === HTML_NAMESPACE) {
      try {
        doc = new DOMParser().parseFromString(dirtyPayload, PARSER_MEDIA_TYPE);
      } catch (_) {}
    }
    /* Use createHTMLDocument in case DOMParser is not available */
    if (!doc || !doc.documentElement) {
      doc = implementation.createDocument(NAMESPACE, 'template', null);
      try {
        doc.documentElement.innerHTML = IS_EMPTY_INPUT ? emptyHTML : dirtyPayload;
      } catch (_) {
        // Syntax error if dirtyPayload is invalid xml
      }
    }
    const body = doc.body || doc.documentElement;
    if (dirty && leadingWhitespace) {
      body.insertBefore(document.createTextNode(leadingWhitespace), body.childNodes[0] || null);
    }
    /* Work on whole document or just its body */
    if (NAMESPACE === HTML_NAMESPACE) {
      return getElementsByTagName.call(doc, WHOLE_DOCUMENT ? 'html' : 'body')[0];
    }
    return WHOLE_DOCUMENT ? doc.documentElement : body;
  };
  /**
   * Creates a NodeIterator object that you can use to traverse filtered lists of nodes or elements in a document.
   *
   * @param root The root element or node to start traversing on.
   * @return The created NodeIterator
   */
  const _createNodeIterator = function _createNodeIterator(root) {
    return createNodeIterator.call(root.ownerDocument || root, root,
    // eslint-disable-next-line no-bitwise
    NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_COMMENT | NodeFilter.SHOW_TEXT | NodeFilter.SHOW_PROCESSING_INSTRUCTION | NodeFilter.SHOW_CDATA_SECTION, null);
  };
  /**
   * _isClobbered
   *
   * @param element element to check for clobbering attacks
   * @return true if clobbered, false if safe
   */
  const _isClobbered = function _isClobbered(element) {
    return element instanceof HTMLFormElement && (typeof element.nodeName !== 'string' || typeof element.textContent !== 'string' || typeof element.removeChild !== 'function' || !(element.attributes instanceof NamedNodeMap) || typeof element.removeAttribute !== 'function' || typeof element.setAttribute !== 'function' || typeof element.namespaceURI !== 'string' || typeof element.insertBefore !== 'function' || typeof element.hasChildNodes !== 'function');
  };
  /**
   * Checks whether the given object is a DOM node.
   *
   * @param value object to check whether it's a DOM node
   * @return true is object is a DOM node
   */
  const _isNode = function _isNode(value) {
    return typeof Node === 'function' && value instanceof Node;
  };
  function _executeHooks(hooks, currentNode, data) {
    arrayForEach(hooks, hook => {
      hook.call(DOMPurify, currentNode, data, CONFIG);
    });
  }
  /**
   * _sanitizeElements
   *
   * @protect nodeName
   * @protect textContent
   * @protect removeChild
   * @param currentNode to check for permission to exist
   * @return true if node was killed, false if left alive
   */
  const _sanitizeElements = function _sanitizeElements(currentNode) {
    let content = null;
    /* Execute a hook if present */
    _executeHooks(hooks.beforeSanitizeElements, currentNode, null);
    /* Check if element is clobbered or can clobber */
    if (_isClobbered(currentNode)) {
      _forceRemove(currentNode);
      return true;
    }
    /* Now let's check the element's type and name */
    const tagName = transformCaseFunc(currentNode.nodeName);
    /* Execute a hook if present */
    _executeHooks(hooks.uponSanitizeElement, currentNode, {
      tagName,
      allowedTags: ALLOWED_TAGS
    });
    /* Detect mXSS attempts abusing namespace confusion */
    if (SAFE_FOR_XML && currentNode.hasChildNodes() && !_isNode(currentNode.firstElementChild) && regExpTest(/<[/\w!]/g, currentNode.innerHTML) && regExpTest(/<[/\w!]/g, currentNode.textContent)) {
      _forceRemove(currentNode);
      return true;
    }
    /* Remove any occurrence of processing instructions */
    if (currentNode.nodeType === NODE_TYPE.progressingInstruction) {
      _forceRemove(currentNode);
      return true;
    }
    /* Remove any kind of possibly harmful comments */
    if (SAFE_FOR_XML && currentNode.nodeType === NODE_TYPE.comment && regExpTest(/<[/\w]/g, currentNode.data)) {
      _forceRemove(currentNode);
      return true;
    }
    /* Remove element if anything forbids its presence */
    if (!(EXTRA_ELEMENT_HANDLING.tagCheck instanceof Function && EXTRA_ELEMENT_HANDLING.tagCheck(tagName)) && (!ALLOWED_TAGS[tagName] || FORBID_TAGS[tagName])) {
      /* Check if we have a custom element to handle */
      if (!FORBID_TAGS[tagName] && _isBasicCustomElement(tagName)) {
        if (CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof RegExp && regExpTest(CUSTOM_ELEMENT_HANDLING.tagNameCheck, tagName)) {
          return false;
        }
        if (CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof Function && CUSTOM_ELEMENT_HANDLING.tagNameCheck(tagName)) {
          return false;
        }
      }
      /* Keep content except for bad-listed elements */
      if (KEEP_CONTENT && !FORBID_CONTENTS[tagName]) {
        const parentNode = getParentNode(currentNode) || currentNode.parentNode;
        const childNodes = getChildNodes(currentNode) || currentNode.childNodes;
        if (childNodes && parentNode) {
          const childCount = childNodes.length;
          for (let i = childCount - 1; i >= 0; --i) {
            const childClone = cloneNode(childNodes[i], true);
            childClone.__removalCount = (currentNode.__removalCount || 0) + 1;
            parentNode.insertBefore(childClone, getNextSibling(currentNode));
          }
        }
      }
      _forceRemove(currentNode);
      return true;
    }
    /* Check whether element has a valid namespace */
    if (currentNode instanceof Element && !_checkValidNamespace(currentNode)) {
      _forceRemove(currentNode);
      return true;
    }
    /* Make sure that older browsers don't get fallback-tag mXSS */
    if ((tagName === 'noscript' || tagName === 'noembed' || tagName === 'noframes') && regExpTest(/<\/no(script|embed|frames)/i, currentNode.innerHTML)) {
      _forceRemove(currentNode);
      return true;
    }
    /* Sanitize element content to be template-safe */
    if (SAFE_FOR_TEMPLATES && currentNode.nodeType === NODE_TYPE.text) {
      /* Get the element's text content */
      content = currentNode.textContent;
      arrayForEach([MUSTACHE_EXPR, ERB_EXPR, TMPLIT_EXPR], expr => {
        content = stringReplace(content, expr, ' ');
      });
      if (currentNode.textContent !== content) {
        arrayPush(DOMPurify.removed, {
          element: currentNode.cloneNode()
        });
        currentNode.textContent = content;
      }
    }
    /* Execute a hook if present */
    _executeHooks(hooks.afterSanitizeElements, currentNode, null);
    return false;
  };
  /**
   * _isValidAttribute
   *
   * @param lcTag Lowercase tag name of containing element.
   * @param lcName Lowercase attribute name.
   * @param value Attribute value.
   * @return Returns true if `value` is valid, otherwise false.
   */
  // eslint-disable-next-line complexity
  const _isValidAttribute = function _isValidAttribute(lcTag, lcName, value) {
    /* FORBID_ATTR must always win, even if ADD_ATTR predicate would allow it */
    if (FORBID_ATTR[lcName]) {
      return false;
    }
    /* Make sure attribute cannot clobber */
    if (SANITIZE_DOM && (lcName === 'id' || lcName === 'name') && (value in document || value in formElement)) {
      return false;
    }
    /* Allow valid data-* attributes: At least one character after "-"
        (https://html.spec.whatwg.org/multipage/dom.html#embedding-custom-non-visible-data-with-the-data-*-attributes)
        XML-compatible (https://html.spec.whatwg.org/multipage/infrastructure.html#xml-compatible and http://www.w3.org/TR/xml/#d0e804)
        We don't need to check the value; it's always URI safe. */
    if (ALLOW_DATA_ATTR && !FORBID_ATTR[lcName] && regExpTest(DATA_ATTR, lcName)) ; else if (ALLOW_ARIA_ATTR && regExpTest(ARIA_ATTR, lcName)) ; else if (EXTRA_ELEMENT_HANDLING.attributeCheck instanceof Function && EXTRA_ELEMENT_HANDLING.attributeCheck(lcName, lcTag)) ; else if (!ALLOWED_ATTR[lcName] || FORBID_ATTR[lcName]) {
      if (
      // First condition does a very basic check if a) it's basically a valid custom element tagname AND
      // b) if the tagName passes whatever the user has configured for CUSTOM_ELEMENT_HANDLING.tagNameCheck
      // and c) if the attribute name passes whatever the user has configured for CUSTOM_ELEMENT_HANDLING.attributeNameCheck
      _isBasicCustomElement(lcTag) && (CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof RegExp && regExpTest(CUSTOM_ELEMENT_HANDLING.tagNameCheck, lcTag) || CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof Function && CUSTOM_ELEMENT_HANDLING.tagNameCheck(lcTag)) && (CUSTOM_ELEMENT_HANDLING.attributeNameCheck instanceof RegExp && regExpTest(CUSTOM_ELEMENT_HANDLING.attributeNameCheck, lcName) || CUSTOM_ELEMENT_HANDLING.attributeNameCheck instanceof Function && CUSTOM_ELEMENT_HANDLING.attributeNameCheck(lcName, lcTag)) ||
      // Alternative, second condition checks if it's an `is`-attribute, AND
      // the value passes whatever the user has configured for CUSTOM_ELEMENT_HANDLING.tagNameCheck
      lcName === 'is' && CUSTOM_ELEMENT_HANDLING.allowCustomizedBuiltInElements && (CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof RegExp && regExpTest(CUSTOM_ELEMENT_HANDLING.tagNameCheck, value) || CUSTOM_ELEMENT_HANDLING.tagNameCheck instanceof Function && CUSTOM_ELEMENT_HANDLING.tagNameCheck(value))) ; else {
        return false;
      }
      /* Check value is safe. First, is attr inert? If so, is safe */
    } else if (URI_SAFE_ATTRIBUTES[lcName]) ; else if (regExpTest(IS_ALLOWED_URI$1, stringReplace(value, ATTR_WHITESPACE, ''))) ; else if ((lcName === 'src' || lcName === 'xlink:href' || lcName === 'href') && lcTag !== 'script' && stringIndexOf(value, 'data:') === 0 && DATA_URI_TAGS[lcTag]) ; else if (ALLOW_UNKNOWN_PROTOCOLS && !regExpTest(IS_SCRIPT_OR_DATA, stringReplace(value, ATTR_WHITESPACE, ''))) ; else if (value) {
      return false;
    } else ;
    return true;
  };
  /**
   * _isBasicCustomElement
   * checks if at least one dash is included in tagName, and it's not the first char
   * for more sophisticated checking see https://github.com/sindresorhus/validate-element-name
   *
   * @param tagName name of the tag of the node to sanitize
   * @returns Returns true if the tag name meets the basic criteria for a custom element, otherwise false.
   */
  const _isBasicCustomElement = function _isBasicCustomElement(tagName) {
    return tagName !== 'annotation-xml' && stringMatch(tagName, CUSTOM_ELEMENT);
  };
  /**
   * _sanitizeAttributes
   *
   * @protect attributes
   * @protect nodeName
   * @protect removeAttribute
   * @protect setAttribute
   *
   * @param currentNode to sanitize
   */
  const _sanitizeAttributes = function _sanitizeAttributes(currentNode) {
    /* Execute a hook if present */
    _executeHooks(hooks.beforeSanitizeAttributes, currentNode, null);
    const {
      attributes
    } = currentNode;
    /* Check if we have attributes; if not we might have a text node */
    if (!attributes || _isClobbered(currentNode)) {
      return;
    }
    const hookEvent = {
      attrName: '',
      attrValue: '',
      keepAttr: true,
      allowedAttributes: ALLOWED_ATTR,
      forceKeepAttr: undefined
    };
    let l = attributes.length;
    /* Go backwards over all attributes; safely remove bad ones */
    while (l--) {
      const attr = attributes[l];
      const {
        name,
        namespaceURI,
        value: attrValue
      } = attr;
      const lcName = transformCaseFunc(name);
      const initValue = attrValue;
      let value = name === 'value' ? initValue : stringTrim(initValue);
      /* Execute a hook if present */
      hookEvent.attrName = lcName;
      hookEvent.attrValue = value;
      hookEvent.keepAttr = true;
      hookEvent.forceKeepAttr = undefined; // Allows developers to see this is a property they can set
      _executeHooks(hooks.uponSanitizeAttribute, currentNode, hookEvent);
      value = hookEvent.attrValue;
      /* Full DOM Clobbering protection via namespace isolation,
       * Prefix id and name attributes with `user-content-`
       */
      if (SANITIZE_NAMED_PROPS && (lcName === 'id' || lcName === 'name')) {
        // Remove the attribute with this value
        _removeAttribute(name, currentNode);
        // Prefix the value and later re-create the attribute with the sanitized value
        value = SANITIZE_NAMED_PROPS_PREFIX + value;
      }
      /* Work around a security issue with comments inside attributes */
      if (SAFE_FOR_XML && regExpTest(/((--!?|])>)|<\/(style|script|title|xmp|textarea|noscript|iframe|noembed|noframes)/i, value)) {
        _removeAttribute(name, currentNode);
        continue;
      }
      /* Make sure we cannot easily use animated hrefs, even if animations are allowed */
      if (lcName === 'attributename' && stringMatch(value, 'href')) {
        _removeAttribute(name, currentNode);
        continue;
      }
      /* Did the hooks approve of the attribute? */
      if (hookEvent.forceKeepAttr) {
        continue;
      }
      /* Did the hooks approve of the attribute? */
      if (!hookEvent.keepAttr) {
        _removeAttribute(name, currentNode);
        continue;
      }
      /* Work around a security issue in jQuery 3.0 */
      if (!ALLOW_SELF_CLOSE_IN_ATTR && regExpTest(/\/>/i, value)) {
        _removeAttribute(name, currentNode);
        continue;
      }
      /* Sanitize attribute content to be template-safe */
      if (SAFE_FOR_TEMPLATES) {
        arrayForEach([MUSTACHE_EXPR, ERB_EXPR, TMPLIT_EXPR], expr => {
          value = stringReplace(value, expr, ' ');
        });
      }
      /* Is `value` valid for this attribute? */
      const lcTag = transformCaseFunc(currentNode.nodeName);
      if (!_isValidAttribute(lcTag, lcName, value)) {
        _removeAttribute(name, currentNode);
        continue;
      }
      /* Handle attributes that require Trusted Types */
      if (trustedTypesPolicy && typeof trustedTypes === 'object' && typeof trustedTypes.getAttributeType === 'function') {
        if (namespaceURI) ; else {
          switch (trustedTypes.getAttributeType(lcTag, lcName)) {
            case 'TrustedHTML':
              {
                value = trustedTypesPolicy.createHTML(value);
                break;
              }
            case 'TrustedScriptURL':
              {
                value = trustedTypesPolicy.createScriptURL(value);
                break;
              }
          }
        }
      }
      /* Handle invalid data-* attribute set by try-catching it */
      if (value !== initValue) {
        try {
          if (namespaceURI) {
            currentNode.setAttributeNS(namespaceURI, name, value);
          } else {
            /* Fallback to setAttribute() for browser-unrecognized namespaces e.g. "x-schema". */
            currentNode.setAttribute(name, value);
          }
          if (_isClobbered(currentNode)) {
            _forceRemove(currentNode);
          } else {
            arrayPop(DOMPurify.removed);
          }
        } catch (_) {
          _removeAttribute(name, currentNode);
        }
      }
    }
    /* Execute a hook if present */
    _executeHooks(hooks.afterSanitizeAttributes, currentNode, null);
  };
  /**
   * _sanitizeShadowDOM
   *
   * @param fragment to iterate over recursively
   */
  const _sanitizeShadowDOM = function _sanitizeShadowDOM(fragment) {
    let shadowNode = null;
    const shadowIterator = _createNodeIterator(fragment);
    /* Execute a hook if present */
    _executeHooks(hooks.beforeSanitizeShadowDOM, fragment, null);
    while (shadowNode = shadowIterator.nextNode()) {
      /* Execute a hook if present */
      _executeHooks(hooks.uponSanitizeShadowNode, shadowNode, null);
      /* Sanitize tags and elements */
      _sanitizeElements(shadowNode);
      /* Check attributes next */
      _sanitizeAttributes(shadowNode);
      /* Deep shadow DOM detected */
      if (shadowNode.content instanceof DocumentFragment) {
        _sanitizeShadowDOM(shadowNode.content);
      }
    }
    /* Execute a hook if present */
    _executeHooks(hooks.afterSanitizeShadowDOM, fragment, null);
  };
  // eslint-disable-next-line complexity
  DOMPurify.sanitize = function (dirty) {
    let cfg = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
    let body = null;
    let importedNode = null;
    let currentNode = null;
    let returnNode = null;
    /* Make sure we have a string to sanitize.
      DO NOT return early, as this will return the wrong type if
      the user has requested a DOM object rather than a string */
    IS_EMPTY_INPUT = !dirty;
    if (IS_EMPTY_INPUT) {
      dirty = '<!-->';
    }
    /* Stringify, in case dirty is an object */
    if (typeof dirty !== 'string' && !_isNode(dirty)) {
      if (typeof dirty.toString === 'function') {
        dirty = dirty.toString();
        if (typeof dirty !== 'string') {
          throw typeErrorCreate('dirty is not a string, aborting');
        }
      } else {
        throw typeErrorCreate('toString is not a function');
      }
    }
    /* Return dirty HTML if DOMPurify cannot run */
    if (!DOMPurify.isSupported) {
      return dirty;
    }
    /* Assign config vars */
    if (!SET_CONFIG) {
      _parseConfig(cfg);
    }
    /* Clean up removed elements */
    DOMPurify.removed = [];
    /* Check if dirty is correctly typed for IN_PLACE */
    if (typeof dirty === 'string') {
      IN_PLACE = false;
    }
    if (IN_PLACE) {
      /* Do some early pre-sanitization to avoid unsafe root nodes */
      if (dirty.nodeName) {
        const tagName = transformCaseFunc(dirty.nodeName);
        if (!ALLOWED_TAGS[tagName] || FORBID_TAGS[tagName]) {
          throw typeErrorCreate('root node is forbidden and cannot be sanitized in-place');
        }
      }
    } else if (dirty instanceof Node) {
      /* If dirty is a DOM element, append to an empty document to avoid
         elements being stripped by the parser */
      body = _initDocument('<!---->');
      importedNode = body.ownerDocument.importNode(dirty, true);
      if (importedNode.nodeType === NODE_TYPE.element && importedNode.nodeName === 'BODY') {
        /* Node is already a body, use as is */
        body = importedNode;
      } else if (importedNode.nodeName === 'HTML') {
        body = importedNode;
      } else {
        // eslint-disable-next-line unicorn/prefer-dom-node-append
        body.appendChild(importedNode);
      }
    } else {
      /* Exit directly if we have nothing to do */
      if (!RETURN_DOM && !SAFE_FOR_TEMPLATES && !WHOLE_DOCUMENT &&
      // eslint-disable-next-line unicorn/prefer-includes
      dirty.indexOf('<') === -1) {
        return trustedTypesPolicy && RETURN_TRUSTED_TYPE ? trustedTypesPolicy.createHTML(dirty) : dirty;
      }
      /* Initialize the document to work on */
      body = _initDocument(dirty);
      /* Check we have a DOM node from the data */
      if (!body) {
        return RETURN_DOM ? null : RETURN_TRUSTED_TYPE ? emptyHTML : '';
      }
    }
    /* Remove first element node (ours) if FORCE_BODY is set */
    if (body && FORCE_BODY) {
      _forceRemove(body.firstChild);
    }
    /* Get node iterator */
    const nodeIterator = _createNodeIterator(IN_PLACE ? dirty : body);
    /* Now start iterating over the created document */
    while (currentNode = nodeIterator.nextNode()) {
      /* Sanitize tags and elements */
      _sanitizeElements(currentNode);
      /* Check attributes next */
      _sanitizeAttributes(currentNode);
      /* Shadow DOM detected, sanitize it */
      if (currentNode.content instanceof DocumentFragment) {
        _sanitizeShadowDOM(currentNode.content);
      }
    }
    /* If we sanitized `dirty` in-place, return it. */
    if (IN_PLACE) {
      return dirty;
    }
    /* Return sanitized string or DOM */
    if (RETURN_DOM) {
      if (RETURN_DOM_FRAGMENT) {
        returnNode = createDocumentFragment.call(body.ownerDocument);
        while (body.firstChild) {
          // eslint-disable-next-line unicorn/prefer-dom-node-append
          returnNode.appendChild(body.firstChild);
        }
      } else {
        returnNode = body;
      }
      if (ALLOWED_ATTR.shadowroot || ALLOWED_ATTR.shadowrootmode) {
        /*
          AdoptNode() is not used because internal state is not reset
          (e.g. the past names map of a HTMLFormElement), this is safe
          in theory but we would rather not risk another attack vector.
          The state that is cloned by importNode() is explicitly defined
          by the specs.
        */
        returnNode = importNode.call(originalDocument, returnNode, true);
      }
      return returnNode;
    }
    let serializedHTML = WHOLE_DOCUMENT ? body.outerHTML : body.innerHTML;
    /* Serialize doctype if allowed */
    if (WHOLE_DOCUMENT && ALLOWED_TAGS['!doctype'] && body.ownerDocument && body.ownerDocument.doctype && body.ownerDocument.doctype.name && regExpTest(DOCTYPE_NAME, body.ownerDocument.doctype.name)) {
      serializedHTML = '<!DOCTYPE ' + body.ownerDocument.doctype.name + '>\n' + serializedHTML;
    }
    /* Sanitize final string template-safe */
    if (SAFE_FOR_TEMPLATES) {
      arrayForEach([MUSTACHE_EXPR, ERB_EXPR, TMPLIT_EXPR], expr => {
        serializedHTML = stringReplace(serializedHTML, expr, ' ');
      });
    }
    return trustedTypesPolicy && RETURN_TRUSTED_TYPE ? trustedTypesPolicy.createHTML(serializedHTML) : serializedHTML;
  };
  DOMPurify.setConfig = function () {
    let cfg = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    _parseConfig(cfg);
    SET_CONFIG = true;
  };
  DOMPurify.clearConfig = function () {
    CONFIG = null;
    SET_CONFIG = false;
  };
  DOMPurify.isValidAttribute = function (tag, attr, value) {
    /* Initialize shared config vars if necessary. */
    if (!CONFIG) {
      _parseConfig({});
    }
    const lcTag = transformCaseFunc(tag);
    const lcName = transformCaseFunc(attr);
    return _isValidAttribute(lcTag, lcName, value);
  };
  DOMPurify.addHook = function (entryPoint, hookFunction) {
    if (typeof hookFunction !== 'function') {
      return;
    }
    arrayPush(hooks[entryPoint], hookFunction);
  };
  DOMPurify.removeHook = function (entryPoint, hookFunction) {
    if (hookFunction !== undefined) {
      const index = arrayLastIndexOf(hooks[entryPoint], hookFunction);
      return index === -1 ? undefined : arraySplice(hooks[entryPoint], index, 1)[0];
    }
    return arrayPop(hooks[entryPoint]);
  };
  DOMPurify.removeHooks = function (entryPoint) {
    hooks[entryPoint] = [];
  };
  DOMPurify.removeAllHooks = function () {
    hooks = _createHooksMap();
  };
  return DOMPurify;
}
var purify = createDOMPurify();


//# sourceMappingURL=purify.es.mjs.map


/***/ },

/***/ "./node_modules/immer/dist/immer.mjs"
/*!*******************************************!*\
  !*** ./node_modules/immer/dist/immer.mjs ***!
  \*******************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Immer: () => (/* binding */ Immer2),
/* harmony export */   applyPatches: () => (/* binding */ applyPatches),
/* harmony export */   castDraft: () => (/* binding */ castDraft),
/* harmony export */   castImmutable: () => (/* binding */ castImmutable),
/* harmony export */   createDraft: () => (/* binding */ createDraft),
/* harmony export */   current: () => (/* binding */ current),
/* harmony export */   enableArrayMethods: () => (/* binding */ enableArrayMethods),
/* harmony export */   enableMapSet: () => (/* binding */ enableMapSet),
/* harmony export */   enablePatches: () => (/* binding */ enablePatches),
/* harmony export */   finishDraft: () => (/* binding */ finishDraft),
/* harmony export */   freeze: () => (/* binding */ freeze),
/* harmony export */   immerable: () => (/* binding */ DRAFTABLE),
/* harmony export */   isDraft: () => (/* binding */ isDraft),
/* harmony export */   isDraftable: () => (/* binding */ isDraftable),
/* harmony export */   nothing: () => (/* binding */ NOTHING),
/* harmony export */   original: () => (/* binding */ original),
/* harmony export */   produce: () => (/* binding */ produce),
/* harmony export */   produceWithPatches: () => (/* binding */ produceWithPatches),
/* harmony export */   setAutoFreeze: () => (/* binding */ setAutoFreeze),
/* harmony export */   setUseStrictIteration: () => (/* binding */ setUseStrictIteration),
/* harmony export */   setUseStrictShallowCopy: () => (/* binding */ setUseStrictShallowCopy)
/* harmony export */ });
// src/utils/env.ts
var NOTHING = Symbol.for("immer-nothing");
var DRAFTABLE = Symbol.for("immer-draftable");
var DRAFT_STATE = Symbol.for("immer-state");

// src/utils/errors.ts
var errors =  true ? [
  // All error codes, starting by 0:
  function(plugin) {
    return `The plugin for '${plugin}' has not been loaded into Immer. To enable the plugin, import and call \`enable${plugin}()\` when initializing your application.`;
  },
  function(thing) {
    return `produce can only be called on things that are draftable: plain objects, arrays, Map, Set or classes that are marked with '[immerable]: true'. Got '${thing}'`;
  },
  "This object has been frozen and should not be mutated",
  function(data) {
    return "Cannot use a proxy that has been revoked. Did you pass an object from inside an immer function to an async process? " + data;
  },
  "An immer producer returned a new value *and* modified its draft. Either return a new value *or* modify the draft.",
  "Immer forbids circular references",
  "The first or second argument to `produce` must be a function",
  "The third argument to `produce` must be a function or undefined",
  "First argument to `createDraft` must be a plain object, an array, or an immerable object",
  "First argument to `finishDraft` must be a draft returned by `createDraft`",
  function(thing) {
    return `'current' expects a draft, got: ${thing}`;
  },
  "Object.defineProperty() cannot be used on an Immer draft",
  "Object.setPrototypeOf() cannot be used on an Immer draft",
  "Immer only supports deleting array indices",
  "Immer only supports setting array indices and the 'length' property",
  function(thing) {
    return `'original' expects a draft, got: ${thing}`;
  }
  // Note: if more errors are added, the errorOffset in Patches.ts should be increased
  // See Patches.ts for additional errors
] : 0;
function die(error, ...args) {
  if (true) {
    const e = errors[error];
    const msg = isFunction(e) ? e.apply(null, args) : e;
    throw new Error(`[Immer] ${msg}`);
  }
  // removed by dead control flow

}

// src/utils/common.ts
var O = Object;
var getPrototypeOf = O.getPrototypeOf;
var CONSTRUCTOR = "constructor";
var PROTOTYPE = "prototype";
var CONFIGURABLE = "configurable";
var ENUMERABLE = "enumerable";
var WRITABLE = "writable";
var VALUE = "value";
var isDraft = (value) => !!value && !!value[DRAFT_STATE];
function isDraftable(value) {
  if (!value)
    return false;
  return isPlainObject(value) || isArray(value) || !!value[DRAFTABLE] || !!value[CONSTRUCTOR]?.[DRAFTABLE] || isMap(value) || isSet(value);
}
var objectCtorString = O[PROTOTYPE][CONSTRUCTOR].toString();
var cachedCtorStrings = /* @__PURE__ */ new WeakMap();
function isPlainObject(value) {
  if (!value || !isObjectish(value))
    return false;
  const proto = getPrototypeOf(value);
  if (proto === null || proto === O[PROTOTYPE])
    return true;
  const Ctor = O.hasOwnProperty.call(proto, CONSTRUCTOR) && proto[CONSTRUCTOR];
  if (Ctor === Object)
    return true;
  if (!isFunction(Ctor))
    return false;
  let ctorString = cachedCtorStrings.get(Ctor);
  if (ctorString === void 0) {
    ctorString = Function.toString.call(Ctor);
    cachedCtorStrings.set(Ctor, ctorString);
  }
  return ctorString === objectCtorString;
}
function original(value) {
  if (!isDraft(value))
    die(15, value);
  return value[DRAFT_STATE].base_;
}
function each(obj, iter, strict = true) {
  if (getArchtype(obj) === 0 /* Object */) {
    const keys = strict ? Reflect.ownKeys(obj) : O.keys(obj);
    keys.forEach((key) => {
      iter(key, obj[key], obj);
    });
  } else {
    obj.forEach((entry, index) => iter(index, entry, obj));
  }
}
function getArchtype(thing) {
  const state = thing[DRAFT_STATE];
  return state ? state.type_ : isArray(thing) ? 1 /* Array */ : isMap(thing) ? 2 /* Map */ : isSet(thing) ? 3 /* Set */ : 0 /* Object */;
}
var has = (thing, prop, type = getArchtype(thing)) => type === 2 /* Map */ ? thing.has(prop) : O[PROTOTYPE].hasOwnProperty.call(thing, prop);
var get = (thing, prop, type = getArchtype(thing)) => (
  // @ts-ignore
  type === 2 /* Map */ ? thing.get(prop) : thing[prop]
);
var set = (thing, propOrOldValue, value, type = getArchtype(thing)) => {
  if (type === 2 /* Map */)
    thing.set(propOrOldValue, value);
  else if (type === 3 /* Set */) {
    thing.add(value);
  } else
    thing[propOrOldValue] = value;
};
function is(x, y) {
  if (x === y) {
    return x !== 0 || 1 / x === 1 / y;
  } else {
    return x !== x && y !== y;
  }
}
var isArray = Array.isArray;
var isMap = (target) => target instanceof Map;
var isSet = (target) => target instanceof Set;
var isObjectish = (target) => typeof target === "object";
var isFunction = (target) => typeof target === "function";
var isBoolean = (target) => typeof target === "boolean";
function isArrayIndex(value) {
  const n = +value;
  return Number.isInteger(n) && String(n) === value;
}
var getProxyDraft = (value) => {
  if (!isObjectish(value))
    return null;
  return value?.[DRAFT_STATE];
};
var latest = (state) => state.copy_ || state.base_;
var getValue = (value) => {
  const proxyDraft = getProxyDraft(value);
  return proxyDraft ? proxyDraft.copy_ ?? proxyDraft.base_ : value;
};
var getFinalValue = (state) => state.modified_ ? state.copy_ : state.base_;
function shallowCopy(base, strict) {
  if (isMap(base)) {
    return new Map(base);
  }
  if (isSet(base)) {
    return new Set(base);
  }
  if (isArray(base))
    return Array[PROTOTYPE].slice.call(base);
  const isPlain = isPlainObject(base);
  if (strict === true || strict === "class_only" && !isPlain) {
    const descriptors = O.getOwnPropertyDescriptors(base);
    delete descriptors[DRAFT_STATE];
    let keys = Reflect.ownKeys(descriptors);
    for (let i = 0; i < keys.length; i++) {
      const key = keys[i];
      const desc = descriptors[key];
      if (desc[WRITABLE] === false) {
        desc[WRITABLE] = true;
        desc[CONFIGURABLE] = true;
      }
      if (desc.get || desc.set)
        descriptors[key] = {
          [CONFIGURABLE]: true,
          [WRITABLE]: true,
          // could live with !!desc.set as well here...
          [ENUMERABLE]: desc[ENUMERABLE],
          [VALUE]: base[key]
        };
    }
    return O.create(getPrototypeOf(base), descriptors);
  } else {
    const proto = getPrototypeOf(base);
    if (proto !== null && isPlain) {
      return { ...base };
    }
    const obj = O.create(proto);
    return O.assign(obj, base);
  }
}
function freeze(obj, deep = false) {
  if (isFrozen(obj) || isDraft(obj) || !isDraftable(obj))
    return obj;
  if (getArchtype(obj) > 1) {
    O.defineProperties(obj, {
      set: dontMutateMethodOverride,
      add: dontMutateMethodOverride,
      clear: dontMutateMethodOverride,
      delete: dontMutateMethodOverride
    });
  }
  O.freeze(obj);
  if (deep)
    each(
      obj,
      (_key, value) => {
        freeze(value, true);
      },
      false
    );
  return obj;
}
function dontMutateFrozenCollections() {
  die(2);
}
var dontMutateMethodOverride = {
  [VALUE]: dontMutateFrozenCollections
};
function isFrozen(obj) {
  if (obj === null || !isObjectish(obj))
    return true;
  return O.isFrozen(obj);
}

// src/utils/plugins.ts
var PluginMapSet = "MapSet";
var PluginPatches = "Patches";
var PluginArrayMethods = "ArrayMethods";
var plugins = {};
function getPlugin(pluginKey) {
  const plugin = plugins[pluginKey];
  if (!plugin) {
    die(0, pluginKey);
  }
  return plugin;
}
var isPluginLoaded = (pluginKey) => !!plugins[pluginKey];
function loadPlugin(pluginKey, implementation) {
  if (!plugins[pluginKey])
    plugins[pluginKey] = implementation;
}

// src/core/scope.ts
var currentScope;
var getCurrentScope = () => currentScope;
var createScope = (parent_, immer_) => ({
  drafts_: [],
  parent_,
  immer_,
  // Whenever the modified draft contains a draft from another scope, we
  // need to prevent auto-freezing so the unowned draft can be finalized.
  canAutoFreeze_: true,
  unfinalizedDrafts_: 0,
  handledSet_: /* @__PURE__ */ new Set(),
  processedForPatches_: /* @__PURE__ */ new Set(),
  mapSetPlugin_: isPluginLoaded(PluginMapSet) ? getPlugin(PluginMapSet) : void 0,
  arrayMethodsPlugin_: isPluginLoaded(PluginArrayMethods) ? getPlugin(PluginArrayMethods) : void 0
});
function usePatchesInScope(scope, patchListener) {
  if (patchListener) {
    scope.patchPlugin_ = getPlugin(PluginPatches);
    scope.patches_ = [];
    scope.inversePatches_ = [];
    scope.patchListener_ = patchListener;
  }
}
function revokeScope(scope) {
  leaveScope(scope);
  scope.drafts_.forEach(revokeDraft);
  scope.drafts_ = null;
}
function leaveScope(scope) {
  if (scope === currentScope) {
    currentScope = scope.parent_;
  }
}
var enterScope = (immer2) => currentScope = createScope(currentScope, immer2);
function revokeDraft(draft) {
  const state = draft[DRAFT_STATE];
  if (state.type_ === 0 /* Object */ || state.type_ === 1 /* Array */)
    state.revoke_();
  else
    state.revoked_ = true;
}

// src/core/finalize.ts
function processResult(result, scope) {
  scope.unfinalizedDrafts_ = scope.drafts_.length;
  const baseDraft = scope.drafts_[0];
  const isReplaced = result !== void 0 && result !== baseDraft;
  if (isReplaced) {
    if (baseDraft[DRAFT_STATE].modified_) {
      revokeScope(scope);
      die(4);
    }
    if (isDraftable(result)) {
      result = finalize(scope, result);
    }
    const { patchPlugin_ } = scope;
    if (patchPlugin_) {
      patchPlugin_.generateReplacementPatches_(
        baseDraft[DRAFT_STATE].base_,
        result,
        scope
      );
    }
  } else {
    result = finalize(scope, baseDraft);
  }
  maybeFreeze(scope, result, true);
  revokeScope(scope);
  if (scope.patches_) {
    scope.patchListener_(scope.patches_, scope.inversePatches_);
  }
  return result !== NOTHING ? result : void 0;
}
function finalize(rootScope, value) {
  if (isFrozen(value))
    return value;
  const state = value[DRAFT_STATE];
  if (!state) {
    const finalValue = handleValue(value, rootScope.handledSet_, rootScope);
    return finalValue;
  }
  if (!isSameScope(state, rootScope)) {
    return value;
  }
  if (!state.modified_) {
    return state.base_;
  }
  if (!state.finalized_) {
    const { callbacks_ } = state;
    if (callbacks_) {
      while (callbacks_.length > 0) {
        const callback = callbacks_.pop();
        callback(rootScope);
      }
    }
    generatePatchesAndFinalize(state, rootScope);
  }
  return state.copy_;
}
function maybeFreeze(scope, value, deep = false) {
  if (!scope.parent_ && scope.immer_.autoFreeze_ && scope.canAutoFreeze_) {
    freeze(value, deep);
  }
}
function markStateFinalized(state) {
  state.finalized_ = true;
  state.scope_.unfinalizedDrafts_--;
}
var isSameScope = (state, rootScope) => state.scope_ === rootScope;
var EMPTY_LOCATIONS_RESULT = [];
function updateDraftInParent(parent, draftValue, finalizedValue, originalKey) {
  const parentCopy = latest(parent);
  const parentType = parent.type_;
  if (originalKey !== void 0) {
    const currentValue = get(parentCopy, originalKey, parentType);
    if (currentValue === draftValue) {
      set(parentCopy, originalKey, finalizedValue, parentType);
      return;
    }
  }
  if (!parent.draftLocations_) {
    const draftLocations = parent.draftLocations_ = /* @__PURE__ */ new Map();
    each(parentCopy, (key, value) => {
      if (isDraft(value)) {
        const keys = draftLocations.get(value) || [];
        keys.push(key);
        draftLocations.set(value, keys);
      }
    });
  }
  const locations = parent.draftLocations_.get(draftValue) ?? EMPTY_LOCATIONS_RESULT;
  for (const location of locations) {
    set(parentCopy, location, finalizedValue, parentType);
  }
}
function registerChildFinalizationCallback(parent, child, key) {
  parent.callbacks_.push(function childCleanup(rootScope) {
    const state = child;
    if (!state || !isSameScope(state, rootScope)) {
      return;
    }
    rootScope.mapSetPlugin_?.fixSetContents(state);
    const finalizedValue = getFinalValue(state);
    updateDraftInParent(parent, state.draft_ ?? state, finalizedValue, key);
    generatePatchesAndFinalize(state, rootScope);
  });
}
function generatePatchesAndFinalize(state, rootScope) {
  const shouldFinalize = state.modified_ && !state.finalized_ && (state.type_ === 3 /* Set */ || state.type_ === 1 /* Array */ && state.allIndicesReassigned_ || (state.assigned_?.size ?? 0) > 0);
  if (shouldFinalize) {
    const { patchPlugin_ } = rootScope;
    if (patchPlugin_) {
      const basePath = patchPlugin_.getPath(state);
      if (basePath) {
        patchPlugin_.generatePatches_(state, basePath, rootScope);
      }
    }
    markStateFinalized(state);
  }
}
function handleCrossReference(target, key, value) {
  const { scope_ } = target;
  if (isDraft(value)) {
    const state = value[DRAFT_STATE];
    if (isSameScope(state, scope_)) {
      state.callbacks_.push(function crossReferenceCleanup() {
        prepareCopy(target);
        const finalizedValue = getFinalValue(state);
        updateDraftInParent(target, value, finalizedValue, key);
      });
    }
  } else if (isDraftable(value)) {
    target.callbacks_.push(function nestedDraftCleanup() {
      const targetCopy = latest(target);
      if (target.type_ === 3 /* Set */) {
        if (targetCopy.has(value)) {
          handleValue(value, scope_.handledSet_, scope_);
        }
      } else {
        if (get(targetCopy, key, target.type_) === value) {
          if (scope_.drafts_.length > 1 && (target.assigned_.get(key) ?? false) === true && target.copy_) {
            handleValue(
              get(target.copy_, key, target.type_),
              scope_.handledSet_,
              scope_
            );
          }
        }
      }
    });
  }
}
function handleValue(target, handledSet, rootScope) {
  if (!rootScope.immer_.autoFreeze_ && rootScope.unfinalizedDrafts_ < 1) {
    return target;
  }
  if (isDraft(target) || handledSet.has(target) || !isDraftable(target) || isFrozen(target)) {
    return target;
  }
  handledSet.add(target);
  each(target, (key, value) => {
    if (isDraft(value)) {
      const state = value[DRAFT_STATE];
      if (isSameScope(state, rootScope)) {
        const updatedValue = getFinalValue(state);
        set(target, key, updatedValue, target.type_);
        markStateFinalized(state);
      }
    } else if (isDraftable(value)) {
      handleValue(value, handledSet, rootScope);
    }
  });
  return target;
}

// src/core/proxy.ts
function createProxyProxy(base, parent) {
  const baseIsArray = isArray(base);
  const state = {
    type_: baseIsArray ? 1 /* Array */ : 0 /* Object */,
    // Track which produce call this is associated with.
    scope_: parent ? parent.scope_ : getCurrentScope(),
    // True for both shallow and deep changes.
    modified_: false,
    // Used during finalization.
    finalized_: false,
    // Track which properties have been assigned (true) or deleted (false).
    // actually instantiated in `prepareCopy()`
    assigned_: void 0,
    // The parent draft state.
    parent_: parent,
    // The base state.
    base_: base,
    // The base proxy.
    draft_: null,
    // set below
    // The base copy with any updated values.
    copy_: null,
    // Called by the `produce` function.
    revoke_: null,
    isManual_: false,
    // `callbacks` actually gets assigned in `createProxy`
    callbacks_: void 0
  };
  let target = state;
  let traps = objectTraps;
  if (baseIsArray) {
    target = [state];
    traps = arrayTraps;
  }
  const { revoke, proxy } = Proxy.revocable(target, traps);
  state.draft_ = proxy;
  state.revoke_ = revoke;
  return [proxy, state];
}
var objectTraps = {
  get(state, prop) {
    if (prop === DRAFT_STATE)
      return state;
    let arrayPlugin = state.scope_.arrayMethodsPlugin_;
    const isArrayWithStringProp = state.type_ === 1 /* Array */ && typeof prop === "string";
    if (isArrayWithStringProp) {
      if (arrayPlugin?.isArrayOperationMethod(prop)) {
        return arrayPlugin.createMethodInterceptor(state, prop);
      }
    }
    const source = latest(state);
    if (!has(source, prop, state.type_)) {
      return readPropFromProto(state, source, prop);
    }
    const value = source[prop];
    if (state.finalized_ || !isDraftable(value)) {
      return value;
    }
    if (isArrayWithStringProp && state.operationMethod && arrayPlugin?.isMutatingArrayMethod(
      state.operationMethod
    ) && isArrayIndex(prop)) {
      return value;
    }
    if (value === peek(state.base_, prop)) {
      prepareCopy(state);
      const childKey = state.type_ === 1 /* Array */ ? +prop : prop;
      const childDraft = createProxy(state.scope_, value, state, childKey);
      return state.copy_[childKey] = childDraft;
    }
    return value;
  },
  has(state, prop) {
    return prop in latest(state);
  },
  ownKeys(state) {
    return Reflect.ownKeys(latest(state));
  },
  set(state, prop, value) {
    const desc = getDescriptorFromProto(latest(state), prop);
    if (desc?.set) {
      desc.set.call(state.draft_, value);
      return true;
    }
    if (!state.modified_) {
      const current2 = peek(latest(state), prop);
      const currentState = current2?.[DRAFT_STATE];
      if (currentState && currentState.base_ === value) {
        state.copy_[prop] = value;
        state.assigned_.set(prop, false);
        return true;
      }
      if (is(value, current2) && (value !== void 0 || has(state.base_, prop, state.type_)))
        return true;
      prepareCopy(state);
      markChanged(state);
    }
    if (state.copy_[prop] === value && // special case: handle new props with value 'undefined'
    (value !== void 0 || prop in state.copy_) || // special case: NaN
    Number.isNaN(value) && Number.isNaN(state.copy_[prop]))
      return true;
    state.copy_[prop] = value;
    state.assigned_.set(prop, true);
    handleCrossReference(state, prop, value);
    return true;
  },
  deleteProperty(state, prop) {
    prepareCopy(state);
    if (peek(state.base_, prop) !== void 0 || prop in state.base_) {
      state.assigned_.set(prop, false);
      markChanged(state);
    } else {
      state.assigned_.delete(prop);
    }
    if (state.copy_) {
      delete state.copy_[prop];
    }
    return true;
  },
  // Note: We never coerce `desc.value` into an Immer draft, because we can't make
  // the same guarantee in ES5 mode.
  getOwnPropertyDescriptor(state, prop) {
    const owner = latest(state);
    const desc = Reflect.getOwnPropertyDescriptor(owner, prop);
    if (!desc)
      return desc;
    return {
      [WRITABLE]: true,
      [CONFIGURABLE]: state.type_ !== 1 /* Array */ || prop !== "length",
      [ENUMERABLE]: desc[ENUMERABLE],
      [VALUE]: owner[prop]
    };
  },
  defineProperty() {
    die(11);
  },
  getPrototypeOf(state) {
    return getPrototypeOf(state.base_);
  },
  setPrototypeOf() {
    die(12);
  }
};
var arrayTraps = {};
for (let key in objectTraps) {
  let fn = objectTraps[key];
  arrayTraps[key] = function() {
    const args = arguments;
    args[0] = args[0][0];
    return fn.apply(this, args);
  };
}
arrayTraps.deleteProperty = function(state, prop) {
  if ( true && isNaN(parseInt(prop)))
    die(13);
  return arrayTraps.set.call(this, state, prop, void 0);
};
arrayTraps.set = function(state, prop, value) {
  if ( true && prop !== "length" && isNaN(parseInt(prop)))
    die(14);
  return objectTraps.set.call(this, state[0], prop, value, state[0]);
};
function peek(draft, prop) {
  const state = draft[DRAFT_STATE];
  const source = state ? latest(state) : draft;
  return source[prop];
}
function readPropFromProto(state, source, prop) {
  const desc = getDescriptorFromProto(source, prop);
  return desc ? VALUE in desc ? desc[VALUE] : (
    // This is a very special case, if the prop is a getter defined by the
    // prototype, we should invoke it with the draft as context!
    desc.get?.call(state.draft_)
  ) : void 0;
}
function getDescriptorFromProto(source, prop) {
  if (!(prop in source))
    return void 0;
  let proto = getPrototypeOf(source);
  while (proto) {
    const desc = Object.getOwnPropertyDescriptor(proto, prop);
    if (desc)
      return desc;
    proto = getPrototypeOf(proto);
  }
  return void 0;
}
function markChanged(state) {
  if (!state.modified_) {
    state.modified_ = true;
    if (state.parent_) {
      markChanged(state.parent_);
    }
  }
}
function prepareCopy(state) {
  if (!state.copy_) {
    state.assigned_ = /* @__PURE__ */ new Map();
    state.copy_ = shallowCopy(
      state.base_,
      state.scope_.immer_.useStrictShallowCopy_
    );
  }
}

// src/core/immerClass.ts
var Immer2 = class {
  constructor(config) {
    this.autoFreeze_ = true;
    this.useStrictShallowCopy_ = false;
    this.useStrictIteration_ = false;
    /**
     * The `produce` function takes a value and a "recipe function" (whose
     * return value often depends on the base state). The recipe function is
     * free to mutate its first argument however it wants. All mutations are
     * only ever applied to a __copy__ of the base state.
     *
     * Pass only a function to create a "curried producer" which relieves you
     * from passing the recipe function every time.
     *
     * Only plain objects and arrays are made mutable. All other objects are
     * considered uncopyable.
     *
     * Note: This function is __bound__ to its `Immer` instance.
     *
     * @param {any} base - the initial state
     * @param {Function} recipe - function that receives a proxy of the base state as first argument and which can be freely modified
     * @param {Function} patchListener - optional function that will be called with all the patches produced here
     * @returns {any} a new state, or the initial state if nothing was modified
     */
    this.produce = (base, recipe, patchListener) => {
      if (isFunction(base) && !isFunction(recipe)) {
        const defaultBase = recipe;
        recipe = base;
        const self = this;
        return function curriedProduce(base2 = defaultBase, ...args) {
          return self.produce(base2, (draft) => recipe.call(this, draft, ...args));
        };
      }
      if (!isFunction(recipe))
        die(6);
      if (patchListener !== void 0 && !isFunction(patchListener))
        die(7);
      let result;
      if (isDraftable(base)) {
        const scope = enterScope(this);
        const proxy = createProxy(scope, base, void 0);
        let hasError = true;
        try {
          result = recipe(proxy);
          hasError = false;
        } finally {
          if (hasError)
            revokeScope(scope);
          else
            leaveScope(scope);
        }
        usePatchesInScope(scope, patchListener);
        return processResult(result, scope);
      } else if (!base || !isObjectish(base)) {
        result = recipe(base);
        if (result === void 0)
          result = base;
        if (result === NOTHING)
          result = void 0;
        if (this.autoFreeze_)
          freeze(result, true);
        if (patchListener) {
          const p = [];
          const ip = [];
          getPlugin(PluginPatches).generateReplacementPatches_(base, result, {
            patches_: p,
            inversePatches_: ip
          });
          patchListener(p, ip);
        }
        return result;
      } else
        die(1, base);
    };
    this.produceWithPatches = (base, recipe) => {
      if (isFunction(base)) {
        return (state, ...args) => this.produceWithPatches(state, (draft) => base(draft, ...args));
      }
      let patches, inversePatches;
      const result = this.produce(base, recipe, (p, ip) => {
        patches = p;
        inversePatches = ip;
      });
      return [result, patches, inversePatches];
    };
    if (isBoolean(config?.autoFreeze))
      this.setAutoFreeze(config.autoFreeze);
    if (isBoolean(config?.useStrictShallowCopy))
      this.setUseStrictShallowCopy(config.useStrictShallowCopy);
    if (isBoolean(config?.useStrictIteration))
      this.setUseStrictIteration(config.useStrictIteration);
  }
  createDraft(base) {
    if (!isDraftable(base))
      die(8);
    if (isDraft(base))
      base = current(base);
    const scope = enterScope(this);
    const proxy = createProxy(scope, base, void 0);
    proxy[DRAFT_STATE].isManual_ = true;
    leaveScope(scope);
    return proxy;
  }
  finishDraft(draft, patchListener) {
    const state = draft && draft[DRAFT_STATE];
    if (!state || !state.isManual_)
      die(9);
    const { scope_: scope } = state;
    usePatchesInScope(scope, patchListener);
    return processResult(void 0, scope);
  }
  /**
   * Pass true to automatically freeze all copies created by Immer.
   *
   * By default, auto-freezing is enabled.
   */
  setAutoFreeze(value) {
    this.autoFreeze_ = value;
  }
  /**
   * Pass true to enable strict shallow copy.
   *
   * By default, immer does not copy the object descriptors such as getter, setter and non-enumrable properties.
   */
  setUseStrictShallowCopy(value) {
    this.useStrictShallowCopy_ = value;
  }
  /**
   * Pass false to use faster iteration that skips non-enumerable properties
   * but still handles symbols for compatibility.
   *
   * By default, strict iteration is enabled (includes all own properties).
   */
  setUseStrictIteration(value) {
    this.useStrictIteration_ = value;
  }
  shouldUseStrictIteration() {
    return this.useStrictIteration_;
  }
  applyPatches(base, patches) {
    let i;
    for (i = patches.length - 1; i >= 0; i--) {
      const patch = patches[i];
      if (patch.path.length === 0 && patch.op === "replace") {
        base = patch.value;
        break;
      }
    }
    if (i > -1) {
      patches = patches.slice(i + 1);
    }
    const applyPatchesImpl = getPlugin(PluginPatches).applyPatches_;
    if (isDraft(base)) {
      return applyPatchesImpl(base, patches);
    }
    return this.produce(
      base,
      (draft) => applyPatchesImpl(draft, patches)
    );
  }
};
function createProxy(rootScope, value, parent, key) {
  const [draft, state] = isMap(value) ? getPlugin(PluginMapSet).proxyMap_(value, parent) : isSet(value) ? getPlugin(PluginMapSet).proxySet_(value, parent) : createProxyProxy(value, parent);
  const scope = parent?.scope_ ?? getCurrentScope();
  scope.drafts_.push(draft);
  state.callbacks_ = parent?.callbacks_ ?? [];
  state.key_ = key;
  if (parent && key !== void 0) {
    registerChildFinalizationCallback(parent, state, key);
  } else {
    state.callbacks_.push(function rootDraftCleanup(rootScope2) {
      rootScope2.mapSetPlugin_?.fixSetContents(state);
      const { patchPlugin_ } = rootScope2;
      if (state.modified_ && patchPlugin_) {
        patchPlugin_.generatePatches_(state, [], rootScope2);
      }
    });
  }
  return draft;
}

// src/core/current.ts
function current(value) {
  if (!isDraft(value))
    die(10, value);
  return currentImpl(value);
}
function currentImpl(value) {
  if (!isDraftable(value) || isFrozen(value))
    return value;
  const state = value[DRAFT_STATE];
  let copy;
  let strict = true;
  if (state) {
    if (!state.modified_)
      return state.base_;
    state.finalized_ = true;
    copy = shallowCopy(value, state.scope_.immer_.useStrictShallowCopy_);
    strict = state.scope_.immer_.shouldUseStrictIteration();
  } else {
    copy = shallowCopy(value, true);
  }
  each(
    copy,
    (key, childValue) => {
      set(copy, key, currentImpl(childValue));
    },
    strict
  );
  if (state) {
    state.finalized_ = false;
  }
  return copy;
}

// src/plugins/patches.ts
function enablePatches() {
  const errorOffset = 16;
  if (true) {
    errors.push(
      'Sets cannot have "replace" patches.',
      function(op) {
        return "Unsupported patch operation: " + op;
      },
      function(path) {
        return "Cannot apply patch, path doesn't resolve: " + path;
      },
      "Patching reserved attributes like __proto__, prototype and constructor is not allowed"
    );
  }
  function getPath(state, path = []) {
    if (state.key_ !== void 0) {
      const parentCopy = state.parent_.copy_ ?? state.parent_.base_;
      const proxyDraft = getProxyDraft(get(parentCopy, state.key_));
      const valueAtKey = get(parentCopy, state.key_);
      if (valueAtKey === void 0) {
        return null;
      }
      if (valueAtKey !== state.draft_ && valueAtKey !== state.base_ && valueAtKey !== state.copy_) {
        return null;
      }
      if (proxyDraft != null && proxyDraft.base_ !== state.base_) {
        return null;
      }
      const isSet2 = state.parent_.type_ === 3 /* Set */;
      let key;
      if (isSet2) {
        const setParent = state.parent_;
        key = Array.from(setParent.drafts_.keys()).indexOf(state.key_);
      } else {
        key = state.key_;
      }
      if (!(isSet2 && parentCopy.size > key || has(parentCopy, key))) {
        return null;
      }
      path.push(key);
    }
    if (state.parent_) {
      return getPath(state.parent_, path);
    }
    path.reverse();
    try {
      resolvePath(state.copy_, path);
    } catch (e) {
      return null;
    }
    return path;
  }
  function resolvePath(base, path) {
    let current2 = base;
    for (let i = 0; i < path.length - 1; i++) {
      const key = path[i];
      current2 = get(current2, key);
      if (!isObjectish(current2) || current2 === null) {
        throw new Error(`Cannot resolve path at '${path.join("/")}'`);
      }
    }
    return current2;
  }
  const REPLACE = "replace";
  const ADD = "add";
  const REMOVE = "remove";
  function generatePatches_(state, basePath, scope) {
    if (state.scope_.processedForPatches_.has(state)) {
      return;
    }
    state.scope_.processedForPatches_.add(state);
    const { patches_, inversePatches_ } = scope;
    switch (state.type_) {
      case 0 /* Object */:
      case 2 /* Map */:
        return generatePatchesFromAssigned(
          state,
          basePath,
          patches_,
          inversePatches_
        );
      case 1 /* Array */:
        return generateArrayPatches(
          state,
          basePath,
          patches_,
          inversePatches_
        );
      case 3 /* Set */:
        return generateSetPatches(
          state,
          basePath,
          patches_,
          inversePatches_
        );
    }
  }
  function generateArrayPatches(state, basePath, patches, inversePatches) {
    let { base_, assigned_ } = state;
    let copy_ = state.copy_;
    if (copy_.length < base_.length) {
      ;
      [base_, copy_] = [copy_, base_];
      [patches, inversePatches] = [inversePatches, patches];
    }
    const allReassigned = state.allIndicesReassigned_ === true;
    for (let i = 0; i < base_.length; i++) {
      const copiedItem = copy_[i];
      const baseItem = base_[i];
      const isAssigned = allReassigned || assigned_?.get(i.toString());
      if (isAssigned && copiedItem !== baseItem) {
        const childState = copiedItem?.[DRAFT_STATE];
        if (childState && childState.modified_) {
          continue;
        }
        const path = basePath.concat([i]);
        patches.push({
          op: REPLACE,
          path,
          // Need to maybe clone it, as it can in fact be the original value
          // due to the base/copy inversion at the start of this function
          value: clonePatchValueIfNeeded(copiedItem)
        });
        inversePatches.push({
          op: REPLACE,
          path,
          value: clonePatchValueIfNeeded(baseItem)
        });
      }
    }
    for (let i = base_.length; i < copy_.length; i++) {
      const path = basePath.concat([i]);
      patches.push({
        op: ADD,
        path,
        // Need to maybe clone it, as it can in fact be the original value
        // due to the base/copy inversion at the start of this function
        value: clonePatchValueIfNeeded(copy_[i])
      });
    }
    for (let i = copy_.length - 1; base_.length <= i; --i) {
      const path = basePath.concat([i]);
      inversePatches.push({
        op: REMOVE,
        path
      });
    }
  }
  function generatePatchesFromAssigned(state, basePath, patches, inversePatches) {
    const { base_, copy_, type_ } = state;
    each(state.assigned_, (key, assignedValue) => {
      const origValue = get(base_, key, type_);
      const value = get(copy_, key, type_);
      const op = !assignedValue ? REMOVE : has(base_, key) ? REPLACE : ADD;
      if (origValue === value && op === REPLACE)
        return;
      const path = basePath.concat(key);
      patches.push(
        op === REMOVE ? { op, path } : { op, path, value: clonePatchValueIfNeeded(value) }
      );
      inversePatches.push(
        op === ADD ? { op: REMOVE, path } : op === REMOVE ? { op: ADD, path, value: clonePatchValueIfNeeded(origValue) } : { op: REPLACE, path, value: clonePatchValueIfNeeded(origValue) }
      );
    });
  }
  function generateSetPatches(state, basePath, patches, inversePatches) {
    let { base_, copy_ } = state;
    let i = 0;
    base_.forEach((value) => {
      if (!copy_.has(value)) {
        const path = basePath.concat([i]);
        patches.push({
          op: REMOVE,
          path,
          value
        });
        inversePatches.unshift({
          op: ADD,
          path,
          value
        });
      }
      i++;
    });
    i = 0;
    copy_.forEach((value) => {
      if (!base_.has(value)) {
        const path = basePath.concat([i]);
        patches.push({
          op: ADD,
          path,
          value
        });
        inversePatches.unshift({
          op: REMOVE,
          path,
          value
        });
      }
      i++;
    });
  }
  function generateReplacementPatches_(baseValue, replacement, scope) {
    const { patches_, inversePatches_ } = scope;
    patches_.push({
      op: REPLACE,
      path: [],
      value: replacement === NOTHING ? void 0 : replacement
    });
    inversePatches_.push({
      op: REPLACE,
      path: [],
      value: baseValue
    });
  }
  function applyPatches_(draft, patches) {
    patches.forEach((patch) => {
      const { path, op } = patch;
      let base = draft;
      for (let i = 0; i < path.length - 1; i++) {
        const parentType = getArchtype(base);
        let p = path[i];
        if (typeof p !== "string" && typeof p !== "number") {
          p = "" + p;
        }
        if ((parentType === 0 /* Object */ || parentType === 1 /* Array */) && (p === "__proto__" || p === CONSTRUCTOR))
          die(errorOffset + 3);
        if (isFunction(base) && p === PROTOTYPE)
          die(errorOffset + 3);
        base = get(base, p);
        if (!isObjectish(base))
          die(errorOffset + 2, path.join("/"));
      }
      const type = getArchtype(base);
      const value = deepClonePatchValue(patch.value);
      const key = path[path.length - 1];
      switch (op) {
        case REPLACE:
          switch (type) {
            case 2 /* Map */:
              return base.set(key, value);
            case 3 /* Set */:
              die(errorOffset);
            default:
              return base[key] = value;
          }
        case ADD:
          switch (type) {
            case 1 /* Array */:
              return key === "-" ? base.push(value) : base.splice(key, 0, value);
            case 2 /* Map */:
              return base.set(key, value);
            case 3 /* Set */:
              return base.add(value);
            default:
              return base[key] = value;
          }
        case REMOVE:
          switch (type) {
            case 1 /* Array */:
              return base.splice(key, 1);
            case 2 /* Map */:
              return base.delete(key);
            case 3 /* Set */:
              return base.delete(patch.value);
            default:
              return delete base[key];
          }
        default:
          die(errorOffset + 1, op);
      }
    });
    return draft;
  }
  function deepClonePatchValue(obj) {
    if (!isDraftable(obj))
      return obj;
    if (isArray(obj))
      return obj.map(deepClonePatchValue);
    if (isMap(obj))
      return new Map(
        Array.from(obj.entries()).map(([k, v]) => [k, deepClonePatchValue(v)])
      );
    if (isSet(obj))
      return new Set(Array.from(obj).map(deepClonePatchValue));
    const cloned = Object.create(getPrototypeOf(obj));
    for (const key in obj)
      cloned[key] = deepClonePatchValue(obj[key]);
    if (has(obj, DRAFTABLE))
      cloned[DRAFTABLE] = obj[DRAFTABLE];
    return cloned;
  }
  function clonePatchValueIfNeeded(obj) {
    if (isDraft(obj)) {
      return deepClonePatchValue(obj);
    } else
      return obj;
  }
  loadPlugin(PluginPatches, {
    applyPatches_,
    generatePatches_,
    generateReplacementPatches_,
    getPath
  });
}

// src/plugins/mapset.ts
function enableMapSet() {
  class DraftMap extends Map {
    constructor(target, parent) {
      super();
      this[DRAFT_STATE] = {
        type_: 2 /* Map */,
        parent_: parent,
        scope_: parent ? parent.scope_ : getCurrentScope(),
        modified_: false,
        finalized_: false,
        copy_: void 0,
        assigned_: void 0,
        base_: target,
        draft_: this,
        isManual_: false,
        revoked_: false,
        callbacks_: []
      };
    }
    get size() {
      return latest(this[DRAFT_STATE]).size;
    }
    has(key) {
      return latest(this[DRAFT_STATE]).has(key);
    }
    set(key, value) {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      if (!latest(state).has(key) || latest(state).get(key) !== value) {
        prepareMapCopy(state);
        markChanged(state);
        state.assigned_.set(key, true);
        state.copy_.set(key, value);
        state.assigned_.set(key, true);
        handleCrossReference(state, key, value);
      }
      return this;
    }
    delete(key) {
      if (!this.has(key)) {
        return false;
      }
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      prepareMapCopy(state);
      markChanged(state);
      if (state.base_.has(key)) {
        state.assigned_.set(key, false);
      } else {
        state.assigned_.delete(key);
      }
      state.copy_.delete(key);
      return true;
    }
    clear() {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      if (latest(state).size) {
        prepareMapCopy(state);
        markChanged(state);
        state.assigned_ = /* @__PURE__ */ new Map();
        each(state.base_, (key) => {
          state.assigned_.set(key, false);
        });
        state.copy_.clear();
      }
    }
    forEach(cb, thisArg) {
      const state = this[DRAFT_STATE];
      latest(state).forEach((_value, key, _map) => {
        cb.call(thisArg, this.get(key), key, this);
      });
    }
    get(key) {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      const value = latest(state).get(key);
      if (state.finalized_ || !isDraftable(value)) {
        return value;
      }
      if (value !== state.base_.get(key)) {
        return value;
      }
      const draft = createProxy(state.scope_, value, state, key);
      prepareMapCopy(state);
      state.copy_.set(key, draft);
      return draft;
    }
    keys() {
      return latest(this[DRAFT_STATE]).keys();
    }
    values() {
      const iterator = this.keys();
      return {
        [Symbol.iterator]: () => this.values(),
        next: () => {
          const r = iterator.next();
          if (r.done)
            return r;
          const value = this.get(r.value);
          return {
            done: false,
            value
          };
        }
      };
    }
    entries() {
      const iterator = this.keys();
      return {
        [Symbol.iterator]: () => this.entries(),
        next: () => {
          const r = iterator.next();
          if (r.done)
            return r;
          const value = this.get(r.value);
          return {
            done: false,
            value: [r.value, value]
          };
        }
      };
    }
    [(DRAFT_STATE, Symbol.iterator)]() {
      return this.entries();
    }
  }
  function proxyMap_(target, parent) {
    const map = new DraftMap(target, parent);
    return [map, map[DRAFT_STATE]];
  }
  function prepareMapCopy(state) {
    if (!state.copy_) {
      state.assigned_ = /* @__PURE__ */ new Map();
      state.copy_ = new Map(state.base_);
    }
  }
  class DraftSet extends Set {
    constructor(target, parent) {
      super();
      this[DRAFT_STATE] = {
        type_: 3 /* Set */,
        parent_: parent,
        scope_: parent ? parent.scope_ : getCurrentScope(),
        modified_: false,
        finalized_: false,
        copy_: void 0,
        base_: target,
        draft_: this,
        drafts_: /* @__PURE__ */ new Map(),
        revoked_: false,
        isManual_: false,
        assigned_: void 0,
        callbacks_: []
      };
    }
    get size() {
      return latest(this[DRAFT_STATE]).size;
    }
    has(value) {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      if (!state.copy_) {
        return state.base_.has(value);
      }
      if (state.copy_.has(value))
        return true;
      if (state.drafts_.has(value) && state.copy_.has(state.drafts_.get(value)))
        return true;
      return false;
    }
    add(value) {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      if (!this.has(value)) {
        prepareSetCopy(state);
        markChanged(state);
        state.copy_.add(value);
        handleCrossReference(state, value, value);
      }
      return this;
    }
    delete(value) {
      if (!this.has(value)) {
        return false;
      }
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      prepareSetCopy(state);
      markChanged(state);
      return state.copy_.delete(value) || (state.drafts_.has(value) ? state.copy_.delete(state.drafts_.get(value)) : (
        /* istanbul ignore next */
        false
      ));
    }
    clear() {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      if (latest(state).size) {
        prepareSetCopy(state);
        markChanged(state);
        state.copy_.clear();
      }
    }
    values() {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      prepareSetCopy(state);
      return state.copy_.values();
    }
    entries() {
      const state = this[DRAFT_STATE];
      assertUnrevoked(state);
      prepareSetCopy(state);
      return state.copy_.entries();
    }
    keys() {
      return this.values();
    }
    [(DRAFT_STATE, Symbol.iterator)]() {
      return this.values();
    }
    forEach(cb, thisArg) {
      const iterator = this.values();
      let result = iterator.next();
      while (!result.done) {
        cb.call(thisArg, result.value, result.value, this);
        result = iterator.next();
      }
    }
  }
  function proxySet_(target, parent) {
    const set2 = new DraftSet(target, parent);
    return [set2, set2[DRAFT_STATE]];
  }
  function prepareSetCopy(state) {
    if (!state.copy_) {
      state.copy_ = /* @__PURE__ */ new Set();
      state.base_.forEach((value) => {
        if (isDraftable(value)) {
          const draft = createProxy(state.scope_, value, state, value);
          state.drafts_.set(value, draft);
          state.copy_.add(draft);
        } else {
          state.copy_.add(value);
        }
      });
    }
  }
  function assertUnrevoked(state) {
    if (state.revoked_)
      die(3, JSON.stringify(latest(state)));
  }
  function fixSetContents(target) {
    if (target.type_ === 3 /* Set */ && target.copy_) {
      const copy = new Set(target.copy_);
      target.copy_.clear();
      copy.forEach((value) => {
        target.copy_.add(getValue(value));
      });
    }
  }
  loadPlugin(PluginMapSet, { proxyMap_, proxySet_, fixSetContents });
}

// src/plugins/arrayMethods.ts
function enableArrayMethods() {
  const SHIFTING_METHODS = /* @__PURE__ */ new Set(["shift", "unshift"]);
  const QUEUE_METHODS = /* @__PURE__ */ new Set(["push", "pop"]);
  const RESULT_RETURNING_METHODS = /* @__PURE__ */ new Set([
    ...QUEUE_METHODS,
    ...SHIFTING_METHODS
  ]);
  const REORDERING_METHODS = /* @__PURE__ */ new Set(["reverse", "sort"]);
  const MUTATING_METHODS = /* @__PURE__ */ new Set([
    ...RESULT_RETURNING_METHODS,
    ...REORDERING_METHODS,
    "splice"
  ]);
  const FIND_METHODS = /* @__PURE__ */ new Set(["find", "findLast"]);
  const NON_MUTATING_METHODS = /* @__PURE__ */ new Set([
    "filter",
    "slice",
    "concat",
    "flat",
    ...FIND_METHODS,
    "findIndex",
    "findLastIndex",
    "some",
    "every",
    "indexOf",
    "lastIndexOf",
    "includes",
    "join",
    "toString",
    "toLocaleString"
  ]);
  function isMutatingArrayMethod(method) {
    return MUTATING_METHODS.has(method);
  }
  function isNonMutatingArrayMethod(method) {
    return NON_MUTATING_METHODS.has(method);
  }
  function isArrayOperationMethod(method) {
    return isMutatingArrayMethod(method) || isNonMutatingArrayMethod(method);
  }
  function enterOperation(state, method) {
    state.operationMethod = method;
  }
  function exitOperation(state) {
    state.operationMethod = void 0;
  }
  function executeArrayMethod(state, operation, markLength = true) {
    prepareCopy(state);
    const result = operation();
    markChanged(state);
    if (markLength)
      state.assigned_.set("length", true);
    return result;
  }
  function markAllIndicesReassigned(state) {
    state.allIndicesReassigned_ = true;
  }
  function normalizeSliceIndex(index, length) {
    if (index < 0) {
      return Math.max(length + index, 0);
    }
    return Math.min(index, length);
  }
  function handleInsertedValues(state, startIndex, values) {
    for (let i = 0; i < values.length; i++) {
      const index = startIndex + i;
      state.assigned_.set(index, true);
      handleCrossReference(state, index, values[i]);
    }
  }
  function handleSimpleOperation(state, method, args) {
    return executeArrayMethod(state, () => {
      const lengthBefore = state.copy_.length;
      const result = state.copy_[method](...args);
      if (SHIFTING_METHODS.has(method)) {
        markAllIndicesReassigned(state);
      }
      if (method === "push" && args.length > 0) {
        handleInsertedValues(state, lengthBefore, args);
      } else if (method === "unshift" && args.length > 0) {
        handleInsertedValues(state, 0, args);
      }
      return RESULT_RETURNING_METHODS.has(method) ? result : state.draft_;
    });
  }
  function handleReorderingOperation(state, method, args) {
    return executeArrayMethod(
      state,
      () => {
        ;
        state.copy_[method](...args);
        markAllIndicesReassigned(state);
        return state.draft_;
      },
      false
    );
  }
  function createMethodInterceptor(state, originalMethod) {
    return function interceptedMethod(...args) {
      const method = originalMethod;
      enterOperation(state, method);
      try {
        if (isMutatingArrayMethod(method)) {
          if (RESULT_RETURNING_METHODS.has(method)) {
            return handleSimpleOperation(state, method, args);
          }
          if (REORDERING_METHODS.has(method)) {
            return handleReorderingOperation(state, method, args);
          }
          if (method === "splice") {
            const res = executeArrayMethod(
              state,
              () => state.copy_.splice(...args)
            );
            markAllIndicesReassigned(state);
            if (args.length > 2) {
              const startIndex = normalizeSliceIndex(
                args[0] ?? 0,
                state.copy_.length
              );
              handleInsertedValues(state, startIndex, args.slice(2));
            }
            return res;
          }
        } else {
          return handleNonMutatingOperation(state, method, args);
        }
      } finally {
        exitOperation(state);
      }
    };
  }
  function handleNonMutatingOperation(state, method, args) {
    const source = latest(state);
    if (method === "filter") {
      const predicate = args[0];
      const result = [];
      for (let i = 0; i < source.length; i++) {
        if (predicate(source[i], i, source)) {
          result.push(state.draft_[i]);
        }
      }
      return result;
    }
    if (FIND_METHODS.has(method)) {
      const predicate = args[0];
      const isForward = method === "find";
      const step = isForward ? 1 : -1;
      const start = isForward ? 0 : source.length - 1;
      for (let i = start; i >= 0 && i < source.length; i += step) {
        if (predicate(source[i], i, source)) {
          return state.draft_[i];
        }
      }
      return void 0;
    }
    if (method === "slice") {
      const rawStart = args[0] ?? 0;
      const rawEnd = args[1] ?? source.length;
      const start = normalizeSliceIndex(rawStart, source.length);
      const end = normalizeSliceIndex(rawEnd, source.length);
      const result = [];
      for (let i = start; i < end; i++) {
        result.push(state.draft_[i]);
      }
      return result;
    }
    return source[method](...args);
  }
  loadPlugin(PluginArrayMethods, {
    createMethodInterceptor,
    isArrayOperationMethod,
    isMutatingArrayMethod
  });
}

// src/immer.ts
var immer = new Immer2();
var produce = immer.produce;
var produceWithPatches = /* @__PURE__ */ immer.produceWithPatches.bind(
  immer
);
var setAutoFreeze = /* @__PURE__ */ immer.setAutoFreeze.bind(immer);
var setUseStrictShallowCopy = /* @__PURE__ */ immer.setUseStrictShallowCopy.bind(
  immer
);
var setUseStrictIteration = /* @__PURE__ */ immer.setUseStrictIteration.bind(
  immer
);
var applyPatches = /* @__PURE__ */ immer.applyPatches.bind(immer);
var createDraft = /* @__PURE__ */ immer.createDraft.bind(immer);
var finishDraft = /* @__PURE__ */ immer.finishDraft.bind(immer);
var castDraft = (value) => value;
var castImmutable = (value) => value;

//# sourceMappingURL=immer.mjs.map

/***/ },

/***/ "./node_modules/react-redux/dist/react-redux.mjs"
/*!*******************************************************!*\
  !*** ./node_modules/react-redux/dist/react-redux.mjs ***!
  \*******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Provider: () => (/* binding */ Provider_default),
/* harmony export */   ReactReduxContext: () => (/* binding */ ReactReduxContext),
/* harmony export */   batch: () => (/* binding */ batch),
/* harmony export */   connect: () => (/* binding */ connect_default),
/* harmony export */   createDispatchHook: () => (/* binding */ createDispatchHook),
/* harmony export */   createSelectorHook: () => (/* binding */ createSelectorHook),
/* harmony export */   createStoreHook: () => (/* binding */ createStoreHook),
/* harmony export */   shallowEqual: () => (/* binding */ shallowEqual),
/* harmony export */   useDispatch: () => (/* binding */ useDispatch),
/* harmony export */   useSelector: () => (/* binding */ useSelector),
/* harmony export */   useStore: () => (/* binding */ useStore)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var use_sync_external_store_with_selector_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! use-sync-external-store/with-selector.js */ "./node_modules/use-sync-external-store/with-selector.js");
// src/utils/react.ts


// src/utils/react-is.ts
var IS_REACT_19 = /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.version.startsWith("19");
var REACT_ELEMENT_TYPE = /* @__PURE__ */ Symbol.for(
  IS_REACT_19 ? "react.transitional.element" : "react.element"
);
var REACT_PORTAL_TYPE = /* @__PURE__ */ Symbol.for("react.portal");
var REACT_FRAGMENT_TYPE = /* @__PURE__ */ Symbol.for("react.fragment");
var REACT_STRICT_MODE_TYPE = /* @__PURE__ */ Symbol.for("react.strict_mode");
var REACT_PROFILER_TYPE = /* @__PURE__ */ Symbol.for("react.profiler");
var REACT_CONSUMER_TYPE = /* @__PURE__ */ Symbol.for("react.consumer");
var REACT_CONTEXT_TYPE = /* @__PURE__ */ Symbol.for("react.context");
var REACT_FORWARD_REF_TYPE = /* @__PURE__ */ Symbol.for("react.forward_ref");
var REACT_SUSPENSE_TYPE = /* @__PURE__ */ Symbol.for("react.suspense");
var REACT_SUSPENSE_LIST_TYPE = /* @__PURE__ */ Symbol.for(
  "react.suspense_list"
);
var REACT_MEMO_TYPE = /* @__PURE__ */ Symbol.for("react.memo");
var REACT_LAZY_TYPE = /* @__PURE__ */ Symbol.for("react.lazy");
var REACT_OFFSCREEN_TYPE = /* @__PURE__ */ Symbol.for("react.offscreen");
var REACT_CLIENT_REFERENCE = /* @__PURE__ */ Symbol.for(
  "react.client.reference"
);
var ForwardRef = REACT_FORWARD_REF_TYPE;
var Memo = REACT_MEMO_TYPE;
function isValidElementType(type) {
  return typeof type === "string" || typeof type === "function" || type === REACT_FRAGMENT_TYPE || type === REACT_PROFILER_TYPE || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || type === REACT_OFFSCREEN_TYPE || typeof type === "object" && type !== null && (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_CONSUMER_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || type.$$typeof === REACT_CLIENT_REFERENCE || type.getModuleId !== void 0) ? true : false;
}
function typeOf(object) {
  if (typeof object === "object" && object !== null) {
    const { $$typeof } = object;
    switch ($$typeof) {
      case REACT_ELEMENT_TYPE:
        switch (object = object.type, object) {
          case REACT_FRAGMENT_TYPE:
          case REACT_PROFILER_TYPE:
          case REACT_STRICT_MODE_TYPE:
          case REACT_SUSPENSE_TYPE:
          case REACT_SUSPENSE_LIST_TYPE:
            return object;
          default:
            switch (object = object && object.$$typeof, object) {
              case REACT_CONTEXT_TYPE:
              case REACT_FORWARD_REF_TYPE:
              case REACT_LAZY_TYPE:
              case REACT_MEMO_TYPE:
                return object;
              case REACT_CONSUMER_TYPE:
                return object;
              default:
                return $$typeof;
            }
        }
      case REACT_PORTAL_TYPE:
        return $$typeof;
    }
  }
}
function isContextConsumer(object) {
  return IS_REACT_19 ? typeOf(object) === REACT_CONSUMER_TYPE : typeOf(object) === REACT_CONTEXT_TYPE;
}
function isMemo(object) {
  return typeOf(object) === REACT_MEMO_TYPE;
}

// src/utils/warning.ts
function warning(message) {
  if (typeof console !== "undefined" && typeof console.error === "function") {
    console.error(message);
  }
  try {
    throw new Error(message);
  } catch (e) {
  }
}

// src/connect/verifySubselectors.ts
function verify(selector, methodName) {
  if (!selector) {
    throw new Error(`Unexpected value for ${methodName} in connect.`);
  } else if (methodName === "mapStateToProps" || methodName === "mapDispatchToProps") {
    if (!Object.prototype.hasOwnProperty.call(selector, "dependsOnOwnProps")) {
      warning(
        `The selector for ${methodName} of connect did not specify a value for dependsOnOwnProps.`
      );
    }
  }
}
function verifySubselectors(mapStateToProps, mapDispatchToProps, mergeProps) {
  verify(mapStateToProps, "mapStateToProps");
  verify(mapDispatchToProps, "mapDispatchToProps");
  verify(mergeProps, "mergeProps");
}

// src/connect/selectorFactory.ts
function pureFinalPropsSelectorFactory(mapStateToProps, mapDispatchToProps, mergeProps, dispatch, {
  areStatesEqual,
  areOwnPropsEqual,
  areStatePropsEqual
}) {
  let hasRunAtLeastOnce = false;
  let state;
  let ownProps;
  let stateProps;
  let dispatchProps;
  let mergedProps;
  function handleFirstCall(firstState, firstOwnProps) {
    state = firstState;
    ownProps = firstOwnProps;
    stateProps = mapStateToProps(state, ownProps);
    dispatchProps = mapDispatchToProps(dispatch, ownProps);
    mergedProps = mergeProps(stateProps, dispatchProps, ownProps);
    hasRunAtLeastOnce = true;
    return mergedProps;
  }
  function handleNewPropsAndNewState() {
    stateProps = mapStateToProps(state, ownProps);
    if (mapDispatchToProps.dependsOnOwnProps)
      dispatchProps = mapDispatchToProps(dispatch, ownProps);
    mergedProps = mergeProps(stateProps, dispatchProps, ownProps);
    return mergedProps;
  }
  function handleNewProps() {
    if (mapStateToProps.dependsOnOwnProps)
      stateProps = mapStateToProps(state, ownProps);
    if (mapDispatchToProps.dependsOnOwnProps)
      dispatchProps = mapDispatchToProps(dispatch, ownProps);
    mergedProps = mergeProps(stateProps, dispatchProps, ownProps);
    return mergedProps;
  }
  function handleNewState() {
    const nextStateProps = mapStateToProps(state, ownProps);
    const statePropsChanged = !areStatePropsEqual(nextStateProps, stateProps);
    stateProps = nextStateProps;
    if (statePropsChanged)
      mergedProps = mergeProps(stateProps, dispatchProps, ownProps);
    return mergedProps;
  }
  function handleSubsequentCalls(nextState, nextOwnProps) {
    const propsChanged = !areOwnPropsEqual(nextOwnProps, ownProps);
    const stateChanged = !areStatesEqual(
      nextState,
      state,
      nextOwnProps,
      ownProps
    );
    state = nextState;
    ownProps = nextOwnProps;
    if (propsChanged && stateChanged) return handleNewPropsAndNewState();
    if (propsChanged) return handleNewProps();
    if (stateChanged) return handleNewState();
    return mergedProps;
  }
  return function pureFinalPropsSelector(nextState, nextOwnProps) {
    return hasRunAtLeastOnce ? handleSubsequentCalls(nextState, nextOwnProps) : handleFirstCall(nextState, nextOwnProps);
  };
}
function finalPropsSelectorFactory(dispatch, {
  initMapStateToProps,
  initMapDispatchToProps,
  initMergeProps,
  ...options
}) {
  const mapStateToProps = initMapStateToProps(dispatch, options);
  const mapDispatchToProps = initMapDispatchToProps(dispatch, options);
  const mergeProps = initMergeProps(dispatch, options);
  if (true) {
    verifySubselectors(mapStateToProps, mapDispatchToProps, mergeProps);
  }
  return pureFinalPropsSelectorFactory(mapStateToProps, mapDispatchToProps, mergeProps, dispatch, options);
}

// src/utils/bindActionCreators.ts
function bindActionCreators(actionCreators, dispatch) {
  const boundActionCreators = {};
  for (const key in actionCreators) {
    const actionCreator = actionCreators[key];
    if (typeof actionCreator === "function") {
      boundActionCreators[key] = (...args) => dispatch(actionCreator(...args));
    }
  }
  return boundActionCreators;
}

// src/utils/isPlainObject.ts
function isPlainObject(obj) {
  if (typeof obj !== "object" || obj === null) return false;
  const proto = Object.getPrototypeOf(obj);
  if (proto === null) return true;
  let baseProto = proto;
  while (Object.getPrototypeOf(baseProto) !== null) {
    baseProto = Object.getPrototypeOf(baseProto);
  }
  return proto === baseProto;
}

// src/utils/verifyPlainObject.ts
function verifyPlainObject(value, displayName, methodName) {
  if (!isPlainObject(value)) {
    warning(
      `${methodName}() in ${displayName} must return a plain object. Instead received ${value}.`
    );
  }
}

// src/connect/wrapMapToProps.ts
function wrapMapToPropsConstant(getConstant) {
  return function initConstantSelector(dispatch) {
    const constant = getConstant(dispatch);
    function constantSelector() {
      return constant;
    }
    constantSelector.dependsOnOwnProps = false;
    return constantSelector;
  };
}
function getDependsOnOwnProps(mapToProps) {
  return mapToProps.dependsOnOwnProps ? Boolean(mapToProps.dependsOnOwnProps) : mapToProps.length !== 1;
}
function wrapMapToPropsFunc(mapToProps, methodName) {
  return function initProxySelector(dispatch, { displayName }) {
    const proxy = function mapToPropsProxy(stateOrDispatch, ownProps) {
      return proxy.dependsOnOwnProps ? proxy.mapToProps(stateOrDispatch, ownProps) : proxy.mapToProps(stateOrDispatch, void 0);
    };
    proxy.dependsOnOwnProps = true;
    proxy.mapToProps = function detectFactoryAndVerify(stateOrDispatch, ownProps) {
      proxy.mapToProps = mapToProps;
      proxy.dependsOnOwnProps = getDependsOnOwnProps(mapToProps);
      let props = proxy(stateOrDispatch, ownProps);
      if (typeof props === "function") {
        proxy.mapToProps = props;
        proxy.dependsOnOwnProps = getDependsOnOwnProps(props);
        props = proxy(stateOrDispatch, ownProps);
      }
      if (true)
        verifyPlainObject(props, displayName, methodName);
      return props;
    };
    return proxy;
  };
}

// src/connect/invalidArgFactory.ts
function createInvalidArgFactory(arg, name) {
  return (dispatch, options) => {
    throw new Error(
      `Invalid value of type ${typeof arg} for ${name} argument when connecting component ${options.wrappedComponentName}.`
    );
  };
}

// src/connect/mapDispatchToProps.ts
function mapDispatchToPropsFactory(mapDispatchToProps) {
  return mapDispatchToProps && typeof mapDispatchToProps === "object" ? wrapMapToPropsConstant(
    (dispatch) => (
      // @ts-ignore
      bindActionCreators(mapDispatchToProps, dispatch)
    )
  ) : !mapDispatchToProps ? wrapMapToPropsConstant((dispatch) => ({
    dispatch
  })) : typeof mapDispatchToProps === "function" ? (
    // @ts-ignore
    wrapMapToPropsFunc(mapDispatchToProps, "mapDispatchToProps")
  ) : createInvalidArgFactory(mapDispatchToProps, "mapDispatchToProps");
}

// src/connect/mapStateToProps.ts
function mapStateToPropsFactory(mapStateToProps) {
  return !mapStateToProps ? wrapMapToPropsConstant(() => ({})) : typeof mapStateToProps === "function" ? (
    // @ts-ignore
    wrapMapToPropsFunc(mapStateToProps, "mapStateToProps")
  ) : createInvalidArgFactory(mapStateToProps, "mapStateToProps");
}

// src/connect/mergeProps.ts
function defaultMergeProps(stateProps, dispatchProps, ownProps) {
  return { ...ownProps, ...stateProps, ...dispatchProps };
}
function wrapMergePropsFunc(mergeProps) {
  return function initMergePropsProxy(dispatch, { displayName, areMergedPropsEqual }) {
    let hasRunOnce = false;
    let mergedProps;
    return function mergePropsProxy(stateProps, dispatchProps, ownProps) {
      const nextMergedProps = mergeProps(stateProps, dispatchProps, ownProps);
      if (hasRunOnce) {
        if (!areMergedPropsEqual(nextMergedProps, mergedProps))
          mergedProps = nextMergedProps;
      } else {
        hasRunOnce = true;
        mergedProps = nextMergedProps;
        if (true)
          verifyPlainObject(mergedProps, displayName, "mergeProps");
      }
      return mergedProps;
    };
  };
}
function mergePropsFactory(mergeProps) {
  return !mergeProps ? () => defaultMergeProps : typeof mergeProps === "function" ? wrapMergePropsFunc(mergeProps) : createInvalidArgFactory(mergeProps, "mergeProps");
}

// src/utils/batch.ts
function defaultNoopBatch(callback) {
  callback();
}

// src/utils/Subscription.ts
function createListenerCollection() {
  let first = null;
  let last = null;
  return {
    clear() {
      first = null;
      last = null;
    },
    notify() {
      defaultNoopBatch(() => {
        let listener = first;
        while (listener) {
          listener.callback();
          listener = listener.next;
        }
      });
    },
    get() {
      const listeners = [];
      let listener = first;
      while (listener) {
        listeners.push(listener);
        listener = listener.next;
      }
      return listeners;
    },
    subscribe(callback) {
      let isSubscribed = true;
      const listener = last = {
        callback,
        next: null,
        prev: last
      };
      if (listener.prev) {
        listener.prev.next = listener;
      } else {
        first = listener;
      }
      return function unsubscribe() {
        if (!isSubscribed || first === null) return;
        isSubscribed = false;
        if (listener.next) {
          listener.next.prev = listener.prev;
        } else {
          last = listener.prev;
        }
        if (listener.prev) {
          listener.prev.next = listener.next;
        } else {
          first = listener.next;
        }
      };
    }
  };
}
var nullListeners = {
  notify() {
  },
  get: () => []
};
function createSubscription(store, parentSub) {
  let unsubscribe;
  let listeners = nullListeners;
  let subscriptionsAmount = 0;
  let selfSubscribed = false;
  function addNestedSub(listener) {
    trySubscribe();
    const cleanupListener = listeners.subscribe(listener);
    let removed = false;
    return () => {
      if (!removed) {
        removed = true;
        cleanupListener();
        tryUnsubscribe();
      }
    };
  }
  function notifyNestedSubs() {
    listeners.notify();
  }
  function handleChangeWrapper() {
    if (subscription.onStateChange) {
      subscription.onStateChange();
    }
  }
  function isSubscribed() {
    return selfSubscribed;
  }
  function trySubscribe() {
    subscriptionsAmount++;
    if (!unsubscribe) {
      unsubscribe = parentSub ? parentSub.addNestedSub(handleChangeWrapper) : store.subscribe(handleChangeWrapper);
      listeners = createListenerCollection();
    }
  }
  function tryUnsubscribe() {
    subscriptionsAmount--;
    if (unsubscribe && subscriptionsAmount === 0) {
      unsubscribe();
      unsubscribe = void 0;
      listeners.clear();
      listeners = nullListeners;
    }
  }
  function trySubscribeSelf() {
    if (!selfSubscribed) {
      selfSubscribed = true;
      trySubscribe();
    }
  }
  function tryUnsubscribeSelf() {
    if (selfSubscribed) {
      selfSubscribed = false;
      tryUnsubscribe();
    }
  }
  const subscription = {
    addNestedSub,
    notifyNestedSubs,
    handleChangeWrapper,
    isSubscribed,
    trySubscribe: trySubscribeSelf,
    tryUnsubscribe: tryUnsubscribeSelf,
    getListeners: () => listeners
  };
  return subscription;
}

// src/utils/useIsomorphicLayoutEffect.ts
var canUseDOM = () => !!(typeof window !== "undefined" && typeof window.document !== "undefined" && typeof window.document.createElement !== "undefined");
var isDOM = /* @__PURE__ */ canUseDOM();
var isRunningInReactNative = () => typeof navigator !== "undefined" && navigator.product === "ReactNative";
var isReactNative = /* @__PURE__ */ isRunningInReactNative();
var getUseIsomorphicLayoutEffect = () => isDOM || isReactNative ? react__WEBPACK_IMPORTED_MODULE_0__.useLayoutEffect : react__WEBPACK_IMPORTED_MODULE_0__.useEffect;
var useIsomorphicLayoutEffect = /* @__PURE__ */ getUseIsomorphicLayoutEffect();

// src/utils/shallowEqual.ts
function is(x, y) {
  if (x === y) {
    return x !== 0 || y !== 0 || 1 / x === 1 / y;
  } else {
    return x !== x && y !== y;
  }
}
function shallowEqual(objA, objB) {
  if (is(objA, objB)) return true;
  if (typeof objA !== "object" || objA === null || typeof objB !== "object" || objB === null) {
    return false;
  }
  const keysA = Object.keys(objA);
  const keysB = Object.keys(objB);
  if (keysA.length !== keysB.length) return false;
  for (let i = 0; i < keysA.length; i++) {
    if (!Object.prototype.hasOwnProperty.call(objB, keysA[i]) || !is(objA[keysA[i]], objB[keysA[i]])) {
      return false;
    }
  }
  return true;
}

// src/utils/hoistStatics.ts
var REACT_STATICS = {
  childContextTypes: true,
  contextType: true,
  contextTypes: true,
  defaultProps: true,
  displayName: true,
  getDefaultProps: true,
  getDerivedStateFromError: true,
  getDerivedStateFromProps: true,
  mixins: true,
  propTypes: true,
  type: true
};
var KNOWN_STATICS = {
  name: true,
  length: true,
  prototype: true,
  caller: true,
  callee: true,
  arguments: true,
  arity: true
};
var FORWARD_REF_STATICS = {
  $$typeof: true,
  render: true,
  defaultProps: true,
  displayName: true,
  propTypes: true
};
var MEMO_STATICS = {
  $$typeof: true,
  compare: true,
  defaultProps: true,
  displayName: true,
  propTypes: true,
  type: true
};
var TYPE_STATICS = {
  [ForwardRef]: FORWARD_REF_STATICS,
  [Memo]: MEMO_STATICS
};
function getStatics(component) {
  if (isMemo(component)) {
    return MEMO_STATICS;
  }
  return TYPE_STATICS[component["$$typeof"]] || REACT_STATICS;
}
var defineProperty = Object.defineProperty;
var getOwnPropertyNames = Object.getOwnPropertyNames;
var getOwnPropertySymbols = Object.getOwnPropertySymbols;
var getOwnPropertyDescriptor = Object.getOwnPropertyDescriptor;
var getPrototypeOf = Object.getPrototypeOf;
var objectPrototype = Object.prototype;
function hoistNonReactStatics(targetComponent, sourceComponent) {
  if (typeof sourceComponent !== "string") {
    if (objectPrototype) {
      const inheritedComponent = getPrototypeOf(sourceComponent);
      if (inheritedComponent && inheritedComponent !== objectPrototype) {
        hoistNonReactStatics(targetComponent, inheritedComponent);
      }
    }
    let keys = getOwnPropertyNames(sourceComponent);
    if (getOwnPropertySymbols) {
      keys = keys.concat(getOwnPropertySymbols(sourceComponent));
    }
    const targetStatics = getStatics(targetComponent);
    const sourceStatics = getStatics(sourceComponent);
    for (let i = 0; i < keys.length; ++i) {
      const key = keys[i];
      if (!KNOWN_STATICS[key] && !(sourceStatics && sourceStatics[key]) && !(targetStatics && targetStatics[key])) {
        const descriptor = getOwnPropertyDescriptor(sourceComponent, key);
        try {
          defineProperty(targetComponent, key, descriptor);
        } catch (e) {
        }
      }
    }
  }
  return targetComponent;
}

// src/components/Context.ts
var ContextKey = /* @__PURE__ */ Symbol.for(`react-redux-context`);
var gT = typeof globalThis !== "undefined" ? globalThis : (
  /* fall back to a per-module scope (pre-8.1 behaviour) if `globalThis` is not available */
  {}
);
function getContext() {
  if (!react__WEBPACK_IMPORTED_MODULE_0__.createContext) return {};
  const contextMap = gT[ContextKey] ??= /* @__PURE__ */ new Map();
  let realContext = contextMap.get(react__WEBPACK_IMPORTED_MODULE_0__.createContext);
  if (!realContext) {
    realContext = react__WEBPACK_IMPORTED_MODULE_0__.createContext(
      null
    );
    if (true) {
      realContext.displayName = "ReactRedux";
    }
    contextMap.set(react__WEBPACK_IMPORTED_MODULE_0__.createContext, realContext);
  }
  return realContext;
}
var ReactReduxContext = /* @__PURE__ */ getContext();

// src/components/connect.tsx
var NO_SUBSCRIPTION_ARRAY = [null, null];
var stringifyComponent = (Comp) => {
  try {
    return JSON.stringify(Comp);
  } catch (err) {
    return String(Comp);
  }
};
function useIsomorphicLayoutEffectWithArgs(effectFunc, effectArgs, dependencies) {
  useIsomorphicLayoutEffect(() => effectFunc(...effectArgs), dependencies);
}
function captureWrapperProps(lastWrapperProps, lastChildProps, renderIsScheduled, wrapperProps, childPropsFromStoreUpdate, notifyNestedSubs) {
  lastWrapperProps.current = wrapperProps;
  renderIsScheduled.current = false;
  if (childPropsFromStoreUpdate.current) {
    childPropsFromStoreUpdate.current = null;
    notifyNestedSubs();
  }
}
function subscribeUpdates(shouldHandleStateChanges, store, subscription, childPropsSelector, lastWrapperProps, lastChildProps, renderIsScheduled, isMounted, childPropsFromStoreUpdate, notifyNestedSubs, additionalSubscribeListener) {
  if (!shouldHandleStateChanges) return () => {
  };
  let didUnsubscribe = false;
  let lastThrownError = null;
  const checkForUpdates = () => {
    if (didUnsubscribe || !isMounted.current) {
      return;
    }
    const latestStoreState = store.getState();
    let newChildProps, error;
    try {
      newChildProps = childPropsSelector(
        latestStoreState,
        lastWrapperProps.current
      );
    } catch (e) {
      error = e;
      lastThrownError = e;
    }
    if (!error) {
      lastThrownError = null;
    }
    if (newChildProps === lastChildProps.current) {
      if (!renderIsScheduled.current) {
        notifyNestedSubs();
      }
    } else {
      lastChildProps.current = newChildProps;
      childPropsFromStoreUpdate.current = newChildProps;
      renderIsScheduled.current = true;
      additionalSubscribeListener();
    }
  };
  subscription.onStateChange = checkForUpdates;
  subscription.trySubscribe();
  checkForUpdates();
  const unsubscribeWrapper = () => {
    didUnsubscribe = true;
    subscription.tryUnsubscribe();
    subscription.onStateChange = null;
    if (lastThrownError) {
      throw lastThrownError;
    }
  };
  return unsubscribeWrapper;
}
function strictEqual(a, b) {
  return a === b;
}
var hasWarnedAboutDeprecatedPureOption = false;
function connect(mapStateToProps, mapDispatchToProps, mergeProps, {
  // The `pure` option has been removed, so TS doesn't like us destructuring this to check its existence.
  // @ts-ignore
  pure,
  areStatesEqual = strictEqual,
  areOwnPropsEqual = shallowEqual,
  areStatePropsEqual = shallowEqual,
  areMergedPropsEqual = shallowEqual,
  // use React's forwardRef to expose a ref of the wrapped component
  forwardRef = false,
  // the context consumer to use
  context = ReactReduxContext
} = {}) {
  if (true) {
    if (pure !== void 0 && !hasWarnedAboutDeprecatedPureOption) {
      hasWarnedAboutDeprecatedPureOption = true;
      warning(
        'The `pure` option has been removed. `connect` is now always a "pure/memoized" component'
      );
    }
  }
  const Context = context;
  const initMapStateToProps = mapStateToPropsFactory(mapStateToProps);
  const initMapDispatchToProps = mapDispatchToPropsFactory(mapDispatchToProps);
  const initMergeProps = mergePropsFactory(mergeProps);
  const shouldHandleStateChanges = Boolean(mapStateToProps);
  const wrapWithConnect = (WrappedComponent) => {
    if (true) {
      const isValid = /* @__PURE__ */ isValidElementType(WrappedComponent);
      if (!isValid)
        throw new Error(
          `You must pass a component to the function returned by connect. Instead received ${stringifyComponent(
            WrappedComponent
          )}`
        );
    }
    const wrappedComponentName = WrappedComponent.displayName || WrappedComponent.name || "Component";
    const displayName = `Connect(${wrappedComponentName})`;
    const selectorFactoryOptions = {
      shouldHandleStateChanges,
      displayName,
      wrappedComponentName,
      WrappedComponent,
      // @ts-ignore
      initMapStateToProps,
      initMapDispatchToProps,
      initMergeProps,
      areStatesEqual,
      areStatePropsEqual,
      areOwnPropsEqual,
      areMergedPropsEqual
    };
    function ConnectFunction(props) {
      const [propsContext, reactReduxForwardedRef, wrapperProps] = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        const { reactReduxForwardedRef: reactReduxForwardedRef2, ...wrapperProps2 } = props;
        return [props.context, reactReduxForwardedRef2, wrapperProps2];
      }, [props]);
      const ContextToUse = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        let ResultContext = Context;
        if (propsContext?.Consumer) {
          if (true) {
            const isValid = /* @__PURE__ */ isContextConsumer(
              // @ts-ignore
              /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.createElement(propsContext.Consumer, null)
            );
            if (!isValid) {
              throw new Error(
                "You must pass a valid React context consumer as `props.context`"
              );
            }
            ResultContext = propsContext;
          }
        }
        return ResultContext;
      }, [propsContext, Context]);
      const contextValue = react__WEBPACK_IMPORTED_MODULE_0__.useContext(ContextToUse);
      const didStoreComeFromProps = Boolean(props.store) && Boolean(props.store.getState) && Boolean(props.store.dispatch);
      const didStoreComeFromContext = Boolean(contextValue) && Boolean(contextValue.store);
      if ( true && !didStoreComeFromProps && !didStoreComeFromContext) {
        throw new Error(
          `Could not find "store" in the context of "${displayName}". Either wrap the root component in a <Provider>, or pass a custom React context provider to <Provider> and the corresponding React context consumer to ${displayName} in connect options.`
        );
      }
      const store = didStoreComeFromProps ? props.store : contextValue.store;
      const getServerState = didStoreComeFromContext ? contextValue.getServerState : store.getState;
      const childPropsSelector = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        return finalPropsSelectorFactory(store.dispatch, selectorFactoryOptions);
      }, [store]);
      const [subscription, notifyNestedSubs] = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        if (!shouldHandleStateChanges) return NO_SUBSCRIPTION_ARRAY;
        const subscription2 = createSubscription(
          store,
          didStoreComeFromProps ? void 0 : contextValue.subscription
        );
        const notifyNestedSubs2 = subscription2.notifyNestedSubs.bind(subscription2);
        return [subscription2, notifyNestedSubs2];
      }, [store, didStoreComeFromProps, contextValue]);
      const overriddenContextValue = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        if (didStoreComeFromProps) {
          return contextValue;
        }
        return {
          ...contextValue,
          subscription
        };
      }, [didStoreComeFromProps, contextValue, subscription]);
      const lastChildProps = react__WEBPACK_IMPORTED_MODULE_0__.useRef(void 0);
      const lastWrapperProps = react__WEBPACK_IMPORTED_MODULE_0__.useRef(wrapperProps);
      const childPropsFromStoreUpdate = react__WEBPACK_IMPORTED_MODULE_0__.useRef(void 0);
      const renderIsScheduled = react__WEBPACK_IMPORTED_MODULE_0__.useRef(false);
      const isMounted = react__WEBPACK_IMPORTED_MODULE_0__.useRef(false);
      const latestSubscriptionCallbackError = react__WEBPACK_IMPORTED_MODULE_0__.useRef(
        void 0
      );
      useIsomorphicLayoutEffect(() => {
        isMounted.current = true;
        return () => {
          isMounted.current = false;
        };
      }, []);
      const actualChildPropsSelector = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        const selector = () => {
          if (childPropsFromStoreUpdate.current && wrapperProps === lastWrapperProps.current) {
            return childPropsFromStoreUpdate.current;
          }
          return childPropsSelector(store.getState(), wrapperProps);
        };
        return selector;
      }, [store, wrapperProps]);
      const subscribeForReact = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        const subscribe = (reactListener) => {
          if (!subscription) {
            return () => {
            };
          }
          return subscribeUpdates(
            shouldHandleStateChanges,
            store,
            subscription,
            // @ts-ignore
            childPropsSelector,
            lastWrapperProps,
            lastChildProps,
            renderIsScheduled,
            isMounted,
            childPropsFromStoreUpdate,
            notifyNestedSubs,
            reactListener
          );
        };
        return subscribe;
      }, [subscription]);
      useIsomorphicLayoutEffectWithArgs(captureWrapperProps, [
        lastWrapperProps,
        lastChildProps,
        renderIsScheduled,
        wrapperProps,
        childPropsFromStoreUpdate,
        notifyNestedSubs
      ]);
      let actualChildProps;
      try {
        actualChildProps = react__WEBPACK_IMPORTED_MODULE_0__.useSyncExternalStore(
          // TODO We're passing through a big wrapper that does a bunch of extra side effects besides subscribing
          subscribeForReact,
          // TODO This is incredibly hacky. We've already processed the store update and calculated new child props,
          // TODO and we're just passing that through so it triggers a re-render for us rather than relying on `uSES`.
          actualChildPropsSelector,
          getServerState ? () => childPropsSelector(getServerState(), wrapperProps) : actualChildPropsSelector
        );
      } catch (err) {
        if (latestSubscriptionCallbackError.current) {
          ;
          err.message += `
The error may be correlated with this previous error:
${latestSubscriptionCallbackError.current.stack}

`;
        }
        throw err;
      }
      useIsomorphicLayoutEffect(() => {
        latestSubscriptionCallbackError.current = void 0;
        childPropsFromStoreUpdate.current = void 0;
        lastChildProps.current = actualChildProps;
      });
      const renderedWrappedComponent = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        return (
          // @ts-ignore
          /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.createElement(
            WrappedComponent,
            {
              ...actualChildProps,
              ref: reactReduxForwardedRef
            }
          )
        );
      }, [reactReduxForwardedRef, WrappedComponent, actualChildProps]);
      const renderedChild = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
        if (shouldHandleStateChanges) {
          return /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.createElement(ContextToUse.Provider, { value: overriddenContextValue }, renderedWrappedComponent);
        }
        return renderedWrappedComponent;
      }, [ContextToUse, renderedWrappedComponent, overriddenContextValue]);
      return renderedChild;
    }
    const _Connect = react__WEBPACK_IMPORTED_MODULE_0__.memo(ConnectFunction);
    const Connect = _Connect;
    Connect.WrappedComponent = WrappedComponent;
    Connect.displayName = ConnectFunction.displayName = displayName;
    if (forwardRef) {
      const _forwarded = react__WEBPACK_IMPORTED_MODULE_0__.forwardRef(
        function forwardConnectRef(props, ref) {
          return /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.createElement(Connect, { ...props, reactReduxForwardedRef: ref });
        }
      );
      const forwarded = _forwarded;
      forwarded.displayName = displayName;
      forwarded.WrappedComponent = WrappedComponent;
      return /* @__PURE__ */ hoistNonReactStatics(forwarded, WrappedComponent);
    }
    return /* @__PURE__ */ hoistNonReactStatics(Connect, WrappedComponent);
  };
  return wrapWithConnect;
}
var connect_default = connect;

// src/components/Provider.tsx
function Provider(providerProps) {
  const { children, context, serverState, store } = providerProps;
  const contextValue = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => {
    const subscription = createSubscription(store);
    const baseContextValue = {
      store,
      subscription,
      getServerState: serverState ? () => serverState : void 0
    };
    if (false) // removed by dead control flow
{} else {
      const { identityFunctionCheck = "once", stabilityCheck = "once" } = providerProps;
      return /* @__PURE__ */ Object.assign(baseContextValue, {
        stabilityCheck,
        identityFunctionCheck
      });
    }
  }, [store, serverState]);
  const previousState = react__WEBPACK_IMPORTED_MODULE_0__.useMemo(() => store.getState(), [store]);
  useIsomorphicLayoutEffect(() => {
    const { subscription } = contextValue;
    subscription.onStateChange = subscription.notifyNestedSubs;
    subscription.trySubscribe();
    if (previousState !== store.getState()) {
      subscription.notifyNestedSubs();
    }
    return () => {
      subscription.tryUnsubscribe();
      subscription.onStateChange = void 0;
    };
  }, [contextValue, previousState]);
  const Context = context || ReactReduxContext;
  return /* @__PURE__ */ react__WEBPACK_IMPORTED_MODULE_0__.createElement(Context.Provider, { value: contextValue }, children);
}
var Provider_default = Provider;

// src/hooks/useReduxContext.ts
function createReduxContextHook(context = ReactReduxContext) {
  return function useReduxContext2() {
    const contextValue = react__WEBPACK_IMPORTED_MODULE_0__.useContext(context);
    if ( true && !contextValue) {
      throw new Error(
        "could not find react-redux context value; please ensure the component is wrapped in a <Provider>"
      );
    }
    return contextValue;
  };
}
var useReduxContext = /* @__PURE__ */ createReduxContextHook();

// src/hooks/useStore.ts
function createStoreHook(context = ReactReduxContext) {
  const useReduxContext2 = context === ReactReduxContext ? useReduxContext : (
    // @ts-ignore
    createReduxContextHook(context)
  );
  const useStore2 = () => {
    const { store } = useReduxContext2();
    return store;
  };
  Object.assign(useStore2, {
    withTypes: () => useStore2
  });
  return useStore2;
}
var useStore = /* @__PURE__ */ createStoreHook();

// src/hooks/useDispatch.ts
function createDispatchHook(context = ReactReduxContext) {
  const useStore2 = context === ReactReduxContext ? useStore : createStoreHook(context);
  const useDispatch2 = () => {
    const store = useStore2();
    return store.dispatch;
  };
  Object.assign(useDispatch2, {
    withTypes: () => useDispatch2
  });
  return useDispatch2;
}
var useDispatch = /* @__PURE__ */ createDispatchHook();

// src/hooks/useSelector.ts

var refEquality = (a, b) => a === b;
function createSelectorHook(context = ReactReduxContext) {
  const useReduxContext2 = context === ReactReduxContext ? useReduxContext : createReduxContextHook(context);
  const useSelector2 = (selector, equalityFnOrOptions = {}) => {
    const { equalityFn = refEquality } = typeof equalityFnOrOptions === "function" ? { equalityFn: equalityFnOrOptions } : equalityFnOrOptions;
    if (true) {
      if (!selector) {
        throw new Error(`You must pass a selector to useSelector`);
      }
      if (typeof selector !== "function") {
        throw new Error(`You must pass a function as a selector to useSelector`);
      }
      if (typeof equalityFn !== "function") {
        throw new Error(
          `You must pass a function as an equality function to useSelector`
        );
      }
    }
    const reduxContext = useReduxContext2();
    const { store, subscription, getServerState } = reduxContext;
    const firstRun = react__WEBPACK_IMPORTED_MODULE_0__.useRef(true);
    const wrappedSelector = react__WEBPACK_IMPORTED_MODULE_0__.useCallback(
      {
        [selector.name](state) {
          const selected = selector(state);
          if (true) {
            const { devModeChecks = {} } = typeof equalityFnOrOptions === "function" ? {} : equalityFnOrOptions;
            const { identityFunctionCheck, stabilityCheck } = reduxContext;
            const {
              identityFunctionCheck: finalIdentityFunctionCheck,
              stabilityCheck: finalStabilityCheck
            } = {
              stabilityCheck,
              identityFunctionCheck,
              ...devModeChecks
            };
            if (finalStabilityCheck === "always" || finalStabilityCheck === "once" && firstRun.current) {
              const toCompare = selector(state);
              if (!equalityFn(selected, toCompare)) {
                let stack = void 0;
                try {
                  throw new Error();
                } catch (e) {
                  ;
                  ({ stack } = e);
                }
                console.warn(
                  "Selector " + (selector.name || "unknown") + " returned a different result when called with the same parameters. This can lead to unnecessary rerenders.\nSelectors that return a new reference (such as an object or an array) should be memoized: https://redux.js.org/usage/deriving-data-selectors#optimizing-selectors-with-memoization",
                  {
                    state,
                    selected,
                    selected2: toCompare,
                    stack
                  }
                );
              }
            }
            if (finalIdentityFunctionCheck === "always" || finalIdentityFunctionCheck === "once" && firstRun.current) {
              if (selected === state) {
                let stack = void 0;
                try {
                  throw new Error();
                } catch (e) {
                  ;
                  ({ stack } = e);
                }
                console.warn(
                  "Selector " + (selector.name || "unknown") + " returned the root state when called. This can lead to unnecessary rerenders.\nSelectors that return the entire state are almost certainly a mistake, as they will cause a rerender whenever *anything* in state changes.",
                  { stack }
                );
              }
            }
            if (firstRun.current) firstRun.current = false;
          }
          return selected;
        }
      }[selector.name],
      [selector]
    );
    const selectedState = (0,use_sync_external_store_with_selector_js__WEBPACK_IMPORTED_MODULE_1__.useSyncExternalStoreWithSelector)(
      subscription.addNestedSub,
      store.getState,
      getServerState || store.getState,
      wrappedSelector,
      equalityFn
    );
    react__WEBPACK_IMPORTED_MODULE_0__.useDebugValue(selectedState);
    return selectedState;
  };
  Object.assign(useSelector2, {
    withTypes: () => useSelector2
  });
  return useSelector2;
}
var useSelector = /* @__PURE__ */ createSelectorHook();

// src/exports.ts
var batch = defaultNoopBatch;

//# sourceMappingURL=react-redux.mjs.map

/***/ },

/***/ "./node_modules/redux-thunk/dist/redux-thunk.mjs"
/*!*******************************************************!*\
  !*** ./node_modules/redux-thunk/dist/redux-thunk.mjs ***!
  \*******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   thunk: () => (/* binding */ thunk),
/* harmony export */   withExtraArgument: () => (/* binding */ withExtraArgument)
/* harmony export */ });
// src/index.ts
function createThunkMiddleware(extraArgument) {
  const middleware = ({ dispatch, getState }) => (next) => (action) => {
    if (typeof action === "function") {
      return action(dispatch, getState, extraArgument);
    }
    return next(action);
  };
  return middleware;
}
var thunk = createThunkMiddleware();
var withExtraArgument = createThunkMiddleware;



/***/ },

/***/ "./node_modules/redux/dist/redux.mjs"
/*!*******************************************!*\
  !*** ./node_modules/redux/dist/redux.mjs ***!
  \*******************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   __DO_NOT_USE__ActionTypes: () => (/* binding */ actionTypes_default),
/* harmony export */   applyMiddleware: () => (/* binding */ applyMiddleware),
/* harmony export */   bindActionCreators: () => (/* binding */ bindActionCreators),
/* harmony export */   combineReducers: () => (/* binding */ combineReducers),
/* harmony export */   compose: () => (/* binding */ compose),
/* harmony export */   createStore: () => (/* binding */ createStore),
/* harmony export */   isAction: () => (/* binding */ isAction),
/* harmony export */   isPlainObject: () => (/* binding */ isPlainObject),
/* harmony export */   legacy_createStore: () => (/* binding */ legacy_createStore)
/* harmony export */ });
// src/utils/formatProdErrorMessage.ts
function formatProdErrorMessage(code) {
  return `Minified Redux error #${code}; visit https://redux.js.org/Errors?code=${code} for the full message or use the non-minified dev environment for full errors. `;
}

// src/utils/symbol-observable.ts
var $$observable = /* @__PURE__ */ (() => typeof Symbol === "function" && Symbol.observable || "@@observable")();
var symbol_observable_default = $$observable;

// src/utils/actionTypes.ts
var randomString = () => Math.random().toString(36).substring(7).split("").join(".");
var ActionTypes = {
  INIT: `@@redux/INIT${/* @__PURE__ */ randomString()}`,
  REPLACE: `@@redux/REPLACE${/* @__PURE__ */ randomString()}`,
  PROBE_UNKNOWN_ACTION: () => `@@redux/PROBE_UNKNOWN_ACTION${randomString()}`
};
var actionTypes_default = ActionTypes;

// src/utils/isPlainObject.ts
function isPlainObject(obj) {
  if (typeof obj !== "object" || obj === null)
    return false;
  let proto = obj;
  while (Object.getPrototypeOf(proto) !== null) {
    proto = Object.getPrototypeOf(proto);
  }
  return Object.getPrototypeOf(obj) === proto || Object.getPrototypeOf(obj) === null;
}

// src/utils/kindOf.ts
function miniKindOf(val) {
  if (val === void 0)
    return "undefined";
  if (val === null)
    return "null";
  const type = typeof val;
  switch (type) {
    case "boolean":
    case "string":
    case "number":
    case "symbol":
    case "function": {
      return type;
    }
  }
  if (Array.isArray(val))
    return "array";
  if (isDate(val))
    return "date";
  if (isError(val))
    return "error";
  const constructorName = ctorName(val);
  switch (constructorName) {
    case "Symbol":
    case "Promise":
    case "WeakMap":
    case "WeakSet":
    case "Map":
    case "Set":
      return constructorName;
  }
  return Object.prototype.toString.call(val).slice(8, -1).toLowerCase().replace(/\s/g, "");
}
function ctorName(val) {
  return typeof val.constructor === "function" ? val.constructor.name : null;
}
function isError(val) {
  return val instanceof Error || typeof val.message === "string" && val.constructor && typeof val.constructor.stackTraceLimit === "number";
}
function isDate(val) {
  if (val instanceof Date)
    return true;
  return typeof val.toDateString === "function" && typeof val.getDate === "function" && typeof val.setDate === "function";
}
function kindOf(val) {
  let typeOfVal = typeof val;
  if (true) {
    typeOfVal = miniKindOf(val);
  }
  return typeOfVal;
}

// src/createStore.ts
function createStore(reducer, preloadedState, enhancer) {
  if (typeof reducer !== "function") {
    throw new Error( false ? 0 : `Expected the root reducer to be a function. Instead, received: '${kindOf(reducer)}'`);
  }
  if (typeof preloadedState === "function" && typeof enhancer === "function" || typeof enhancer === "function" && typeof arguments[3] === "function") {
    throw new Error( false ? 0 : "It looks like you are passing several store enhancers to createStore(). This is not supported. Instead, compose them together to a single function. See https://redux.js.org/tutorials/fundamentals/part-4-store#creating-a-store-with-enhancers for an example.");
  }
  if (typeof preloadedState === "function" && typeof enhancer === "undefined") {
    enhancer = preloadedState;
    preloadedState = void 0;
  }
  if (typeof enhancer !== "undefined") {
    if (typeof enhancer !== "function") {
      throw new Error( false ? 0 : `Expected the enhancer to be a function. Instead, received: '${kindOf(enhancer)}'`);
    }
    return enhancer(createStore)(reducer, preloadedState);
  }
  let currentReducer = reducer;
  let currentState = preloadedState;
  let currentListeners = /* @__PURE__ */ new Map();
  let nextListeners = currentListeners;
  let listenerIdCounter = 0;
  let isDispatching = false;
  function ensureCanMutateNextListeners() {
    if (nextListeners === currentListeners) {
      nextListeners = /* @__PURE__ */ new Map();
      currentListeners.forEach((listener, key) => {
        nextListeners.set(key, listener);
      });
    }
  }
  function getState() {
    if (isDispatching) {
      throw new Error( false ? 0 : "You may not call store.getState() while the reducer is executing. The reducer has already received the state as an argument. Pass it down from the top reducer instead of reading it from the store.");
    }
    return currentState;
  }
  function subscribe(listener) {
    if (typeof listener !== "function") {
      throw new Error( false ? 0 : `Expected the listener to be a function. Instead, received: '${kindOf(listener)}'`);
    }
    if (isDispatching) {
      throw new Error( false ? 0 : "You may not call store.subscribe() while the reducer is executing. If you would like to be notified after the store has been updated, subscribe from a component and invoke store.getState() in the callback to access the latest state. See https://redux.js.org/api/store#subscribelistener for more details.");
    }
    let isSubscribed = true;
    ensureCanMutateNextListeners();
    const listenerId = listenerIdCounter++;
    nextListeners.set(listenerId, listener);
    return function unsubscribe() {
      if (!isSubscribed) {
        return;
      }
      if (isDispatching) {
        throw new Error( false ? 0 : "You may not unsubscribe from a store listener while the reducer is executing. See https://redux.js.org/api/store#subscribelistener for more details.");
      }
      isSubscribed = false;
      ensureCanMutateNextListeners();
      nextListeners.delete(listenerId);
      currentListeners = null;
    };
  }
  function dispatch(action) {
    if (!isPlainObject(action)) {
      throw new Error( false ? 0 : `Actions must be plain objects. Instead, the actual type was: '${kindOf(action)}'. You may need to add middleware to your store setup to handle dispatching other values, such as 'redux-thunk' to handle dispatching functions. See https://redux.js.org/tutorials/fundamentals/part-4-store#middleware and https://redux.js.org/tutorials/fundamentals/part-6-async-logic#using-the-redux-thunk-middleware for examples.`);
    }
    if (typeof action.type === "undefined") {
      throw new Error( false ? 0 : 'Actions may not have an undefined "type" property. You may have misspelled an action type string constant.');
    }
    if (typeof action.type !== "string") {
      throw new Error( false ? 0 : `Action "type" property must be a string. Instead, the actual type was: '${kindOf(action.type)}'. Value was: '${action.type}' (stringified)`);
    }
    if (isDispatching) {
      throw new Error( false ? 0 : "Reducers may not dispatch actions.");
    }
    try {
      isDispatching = true;
      currentState = currentReducer(currentState, action);
    } finally {
      isDispatching = false;
    }
    const listeners = currentListeners = nextListeners;
    listeners.forEach((listener) => {
      listener();
    });
    return action;
  }
  function replaceReducer(nextReducer) {
    if (typeof nextReducer !== "function") {
      throw new Error( false ? 0 : `Expected the nextReducer to be a function. Instead, received: '${kindOf(nextReducer)}`);
    }
    currentReducer = nextReducer;
    dispatch({
      type: actionTypes_default.REPLACE
    });
  }
  function observable() {
    const outerSubscribe = subscribe;
    return {
      /**
       * The minimal observable subscription method.
       * @param observer Any object that can be used as an observer.
       * The observer object should have a `next` method.
       * @returns An object with an `unsubscribe` method that can
       * be used to unsubscribe the observable from the store, and prevent further
       * emission of values from the observable.
       */
      subscribe(observer) {
        if (typeof observer !== "object" || observer === null) {
          throw new Error( false ? 0 : `Expected the observer to be an object. Instead, received: '${kindOf(observer)}'`);
        }
        function observeState() {
          const observerAsObserver = observer;
          if (observerAsObserver.next) {
            observerAsObserver.next(getState());
          }
        }
        observeState();
        const unsubscribe = outerSubscribe(observeState);
        return {
          unsubscribe
        };
      },
      [symbol_observable_default]() {
        return this;
      }
    };
  }
  dispatch({
    type: actionTypes_default.INIT
  });
  const store = {
    dispatch,
    subscribe,
    getState,
    replaceReducer,
    [symbol_observable_default]: observable
  };
  return store;
}
function legacy_createStore(reducer, preloadedState, enhancer) {
  return createStore(reducer, preloadedState, enhancer);
}

// src/utils/warning.ts
function warning(message) {
  if (typeof console !== "undefined" && typeof console.error === "function") {
    console.error(message);
  }
  try {
    throw new Error(message);
  } catch (e) {
  }
}

// src/combineReducers.ts
function getUnexpectedStateShapeWarningMessage(inputState, reducers, action, unexpectedKeyCache) {
  const reducerKeys = Object.keys(reducers);
  const argumentName = action && action.type === actionTypes_default.INIT ? "preloadedState argument passed to createStore" : "previous state received by the reducer";
  if (reducerKeys.length === 0) {
    return "Store does not have a valid reducer. Make sure the argument passed to combineReducers is an object whose values are reducers.";
  }
  if (!isPlainObject(inputState)) {
    return `The ${argumentName} has unexpected type of "${kindOf(inputState)}". Expected argument to be an object with the following keys: "${reducerKeys.join('", "')}"`;
  }
  const unexpectedKeys = Object.keys(inputState).filter((key) => !reducers.hasOwnProperty(key) && !unexpectedKeyCache[key]);
  unexpectedKeys.forEach((key) => {
    unexpectedKeyCache[key] = true;
  });
  if (action && action.type === actionTypes_default.REPLACE)
    return;
  if (unexpectedKeys.length > 0) {
    return `Unexpected ${unexpectedKeys.length > 1 ? "keys" : "key"} "${unexpectedKeys.join('", "')}" found in ${argumentName}. Expected to find one of the known reducer keys instead: "${reducerKeys.join('", "')}". Unexpected keys will be ignored.`;
  }
}
function assertReducerShape(reducers) {
  Object.keys(reducers).forEach((key) => {
    const reducer = reducers[key];
    const initialState = reducer(void 0, {
      type: actionTypes_default.INIT
    });
    if (typeof initialState === "undefined") {
      throw new Error( false ? 0 : `The slice reducer for key "${key}" returned undefined during initialization. If the state passed to the reducer is undefined, you must explicitly return the initial state. The initial state may not be undefined. If you don't want to set a value for this reducer, you can use null instead of undefined.`);
    }
    if (typeof reducer(void 0, {
      type: actionTypes_default.PROBE_UNKNOWN_ACTION()
    }) === "undefined") {
      throw new Error( false ? 0 : `The slice reducer for key "${key}" returned undefined when probed with a random type. Don't try to handle '${actionTypes_default.INIT}' or other actions in "redux/*" namespace. They are considered private. Instead, you must return the current state for any unknown actions, unless it is undefined, in which case you must return the initial state, regardless of the action type. The initial state may not be undefined, but can be null.`);
    }
  });
}
function combineReducers(reducers) {
  const reducerKeys = Object.keys(reducers);
  const finalReducers = {};
  for (let i = 0; i < reducerKeys.length; i++) {
    const key = reducerKeys[i];
    if (true) {
      if (typeof reducers[key] === "undefined") {
        warning(`No reducer provided for key "${key}"`);
      }
    }
    if (typeof reducers[key] === "function") {
      finalReducers[key] = reducers[key];
    }
  }
  const finalReducerKeys = Object.keys(finalReducers);
  let unexpectedKeyCache;
  if (true) {
    unexpectedKeyCache = {};
  }
  let shapeAssertionError;
  try {
    assertReducerShape(finalReducers);
  } catch (e) {
    shapeAssertionError = e;
  }
  return function combination(state = {}, action) {
    if (shapeAssertionError) {
      throw shapeAssertionError;
    }
    if (true) {
      const warningMessage = getUnexpectedStateShapeWarningMessage(state, finalReducers, action, unexpectedKeyCache);
      if (warningMessage) {
        warning(warningMessage);
      }
    }
    let hasChanged = false;
    const nextState = {};
    for (let i = 0; i < finalReducerKeys.length; i++) {
      const key = finalReducerKeys[i];
      const reducer = finalReducers[key];
      const previousStateForKey = state[key];
      const nextStateForKey = reducer(previousStateForKey, action);
      if (typeof nextStateForKey === "undefined") {
        const actionType = action && action.type;
        throw new Error( false ? 0 : `When called with an action of type ${actionType ? `"${String(actionType)}"` : "(unknown type)"}, the slice reducer for key "${key}" returned undefined. To ignore an action, you must explicitly return the previous state. If you want this reducer to hold no value, you can return null instead of undefined.`);
      }
      nextState[key] = nextStateForKey;
      hasChanged = hasChanged || nextStateForKey !== previousStateForKey;
    }
    hasChanged = hasChanged || finalReducerKeys.length !== Object.keys(state).length;
    return hasChanged ? nextState : state;
  };
}

// src/bindActionCreators.ts
function bindActionCreator(actionCreator, dispatch) {
  return function(...args) {
    return dispatch(actionCreator.apply(this, args));
  };
}
function bindActionCreators(actionCreators, dispatch) {
  if (typeof actionCreators === "function") {
    return bindActionCreator(actionCreators, dispatch);
  }
  if (typeof actionCreators !== "object" || actionCreators === null) {
    throw new Error( false ? 0 : `bindActionCreators expected an object or a function, but instead received: '${kindOf(actionCreators)}'. Did you write "import ActionCreators from" instead of "import * as ActionCreators from"?`);
  }
  const boundActionCreators = {};
  for (const key in actionCreators) {
    const actionCreator = actionCreators[key];
    if (typeof actionCreator === "function") {
      boundActionCreators[key] = bindActionCreator(actionCreator, dispatch);
    }
  }
  return boundActionCreators;
}

// src/compose.ts
function compose(...funcs) {
  if (funcs.length === 0) {
    return (arg) => arg;
  }
  if (funcs.length === 1) {
    return funcs[0];
  }
  return funcs.reduce((a, b) => (...args) => a(b(...args)));
}

// src/applyMiddleware.ts
function applyMiddleware(...middlewares) {
  return (createStore2) => (reducer, preloadedState) => {
    const store = createStore2(reducer, preloadedState);
    let dispatch = () => {
      throw new Error( false ? 0 : "Dispatching while constructing your middleware is not allowed. Other middleware would not be applied to this dispatch.");
    };
    const middlewareAPI = {
      getState: store.getState,
      dispatch: (action, ...args) => dispatch(action, ...args)
    };
    const chain = middlewares.map((middleware) => middleware(middlewareAPI));
    dispatch = compose(...chain)(store.dispatch);
    return {
      ...store,
      dispatch
    };
  };
}

// src/utils/isAction.ts
function isAction(action) {
  return isPlainObject(action) && "type" in action && typeof action.type === "string";
}

//# sourceMappingURL=redux.mjs.map

/***/ },

/***/ "./node_modules/reselect/dist/reselect.mjs"
/*!*************************************************!*\
  !*** ./node_modules/reselect/dist/reselect.mjs ***!
  \*************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createSelector: () => (/* binding */ createSelector),
/* harmony export */   createSelectorCreator: () => (/* binding */ createSelectorCreator),
/* harmony export */   createStructuredSelector: () => (/* binding */ createStructuredSelector),
/* harmony export */   lruMemoize: () => (/* binding */ lruMemoize),
/* harmony export */   referenceEqualityCheck: () => (/* binding */ referenceEqualityCheck),
/* harmony export */   setGlobalDevModeChecks: () => (/* binding */ setGlobalDevModeChecks),
/* harmony export */   unstable_autotrackMemoize: () => (/* binding */ autotrackMemoize),
/* harmony export */   weakMapMemoize: () => (/* binding */ weakMapMemoize)
/* harmony export */ });
// src/devModeChecks/identityFunctionCheck.ts
var runIdentityFunctionCheck = (resultFunc, inputSelectorsResults, outputSelectorResult) => {
  if (inputSelectorsResults.length === 1 && inputSelectorsResults[0] === outputSelectorResult) {
    let isInputSameAsOutput = false;
    try {
      const emptyObject = {};
      if (resultFunc(emptyObject) === emptyObject)
        isInputSameAsOutput = true;
    } catch {
    }
    if (isInputSameAsOutput) {
      let stack = void 0;
      try {
        throw new Error();
      } catch (e) {
        ;
        ({ stack } = e);
      }
      console.warn(
        "The result function returned its own inputs without modification. e.g\n`createSelector([state => state.todos], todos => todos)`\nThis could lead to inefficient memoization and unnecessary re-renders.\nEnsure transformation logic is in the result function, and extraction logic is in the input selectors.",
        { stack }
      );
    }
  }
};

// src/devModeChecks/inputStabilityCheck.ts
var runInputStabilityCheck = (inputSelectorResultsObject, options, inputSelectorArgs) => {
  const { memoize, memoizeOptions } = options;
  const { inputSelectorResults, inputSelectorResultsCopy } = inputSelectorResultsObject;
  const createAnEmptyObject = memoize(() => ({}), ...memoizeOptions);
  const areInputSelectorResultsEqual = createAnEmptyObject.apply(null, inputSelectorResults) === createAnEmptyObject.apply(null, inputSelectorResultsCopy);
  if (!areInputSelectorResultsEqual) {
    let stack = void 0;
    try {
      throw new Error();
    } catch (e) {
      ;
      ({ stack } = e);
    }
    console.warn(
      "An input selector returned a different result when passed same arguments.\nThis means your output selector will likely run more frequently than intended.\nAvoid returning a new reference inside your input selector, e.g.\n`createSelector([state => state.todos.map(todo => todo.id)], todoIds => todoIds.length)`",
      {
        arguments: inputSelectorArgs,
        firstInputs: inputSelectorResults,
        secondInputs: inputSelectorResultsCopy,
        stack
      }
    );
  }
};

// src/devModeChecks/setGlobalDevModeChecks.ts
var globalDevModeChecks = {
  inputStabilityCheck: "once",
  identityFunctionCheck: "once"
};
var setGlobalDevModeChecks = (devModeChecks) => {
  Object.assign(globalDevModeChecks, devModeChecks);
};

// src/utils.ts
var NOT_FOUND = /* @__PURE__ */ Symbol("NOT_FOUND");
function assertIsFunction(func, errorMessage = `expected a function, instead received ${typeof func}`) {
  if (typeof func !== "function") {
    throw new TypeError(errorMessage);
  }
}
function assertIsObject(object, errorMessage = `expected an object, instead received ${typeof object}`) {
  if (typeof object !== "object") {
    throw new TypeError(errorMessage);
  }
}
function assertIsArrayOfFunctions(array, errorMessage = `expected all items to be functions, instead received the following types: `) {
  if (!array.every((item) => typeof item === "function")) {
    const itemTypes = array.map(
      (item) => typeof item === "function" ? `function ${item.name || "unnamed"}()` : typeof item
    ).join(", ");
    throw new TypeError(`${errorMessage}[${itemTypes}]`);
  }
}
var ensureIsArray = (item) => {
  return Array.isArray(item) ? item : [item];
};
function getDependencies(createSelectorArgs) {
  const dependencies = Array.isArray(createSelectorArgs[0]) ? createSelectorArgs[0] : createSelectorArgs;
  assertIsArrayOfFunctions(
    dependencies,
    `createSelector expects all input-selectors to be functions, but received the following types: `
  );
  return dependencies;
}
function collectInputSelectorResults(dependencies, inputSelectorArgs) {
  const inputSelectorResults = [];
  const { length } = dependencies;
  for (let i = 0; i < length; i++) {
    inputSelectorResults.push(dependencies[i].apply(null, inputSelectorArgs));
  }
  return inputSelectorResults;
}
var getDevModeChecksExecutionInfo = (firstRun, devModeChecks) => {
  const { identityFunctionCheck, inputStabilityCheck } = {
    ...globalDevModeChecks,
    ...devModeChecks
  };
  return {
    identityFunctionCheck: {
      shouldRun: identityFunctionCheck === "always" || identityFunctionCheck === "once" && firstRun,
      run: runIdentityFunctionCheck
    },
    inputStabilityCheck: {
      shouldRun: inputStabilityCheck === "always" || inputStabilityCheck === "once" && firstRun,
      run: runInputStabilityCheck
    }
  };
};

// src/autotrackMemoize/autotracking.ts
var $REVISION = 0;
var CURRENT_TRACKER = null;
var Cell = class {
  revision = $REVISION;
  _value;
  _lastValue;
  _isEqual = tripleEq;
  constructor(initialValue, isEqual = tripleEq) {
    this._value = this._lastValue = initialValue;
    this._isEqual = isEqual;
  }
  // Whenever a storage value is read, it'll add itself to the current tracker if
  // one exists, entangling its state with that cache.
  get value() {
    CURRENT_TRACKER?.add(this);
    return this._value;
  }
  // Whenever a storage value is updated, we bump the global revision clock,
  // assign the revision for this storage to the new value, _and_ we schedule a
  // rerender. This is important, and it's what makes autotracking  _pull_
  // based. We don't actively tell the caches which depend on the storage that
  // anything has happened. Instead, we recompute the caches when needed.
  set value(newValue) {
    if (this.value === newValue)
      return;
    this._value = newValue;
    this.revision = ++$REVISION;
  }
};
function tripleEq(a, b) {
  return a === b;
}
var TrackingCache = class {
  _cachedValue;
  _cachedRevision = -1;
  _deps = [];
  hits = 0;
  fn;
  constructor(fn) {
    this.fn = fn;
  }
  clear() {
    this._cachedValue = void 0;
    this._cachedRevision = -1;
    this._deps = [];
    this.hits = 0;
  }
  get value() {
    if (this.revision > this._cachedRevision) {
      const { fn } = this;
      const currentTracker = /* @__PURE__ */ new Set();
      const prevTracker = CURRENT_TRACKER;
      CURRENT_TRACKER = currentTracker;
      this._cachedValue = fn();
      CURRENT_TRACKER = prevTracker;
      this.hits++;
      this._deps = Array.from(currentTracker);
      this._cachedRevision = this.revision;
    }
    CURRENT_TRACKER?.add(this);
    return this._cachedValue;
  }
  get revision() {
    return Math.max(...this._deps.map((d) => d.revision), 0);
  }
};
function getValue(cell) {
  if (!(cell instanceof Cell)) {
    console.warn("Not a valid cell! ", cell);
  }
  return cell.value;
}
function setValue(storage, value) {
  if (!(storage instanceof Cell)) {
    throw new TypeError(
      "setValue must be passed a tracked store created with `createStorage`."
    );
  }
  storage.value = storage._lastValue = value;
}
function createCell(initialValue, isEqual = tripleEq) {
  return new Cell(initialValue, isEqual);
}
function createCache(fn) {
  assertIsFunction(
    fn,
    "the first parameter to `createCache` must be a function"
  );
  return new TrackingCache(fn);
}

// src/autotrackMemoize/tracking.ts
var neverEq = (a, b) => false;
function createTag() {
  return createCell(null, neverEq);
}
function dirtyTag(tag, value) {
  setValue(tag, value);
}
var consumeCollection = (node) => {
  let tag = node.collectionTag;
  if (tag === null) {
    tag = node.collectionTag = createTag();
  }
  getValue(tag);
};
var dirtyCollection = (node) => {
  const tag = node.collectionTag;
  if (tag !== null) {
    dirtyTag(tag, null);
  }
};

// src/autotrackMemoize/proxy.ts
var REDUX_PROXY_LABEL = Symbol();
var nextId = 0;
var proto = Object.getPrototypeOf({});
var ObjectTreeNode = class {
  constructor(value) {
    this.value = value;
    this.value = value;
    this.tag.value = value;
  }
  proxy = new Proxy(this, objectProxyHandler);
  tag = createTag();
  tags = {};
  children = {};
  collectionTag = null;
  id = nextId++;
};
var objectProxyHandler = {
  get(node, key) {
    function calculateResult() {
      const { value } = node;
      const childValue = Reflect.get(value, key);
      if (typeof key === "symbol") {
        return childValue;
      }
      if (key in proto) {
        return childValue;
      }
      if (typeof childValue === "object" && childValue !== null) {
        let childNode = node.children[key];
        if (childNode === void 0) {
          childNode = node.children[key] = createNode(childValue);
        }
        if (childNode.tag) {
          getValue(childNode.tag);
        }
        return childNode.proxy;
      } else {
        let tag = node.tags[key];
        if (tag === void 0) {
          tag = node.tags[key] = createTag();
          tag.value = childValue;
        }
        getValue(tag);
        return childValue;
      }
    }
    const res = calculateResult();
    return res;
  },
  ownKeys(node) {
    consumeCollection(node);
    return Reflect.ownKeys(node.value);
  },
  getOwnPropertyDescriptor(node, prop) {
    return Reflect.getOwnPropertyDescriptor(node.value, prop);
  },
  has(node, prop) {
    return Reflect.has(node.value, prop);
  }
};
var ArrayTreeNode = class {
  constructor(value) {
    this.value = value;
    this.value = value;
    this.tag.value = value;
  }
  proxy = new Proxy([this], arrayProxyHandler);
  tag = createTag();
  tags = {};
  children = {};
  collectionTag = null;
  id = nextId++;
};
var arrayProxyHandler = {
  get([node], key) {
    if (key === "length") {
      consumeCollection(node);
    }
    return objectProxyHandler.get(node, key);
  },
  ownKeys([node]) {
    return objectProxyHandler.ownKeys(node);
  },
  getOwnPropertyDescriptor([node], prop) {
    return objectProxyHandler.getOwnPropertyDescriptor(node, prop);
  },
  has([node], prop) {
    return objectProxyHandler.has(node, prop);
  }
};
function createNode(value) {
  if (Array.isArray(value)) {
    return new ArrayTreeNode(value);
  }
  return new ObjectTreeNode(value);
}
function updateNode(node, newValue) {
  const { value, tags, children } = node;
  node.value = newValue;
  if (Array.isArray(value) && Array.isArray(newValue) && value.length !== newValue.length) {
    dirtyCollection(node);
  } else {
    if (value !== newValue) {
      let oldKeysSize = 0;
      let newKeysSize = 0;
      let anyKeysAdded = false;
      for (const _key in value) {
        oldKeysSize++;
      }
      for (const key in newValue) {
        newKeysSize++;
        if (!(key in value)) {
          anyKeysAdded = true;
          break;
        }
      }
      const isDifferent = anyKeysAdded || oldKeysSize !== newKeysSize;
      if (isDifferent) {
        dirtyCollection(node);
      }
    }
  }
  for (const key in tags) {
    const childValue = value[key];
    const newChildValue = newValue[key];
    if (childValue !== newChildValue) {
      dirtyCollection(node);
      dirtyTag(tags[key], newChildValue);
    }
    if (typeof newChildValue === "object" && newChildValue !== null) {
      delete tags[key];
    }
  }
  for (const key in children) {
    const childNode = children[key];
    const newChildValue = newValue[key];
    const childValue = childNode.value;
    if (childValue === newChildValue) {
      continue;
    } else if (typeof newChildValue === "object" && newChildValue !== null) {
      updateNode(childNode, newChildValue);
    } else {
      deleteNode(childNode);
      delete children[key];
    }
  }
}
function deleteNode(node) {
  if (node.tag) {
    dirtyTag(node.tag, null);
  }
  dirtyCollection(node);
  for (const key in node.tags) {
    dirtyTag(node.tags[key], null);
  }
  for (const key in node.children) {
    deleteNode(node.children[key]);
  }
}

// src/lruMemoize.ts
function createSingletonCache(equals) {
  let entry;
  return {
    get(key) {
      if (entry && equals(entry.key, key)) {
        return entry.value;
      }
      return NOT_FOUND;
    },
    put(key, value) {
      entry = { key, value };
    },
    getEntries() {
      return entry ? [entry] : [];
    },
    clear() {
      entry = void 0;
    }
  };
}
function createLruCache(maxSize, equals) {
  let entries = [];
  function get(key) {
    const cacheIndex = entries.findIndex((entry) => equals(key, entry.key));
    if (cacheIndex > -1) {
      const entry = entries[cacheIndex];
      if (cacheIndex > 0) {
        entries.splice(cacheIndex, 1);
        entries.unshift(entry);
      }
      return entry.value;
    }
    return NOT_FOUND;
  }
  function put(key, value) {
    if (get(key) === NOT_FOUND) {
      entries.unshift({ key, value });
      if (entries.length > maxSize) {
        entries.pop();
      }
    }
  }
  function getEntries() {
    return entries;
  }
  function clear() {
    entries = [];
  }
  return { get, put, getEntries, clear };
}
var referenceEqualityCheck = (a, b) => a === b;
function createCacheKeyComparator(equalityCheck) {
  return function areArgumentsShallowlyEqual(prev, next) {
    if (prev === null || next === null || prev.length !== next.length) {
      return false;
    }
    const { length } = prev;
    for (let i = 0; i < length; i++) {
      if (!equalityCheck(prev[i], next[i])) {
        return false;
      }
    }
    return true;
  };
}
function lruMemoize(func, equalityCheckOrOptions) {
  const providedOptions = typeof equalityCheckOrOptions === "object" ? equalityCheckOrOptions : { equalityCheck: equalityCheckOrOptions };
  const {
    equalityCheck = referenceEqualityCheck,
    maxSize = 1,
    resultEqualityCheck
  } = providedOptions;
  const comparator = createCacheKeyComparator(equalityCheck);
  let resultsCount = 0;
  const cache = maxSize <= 1 ? createSingletonCache(comparator) : createLruCache(maxSize, comparator);
  function memoized() {
    let value = cache.get(arguments);
    if (value === NOT_FOUND) {
      value = func.apply(null, arguments);
      resultsCount++;
      if (resultEqualityCheck) {
        const entries = cache.getEntries();
        const matchingEntry = entries.find(
          (entry) => resultEqualityCheck(entry.value, value)
        );
        if (matchingEntry) {
          value = matchingEntry.value;
          resultsCount !== 0 && resultsCount--;
        }
      }
      cache.put(arguments, value);
    }
    return value;
  }
  memoized.clearCache = () => {
    cache.clear();
    memoized.resetResultsCount();
  };
  memoized.resultsCount = () => resultsCount;
  memoized.resetResultsCount = () => {
    resultsCount = 0;
  };
  return memoized;
}

// src/autotrackMemoize/autotrackMemoize.ts
function autotrackMemoize(func) {
  const node = createNode(
    []
  );
  let lastArgs = null;
  const shallowEqual = createCacheKeyComparator(referenceEqualityCheck);
  const cache = createCache(() => {
    const res = func.apply(null, node.proxy);
    return res;
  });
  function memoized() {
    if (!shallowEqual(lastArgs, arguments)) {
      updateNode(node, arguments);
      lastArgs = arguments;
    }
    return cache.value;
  }
  memoized.clearCache = () => {
    return cache.clear();
  };
  return memoized;
}

// src/weakMapMemoize.ts
var StrongRef = class {
  constructor(value) {
    this.value = value;
  }
  deref() {
    return this.value;
  }
};
var Ref = typeof WeakRef !== "undefined" ? WeakRef : StrongRef;
var UNTERMINATED = 0;
var TERMINATED = 1;
function createCacheNode() {
  return {
    s: UNTERMINATED,
    v: void 0,
    o: null,
    p: null
  };
}
function weakMapMemoize(func, options = {}) {
  let fnNode = createCacheNode();
  const { resultEqualityCheck } = options;
  let lastResult;
  let resultsCount = 0;
  function memoized() {
    let cacheNode = fnNode;
    const { length } = arguments;
    for (let i = 0, l = length; i < l; i++) {
      const arg = arguments[i];
      if (typeof arg === "function" || typeof arg === "object" && arg !== null) {
        let objectCache = cacheNode.o;
        if (objectCache === null) {
          cacheNode.o = objectCache = /* @__PURE__ */ new WeakMap();
        }
        const objectNode = objectCache.get(arg);
        if (objectNode === void 0) {
          cacheNode = createCacheNode();
          objectCache.set(arg, cacheNode);
        } else {
          cacheNode = objectNode;
        }
      } else {
        let primitiveCache = cacheNode.p;
        if (primitiveCache === null) {
          cacheNode.p = primitiveCache = /* @__PURE__ */ new Map();
        }
        const primitiveNode = primitiveCache.get(arg);
        if (primitiveNode === void 0) {
          cacheNode = createCacheNode();
          primitiveCache.set(arg, cacheNode);
        } else {
          cacheNode = primitiveNode;
        }
      }
    }
    const terminatedNode = cacheNode;
    let result;
    if (cacheNode.s === TERMINATED) {
      result = cacheNode.v;
    } else {
      result = func.apply(null, arguments);
      resultsCount++;
      if (resultEqualityCheck) {
        const lastResultValue = lastResult?.deref?.() ?? lastResult;
        if (lastResultValue != null && resultEqualityCheck(lastResultValue, result)) {
          result = lastResultValue;
          resultsCount !== 0 && resultsCount--;
        }
        const needsWeakRef = typeof result === "object" && result !== null || typeof result === "function";
        lastResult = needsWeakRef ? new Ref(result) : result;
      }
    }
    terminatedNode.s = TERMINATED;
    terminatedNode.v = result;
    return result;
  }
  memoized.clearCache = () => {
    fnNode = createCacheNode();
    memoized.resetResultsCount();
  };
  memoized.resultsCount = () => resultsCount;
  memoized.resetResultsCount = () => {
    resultsCount = 0;
  };
  return memoized;
}

// src/createSelectorCreator.ts
function createSelectorCreator(memoizeOrOptions, ...memoizeOptionsFromArgs) {
  const createSelectorCreatorOptions = typeof memoizeOrOptions === "function" ? {
    memoize: memoizeOrOptions,
    memoizeOptions: memoizeOptionsFromArgs
  } : memoizeOrOptions;
  const createSelector2 = (...createSelectorArgs) => {
    let recomputations = 0;
    let dependencyRecomputations = 0;
    let lastResult;
    let directlyPassedOptions = {};
    let resultFunc = createSelectorArgs.pop();
    if (typeof resultFunc === "object") {
      directlyPassedOptions = resultFunc;
      resultFunc = createSelectorArgs.pop();
    }
    assertIsFunction(
      resultFunc,
      `createSelector expects an output function after the inputs, but received: [${typeof resultFunc}]`
    );
    const combinedOptions = {
      ...createSelectorCreatorOptions,
      ...directlyPassedOptions
    };
    const {
      memoize,
      memoizeOptions = [],
      argsMemoize = weakMapMemoize,
      argsMemoizeOptions = [],
      devModeChecks = {}
    } = combinedOptions;
    const finalMemoizeOptions = ensureIsArray(memoizeOptions);
    const finalArgsMemoizeOptions = ensureIsArray(argsMemoizeOptions);
    const dependencies = getDependencies(createSelectorArgs);
    const memoizedResultFunc = memoize(function recomputationWrapper() {
      recomputations++;
      return resultFunc.apply(
        null,
        arguments
      );
    }, ...finalMemoizeOptions);
    let firstRun = true;
    const selector = argsMemoize(function dependenciesChecker() {
      dependencyRecomputations++;
      const inputSelectorResults = collectInputSelectorResults(
        dependencies,
        arguments
      );
      lastResult = memoizedResultFunc.apply(null, inputSelectorResults);
      if (true) {
        const { identityFunctionCheck, inputStabilityCheck } = getDevModeChecksExecutionInfo(firstRun, devModeChecks);
        if (identityFunctionCheck.shouldRun) {
          identityFunctionCheck.run(
            resultFunc,
            inputSelectorResults,
            lastResult
          );
        }
        if (inputStabilityCheck.shouldRun) {
          const inputSelectorResultsCopy = collectInputSelectorResults(
            dependencies,
            arguments
          );
          inputStabilityCheck.run(
            { inputSelectorResults, inputSelectorResultsCopy },
            { memoize, memoizeOptions: finalMemoizeOptions },
            arguments
          );
        }
        if (firstRun)
          firstRun = false;
      }
      return lastResult;
    }, ...finalArgsMemoizeOptions);
    return Object.assign(selector, {
      resultFunc,
      memoizedResultFunc,
      dependencies,
      dependencyRecomputations: () => dependencyRecomputations,
      resetDependencyRecomputations: () => {
        dependencyRecomputations = 0;
      },
      lastResult: () => lastResult,
      recomputations: () => recomputations,
      resetRecomputations: () => {
        recomputations = 0;
      },
      memoize,
      argsMemoize
    });
  };
  Object.assign(createSelector2, {
    withTypes: () => createSelector2
  });
  return createSelector2;
}
var createSelector = /* @__PURE__ */ createSelectorCreator(weakMapMemoize);

// src/createStructuredSelector.ts
var createStructuredSelector = Object.assign(
  (inputSelectorsObject, selectorCreator = createSelector) => {
    assertIsObject(
      inputSelectorsObject,
      `createStructuredSelector expects first argument to be an object where each property is a selector, instead received a ${typeof inputSelectorsObject}`
    );
    const inputSelectorKeys = Object.keys(inputSelectorsObject);
    const dependencies = inputSelectorKeys.map(
      (key) => inputSelectorsObject[key]
    );
    const structuredSelector = selectorCreator(
      dependencies,
      (...inputSelectorResults) => {
        return inputSelectorResults.reduce((composition, value, index) => {
          composition[inputSelectorKeys[index]] = value;
          return composition;
        }, {});
      }
    );
    return structuredSelector;
  },
  { withTypes: () => createStructuredSelector }
);

//# sourceMappingURL=reselect.mjs.map

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************************************!*\
  !*** ./assets/src/string-translate/index.js ***!
  \**********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _App__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./App */ "./assets/src/string-translate/App.js");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-dom */ "react-dom");
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_dom__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _index_css__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./index.css */ "./assets/src/string-translate/index.css");
/* harmony import */ var _redux_store_store__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./redux-store/store */ "./assets/src/string-translate/redux-store/store.js");
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react-redux */ "./node_modules/react-redux/dist/react-redux.mjs");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _components_loop_callback__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./components/loop-callback */ "./assets/src/string-translate/components/loop-callback/index.js");
/* harmony import */ var _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./components/translate-provider/local-ai/local-ai-translate */ "./assets/src/string-translate/components/translate-provider/local-ai/local-ai-translate.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);










(() => {
  const BulkTranslate = props => {
    const [modalVisible, setModalVisible] = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
    const [postIds, setPostIds] = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)([]);
    const prefix = props.prefix;
    const wrapper = document.getElementById(`${prefix}-wrapper`);
    let localAiCheckInProgres = false;
    const handleModalVisibility = e => {
      e.preventDefault();
      setModalVisible(prev => !prev);
      destroyGoogleWidget();
    };

    // 1️⃣ Clear old cached data on page load
    const clearOldTranslatorCacheOnLoad = () => {
      const loadKey = "automlp_wpml_LOCAL_AI_PAGE_LOADED";
      if (sessionStorage.getItem(loadKey)) {
        return;
      }
      localStorage.removeItem("automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES");
      sessionStorage.setItem(loadKey, "1");
    };

    // 2️⃣ Language pack availability check (gesture-based)
    const checkLanguagePackAvailability = async () => {
      const languagesObj = {
        ...automlp_wpml_bulk_translate_object.languageObject
      };
      const supportedLanguages = _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_8__["default"].supportedLanguages || [];
      delete languagesObj.en;
      let savedLanguages = [];
      try {
        savedLanguages = JSON.parse(localStorage.getItem("automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES")) || [];
      } catch {
        savedLanguages = [];
      }
      savedLanguages.forEach(lang => delete languagesObj[lang]);
      if (!Object.keys(languagesObj).length) return;
      const processNextLanguage = async () => {
        if (localAiCheckInProgres || Object.keys(languagesObj).length === 0) return;
        localAiCheckInProgres = true;
        const targetLang = Object.keys(languagesObj)[0];
        if (supportedLanguages.includes(targetLang)) {
          try {
            const status = await _components_translate_provider_local_ai_local_ai_translate__WEBPACK_IMPORTED_MODULE_8__["default"].languagePairAvality("en", targetLang);
            if (["available", "readily"].includes(status)) {
              delete languagesObj[targetLang];
              savedLanguages.push(targetLang);
              localStorage.setItem("automlp_wpml_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES", JSON.stringify(savedLanguages));
            }
          } catch (err) {
            console.error("Language availability check failed:", targetLang, err);
          }
        } else {
          delete languagesObj[targetLang];
        }
        localAiCheckInProgres = false;
        if (Object.keys(languagesObj).length === 0) {
          document.removeEventListener("mousemove", onMouseMove);
          const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
          doActionsBtn.forEach(btn => {
            btn.removeEventListener("mousemove", checkLanguagePackAvailability);
            btn.removeEventListener("mouseleave", checkLanguagePackAvailability);
            btn.removeEventListener("mouseenter", checkLanguagePackAvailability);
          });
        }
      };
      const onMouseMove = () => {
        processNextLanguage();
      };
      document.addEventListener("mousemove", onMouseMove);
    };
    const bulkTranslationHandler = e => {
      e.preventDefault();

      // Check if we're on the String Translation page
      const isStringTranslationPage = window.location.href.indexOf("wpml-string-translation") !== -1;
      let postIds = [];
      let stringFilters = {};
      if (isStringTranslationPage) {
        const stringTable = document.querySelector("#icl_string_translations") || document.querySelector("table.js-wpml-st-table");

        // All checkboxes in the table (whether checked or not)
        const allCheckboxes = stringTable ? stringTable.querySelectorAll("input.wpml-checkbox-native, input.js-icl-st-row-cb") : [];

        // Only the checked ones
        const checked = stringTable ? stringTable.querySelectorAll("input.wpml-checkbox-native:checked, input.js-icl-st-row-cb:checked") : [];

        // If nothing is checked, treat ALL rows as selected
        const effectiveSelection = checked && checked.length > 0 ? checked : allCheckboxes;
        const selectedStringIds = [];
        const selectedStringsMap = {};
        const stringLanguageStatus = {};
        Array.from(effectiveSelection).forEach(el => {
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
            langCell.querySelectorAll('i[id]').forEach(icon => {
              const idAttr = icon.getAttribute('id') || '';
              const dash = idAttr.indexOf('-');
              if (dash > 0) {
                const lang = idAttr.slice(dash + 1);
                stringLanguageStatus[id][lang] = icon.classList.contains('otgs-ico-edit') ? 'edit' : 'add';
              }
            });
          }
        });
        stringFilters = selectedStringIds.length > 0 ? {
          selected_string_ids: selectedStringIds
        } : {};

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
      const googleWidget = document.querySelector('.skiptranslate iframe[id=":1.container"]');
      document.body.classList.remove(prefix + "-google-translate");
      if (googleWidget) {
        const closeButton = googleWidget.contentDocument.querySelector('a[id=":1.close"][title="Close"] img');
        if (closeButton) {
          closeButton.click();
        }
      }
    };
    (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
      const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
      if (doActionsBtn) {
        clearOldTranslatorCacheOnLoad();
        doActionsBtn.forEach(btn => {
          btn.addEventListener("click", bulkTranslationHandler);
          btn.addEventListener("mousemove", checkLanguagePackAvailability);
          btn.addEventListener("mouseleave", checkLanguagePackAvailability);
          btn.addEventListener("mouseenter", checkLanguagePackAvailability);
        });
      }
    }, []);
    (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
      const mainWrapper = document.getElementById(`${prefix}-wrapper`);
      if (mainWrapper) {
        mainWrapper.classList.toggle(`${prefix}-active`, modalVisible);
      }
    }, [modalVisible]);
    return modalVisible ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_App__WEBPACK_IMPORTED_MODULE_0__["default"], {
      onDestory: handleModalVisibility,
      prefix: prefix,
      postIds: postIds
    }) : null;
  };
  window.addEventListener("load", async () => {
    const prefix = "automlp-wpml-bulk-translate";

    // Move bulk translate button to correct position on string translation page
    const bulkTranslateBtn = document.querySelector(`.${prefix}-btn`);
    bulkTranslateBtn.style.display = "block";
    const stringFilterDiv = document.querySelector(".wpml-string-translation-filter");
    const filterButton = document.querySelector("#icl_st_filter_search_sb");
    if (bulkTranslateBtn && stringFilterDiv && filterButton) {
      // Insert the button after the filter button
      filterButton.insertAdjacentElement("afterend", bulkTranslateBtn);
    }
    react_dom__WEBPACK_IMPORTED_MODULE_2___default().createRoot(document.getElementById(`${prefix}-wrapper`)).render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(react_redux__WEBPACK_IMPORTED_MODULE_5__.Provider, {
      store: _redux_store_store__WEBPACK_IMPORTED_MODULE_4__.store,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(BulkTranslate, {
        prefix: prefix
      })
    }));
  });
})();
})();

/******/ })()
;
//# sourceMappingURL=index.js.map