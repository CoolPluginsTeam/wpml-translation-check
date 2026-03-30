/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./includes/wizard/src/components/AiTranslation.jsx"
/*!**********************************************************!*\
  !*** ./includes/wizard/src/components/AiTranslation.jsx ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _WizardHelpFooter__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./WizardHelpFooter */ "./includes/wizard/src/components/WizardHelpFooter.jsx");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./SetupContinueButton */ "./includes/wizard/src/components/SetupContinueButton.jsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../utils */ "./includes/wizard/src/utils.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







const AiTranslation = ({
  onBack,
  onContinue
}) => {
  const data = window.automlp_ai_setup || {};
  const savedCreds = data.saved_credentials || {};
  const isUsingConnectorsAi = data.is_connectors_ai || false;
  const connectorsUrl = data.connectors_url || "#";
  // Helper function to mask API keys
  const maskApiKey = apiKey => {
    if (!apiKey || apiKey.includes("*")) {
      return apiKey;
    }
    if (apiKey.length < 12) {
      return apiKey;
    }
    const start = apiKey.substring(0, 6);
    const end = apiKey.substring(apiKey.length - 6);
    const middleLength = apiKey.length - 12;
    const maskedMiddle = "*".repeat(Math.min(Math.max(middleLength, 0), 24));
    return start + maskedMiddle + end;
  };

  // Check if we have existing keys
  const hasExistingOpenaiKey = !!(savedCreds.openai_key && savedCreds.openai_key.trim());
  const hasExistingGoogleKey = !!(savedCreds.google_key && savedCreds.google_key.trim());
  const [openaiKey, setOpenaiKey] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(hasExistingOpenaiKey ? isUsingConnectorsAi ? savedCreds.openai_key : maskApiKey(savedCreds.openai_key) : "");
  const [googleKey, setGoogleKey] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(hasExistingGoogleKey ? isUsingConnectorsAi ? savedCreds.google_key : maskApiKey(savedCreds.google_key) : "");
  const [saving, setSaving] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
  const [openaiMessage, setOpenaiMessage] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(null); // error under OpenAI
  const [googleMessage, setGoogleMessage] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(null); // error under Google
  const [generalMessage, setGeneralMessage] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(null); // success / general error
  const [isError, setIsError] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
  const wp_version_check = data.wp_version_check || false;
  // Track if fields are in edit mode
  const [openaiEditMode, setOpenaiEditMode] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(!hasExistingOpenaiKey);
  const [googleEditMode, setGoogleEditMode] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(!hasExistingGoogleKey);

  // Store original masked values
  const openaiMasked = hasExistingOpenaiKey && !isUsingConnectorsAi ? maskApiKey(savedCreds.openai_key) : "";
  const googleMasked = hasExistingGoogleKey && !isUsingConnectorsAi ? maskApiKey(savedCreds.google_key) : "";

  // Check if at least one API key is available (either existing or newly entered)
  const hasValidApiKey = () => {
    // Check OpenAI key
    const hasOpenaiKey = hasExistingOpenaiKey || openaiEditMode && openaiKey.trim() !== "" && openaiKey !== openaiMasked;
    // Check Google key
    const hasGoogleKey = hasExistingGoogleKey || googleEditMode && googleKey.trim() !== "" && googleKey !== googleMasked;
    return hasOpenaiKey || hasGoogleKey;
  };
  const handleSave = async () => {
    setSaving(true);
    setOpenaiMessage(null);
    setGoogleMessage(null);
    setGeneralMessage(null);
    setIsError(false);

    // Prepare request data - only send keys that have been changed
    const requestData = {
      is_wizard: true // Flag to indicate this is from wizard
    };

    // Only send OpenAI key if it's been edited and is not the masked version
    if (openaiEditMode && openaiKey.trim() !== "" && openaiKey !== openaiMasked) {
      requestData.openai_key = openaiKey;
    } else if (openaiEditMode && openaiKey.trim() === "" && hasExistingOpenaiKey) {
      // Empty field with existing key = reset request
      requestData.openai_key = "";
    }

    // Only send Google key if it's been edited and is not the masked version
    if (googleEditMode && googleKey.trim() !== "" && googleKey !== googleMasked) {
      requestData.google_key = googleKey;
    } else if (googleEditMode && googleKey.trim() === "" && hasExistingGoogleKey) {
      // Empty field with existing key = reset request
      requestData.google_key = "";
    }
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: "automlp-bulk-translate/wizard-save-credentials",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": (0,_utils__WEBPACK_IMPORTED_MODULE_5__.getNonce)()
        },
        body: JSON.stringify(requestData)
      });

      // Success: show a single general success message
      if (window.automlp_ai_setup) {
        window.automlp_ai_setup.saved_credentials = window.automlp_ai_setup.saved_credentials || {};
        if (requestData.openai_key !== undefined && requestData.openai_key !== "") {
          window.automlp_ai_setup.saved_credentials.openai_key = maskApiKey(requestData.openai_key);
        } else if (requestData.openai_key === "") {
          delete window.automlp_ai_setup.saved_credentials.openai_key;
        }
        if (requestData.google_key !== undefined && requestData.google_key !== "") {
          window.automlp_ai_setup.saved_credentials.google_key = maskApiKey(requestData.google_key);
        } else if (requestData.google_key === "") {
          delete window.automlp_ai_setup.saved_credentials.google_key;
        }
      }

      // Success: show a single general success message
      setGeneralMessage((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("API keys saved.", "wpml-translation-check"));
      setIsError(false);
      return true;
    } catch (err) {
      const fieldErrors = err?.data?.errors || {};

      // Field‑specific errors from PHP
      if (fieldErrors.openai) {
        setOpenaiMessage(fieldErrors.openai);
      }
      if (fieldErrors.google) {
        setGoogleMessage(fieldErrors.google);
      }

      // No key at all
      if (err?.code === "automlp_no_api_key" && !fieldErrors.openai && !fieldErrors.google) {
        setGeneralMessage(err?.message);
      }

      // Fallback general error if nothing else
      if (!fieldErrors.openai && !fieldErrors.google && !generalMessage && err?.message) {
        setGeneralMessage(err.message);
      }
      setIsError(true);
      return false;
    } finally {
      setSaving(false);
    }
  };

  // Handle input field clicks - make editable when showing masked key
  const handleInputClick = provider => {
    if (provider === "openai" && !openaiEditMode && openaiKey === openaiMasked) {
      setOpenaiEditMode(true);
      setOpenaiKey("");
    } else if (provider === "google" && !googleEditMode && googleKey === googleMasked) {
      setGoogleEditMode(true);
      setGoogleKey("");
    }
  };

  // Handle input blur - restore masked view if empty
  const handleInputBlur = provider => {
    if (provider === "openai" && openaiEditMode && openaiKey.trim() === "" && hasExistingOpenaiKey) {
      setOpenaiEditMode(false);
      setOpenaiKey(openaiMasked);
    } else if (provider === "google" && googleEditMode && googleKey.trim() === "" && hasExistingGoogleKey) {
      setGoogleEditMode(false);
      setGoogleKey(googleMasked);
    }
  };

  // Handle reset button clicks - immediately delete the API key
  const handleReset = async provider => {
    const requestData = {
      is_reset: true // Flag to indicate this is a reset operation - bypass validation
    };
    requestData[provider + "_key"] = "";
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: "automlp-bulk-translate/wizard-save-credentials",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": (0,_utils__WEBPACK_IMPORTED_MODULE_5__.getNonce)()
        },
        body: JSON.stringify(requestData)
      });

      // Success - update state to reflect deletion
      if (provider === "openai") {
        setOpenaiKey("");
        setOpenaiEditMode(true);
      } else if (provider === "google") {
        setGoogleKey("");
        setGoogleEditMode(true);
      }

      // Reload to get fresh data
      window.location.reload();
    } catch (err) {
      // Show error message
      const errorMsg = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Failed to delete API key. Please try again.", "wpml-translation-check");
      if (provider === "openai") {
        setOpenaiMessage(errorMsg);
      } else if (provider === "google") {
        setGoogleMessage(errorMsg);
      }
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "automlp-ai-wizard-card",
      style: {
        maxWidth: '680px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "automlp-ai-wizard-language-container",
        style: {
          flex: 1
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
          style: {
            marginTop: 0
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Connect AI Provider", "wpml-translation-check")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            marginBottom: 12,
            color: "#6b7280"
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("To start using AI translation, connect at least one AI provider below. Add your AI provider API key to start translating your website with AutoML.", "wpml-translation-check")
        }), generalMessage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            color: isError ? "#b32d2e" : "#00a32a",
            marginBottom: 12,
            fontSize: 14
          },
          children: generalMessage
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-api-key-container",
          style: {
            marginBottom: 16,
            color: "#6b7280"
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("label", {
            htmlFor: "automlp-ai-wizard-openai-key",
            style: {
              display: "block",
              marginBottom: 6,
              fontWeight: 500
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("OpenAI API key", "wpml-translation-check")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            style: {
              display: "flex",
              alignItems: "center",
              gap: "8px",
              width: "100%"
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
              id: "automlp-ai-wizard-openai-key",
              type: "text",
              value: openaiKey,
              onChange: e => setOpenaiKey(e.target.value),
              onClick: () => handleInputClick("openai"),
              onBlur: () => handleInputBlur("openai"),
              disabled: !openaiEditMode,
              placeholder: openaiEditMode ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Enter OpenAI API key", "wpml-translation-check") : "",
              style: {
                flex: 1,
                padding: "8px 12px",
                fontSize: 14,
                cursor: !openaiEditMode ? "pointer" : "text"
              }
            }), (isUsingConnectorsAi || hasExistingOpenaiKey && !openaiEditMode) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
              children: [!isUsingConnectorsAi && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                style: {
                  color: "#46b450",
                  fontSize: "14px",
                  marginRight: "4px"
                },
                children: "\u2713"
              }), hasExistingOpenaiKey ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
                type: "button",
                onClick: () => handleReset("openai"),
                className: "button button-primary automlp-reset-key-btn",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Reset", "wpml-translation-check")
              }) : null]
            })]
          }), openaiMessage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            style: {
              color: "#b32d2e",
              marginTop: 4,
              marginBottom: 12,
              fontSize: 13
            },
            children: openaiMessage
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
            href: "https://docs.coolplugins.net/doc/generate-openai-api-key/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup_api_key",
            target: "_blank",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Click here", "wpml-translation-check")
          }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(" to see how to configure OpenAI in the AI SDK.", "wpml-translation-check")]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-api-key-container",
          style: {
            marginBottom: 16,
            color: "#6b7280"
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("label", {
            htmlFor: "automlp-ai-wizard-google-key",
            style: {
              display: "block",
              marginBottom: 6,
              fontWeight: 500
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Google / Gemini API key", "wpml-translation-check")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            style: {
              display: "flex",
              alignItems: "center",
              gap: "8px",
              width: "100%"
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
              id: "automlp-ai-wizard-google-key",
              type: "text",
              value: googleKey,
              onChange: e => setGoogleKey(e.target.value),
              onClick: () => handleInputClick("google"),
              onBlur: () => handleInputBlur("google"),
              disabled: !googleEditMode,
              placeholder: googleEditMode ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Enter Google API key", "wpml-translation-check") : "",
              style: {
                flex: 1,
                padding: "8px 12px",
                fontSize: 14,
                cursor: !googleEditMode ? "pointer" : "text"
              }
            }), (isUsingConnectorsAi || hasExistingGoogleKey && !googleEditMode) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
              children: [!isUsingConnectorsAi && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                style: {
                  color: "#46b450",
                  fontSize: "14px",
                  marginRight: "4px"
                },
                children: "\u2713"
              }), hasExistingGoogleKey ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
                type: "button",
                onClick: () => handleReset("google"),
                className: "button button-primary automlp-reset-key-btn",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Reset", "wpml-translation-check")
              }) : null]
            })]
          }), googleMessage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            style: {
              color: "#b32d2e",
              marginTop: 4,
              marginBottom: 12,
              fontSize: 13
            },
            children: googleMessage
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
            href: "https://docs.coolplugins.net/doc/generate-gemini-ai-api-key/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup_api_key",
            target: "_blank",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Click here", "wpml-translation-check")
          }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(" to see how to configure Google Gemini in the AI SDK.", "wpml-translation-check")]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-card-language-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "automlp-ai-wizard-card-language-footer-icon",
            "aria-hidden": "true",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
              src: (data.home_url || "") + "/wp-content/plugins/wpml-translation-check/assets/images/star-icons.png",
              alt: "",
              width: 18,
              height: 18,
              style: {
                display: "block"
              }
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "automlp-ai-wizard-card-language-footer-content",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Chrome Built-in AI", "wpml-translation-check")
              }), " — ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Translate on your device using local AI, no API key required, unlimited translations, save 100% on API usage costs.", "wpml-translation-check")]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
              href: "https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=setup",
              target: "_blank",
              rel: "noopener noreferrer",
              className: "automlp-ai-wizard-card-language-footer-link",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Upgrade to Pro →", "wpml-translation-check")
            })]
          })]
        }), !wp_version_check && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-api-note",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "dashicons dashicons-warning"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("em", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("API keys are saved securely and can be updated anytime in WPML → AutoMLP AI → Settings.", "wpml-translation-check")
            })
          })]
        }), wp_version_check && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-api-note",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "dashicons dashicons-warning"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("em", {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Note: API keys configured here are linked with the", "wpml-translation-check"), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
                href: connectorsUrl,
                target: "_blank",
                rel: "noopener noreferrer",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AI → Connectors", "wpml-translation-check")
                })
              }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("settings in WordPress.", "wpml-translation-check")]
            })
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "automlp-ai-wizard-footer",
        style: {
          display: "flex",
          justifyContent: "space-between",
          marginTop: 24
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__.SetupBackButton, {
          onClick: onBack
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__["default"], {
          onClick: async () => {
            const saved = await handleSave();
            if (!saved) return;
            onContinue();
          },
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Continue", "wpml-translation-check"),
          disabled: saving || !hasValidApiKey()
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_WizardHelpFooter__WEBPACK_IMPORTED_MODULE_2__["default"], {})]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AiTranslation);

/***/ },

/***/ "./includes/wizard/src/components/Languages.jsx"
/*!******************************************************!*\
  !*** ./includes/wizard/src/components/Languages.jsx ***!
  \******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _WizardHelpFooter__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./WizardHelpFooter */ "./includes/wizard/src/components/WizardHelpFooter.jsx");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../utils */ "./includes/wizard/src/utils.js");
/* harmony import */ var _SetupContinueButton__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./SetupContinueButton */ "./includes/wizard/src/components/SetupContinueButton.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







const Languages = ({
  onBack,
  onContinue
}) => {
  const data = window.automlp_ai_setup || {};
  const dashboardUrl = data.dashboard_url || (data.admin_url || '').replace('admin.php', 'admin.php?automlp_ai_dashboard&tab=settings');
  const defaultCode = (data.default_language || "").toLowerCase();
  const allLanguages = Array.isArray(data.wpml_languages) ? data.wpml_languages : [];
  const wpmlLanguages = defaultCode ? allLanguages.filter(lang => (lang.code || "").toLowerCase() !== defaultCode) : allLanguages;
  const savedCode = data.saved_language && data.saved_language.code ? data.saved_language.code : "";
  const [selectedCode, setSelectedCode] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(savedCode);
  const handleLanguageChange = code => {
    setSelectedCode(code);
    // Persist immediately so Back → Continue restores the selection.
    if (window.automlp_ai_setup) {
      const lang = wpmlLanguages.find(l => l.code === code) || null;
      window.automlp_ai_setup.saved_language = lang ? {
        code: lang.code,
        name: lang.name || lang.code,
        flag_url: lang.flag_url,
        locale: lang.locale
      } : {
        code,
        name: "",
        flag_url: ""
      };
    }
  };

  // Options for dropdown.
  const languageOptions = wpmlLanguages.map(lang => ({
    value: lang.code,
    label: lang.name || lang.code
  }));
  const selectedLang = wpmlLanguages.find(lang => (lang.code || "") === selectedCode) || null;
  const selectedLabel = selectedLang?.name || selectedLang?.code || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Select an option", "wpml-translation-check");
  const handleContinue = async () => {
    if (selectedCode) {
      const selectedLangObj = wpmlLanguages.find(lang => (lang.code || "") === selectedCode);
      const payload = selectedLangObj ? {
        selected_language: {
          code: selectedLangObj.code,
          name: selectedLangObj.name || selectedLangObj.code,
          flag_url: selectedLangObj.flag_url,
          locale: selectedLangObj.locale
        }
      } : {
        selected_language: {
          code: selectedCode,
          name: "",
          flag_url: ""
        }
      };
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
          path: "automlp-bulk-translate/wizard-save-language",
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": (0,_utils__WEBPACK_IMPORTED_MODULE_4__.getNonce)()
          },
          body: JSON.stringify(payload)
        });

        // Persist selection in memory so Back button restores it.
        if (window.automlp_ai_setup) {
          window.automlp_ai_setup.saved_language = payload.selected_language;
        }
      } catch (err) {
        // Ignore and continue anyway.
      }
    }
    return true;
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "automlp-ai-wizard-card",
      style: {
        maxWidth: '680px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "automlp-ai-wizard-language-container",
        style: {
          flex: 1,
          marginBottom: 20
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
          style: {
            marginTop: 0
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Select Language for AI Translation", "wpml-translation-check")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          className: "automlp-ai-wizard-intro",
          style: {
            color: "#6b7280"
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Choose your website language you want to translate using AI. The free version of AutoMLP allows AI translation for one language only.", "wpml-translation-check")
        }), wpmlLanguages.length !== 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("label", {
            htmlFor: "automlp-ai-wizard-language-select",
            style: {
              display: "block",
              fontSize: ".85rem",
              margin: "25px 0 8px 0",
              fontWeight: 500,
              color: "#333"
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Choose a language", "wpml-translation-check")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("select", {
            id: "automlp-ai-wizard-language-select",
            value: selectedCode,
            onChange: event => handleLanguageChange(event.target.value),
            className: "automlp-ai-wizard-select",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("option", {
              value: "",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Select an option", "wpml-translation-check")
            }), languageOptions.map(opt => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("option", {
              value: opt.value,
              children: opt.label
            }, opt.value))]
          })]
        }), wpmlLanguages.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
          style: {
            marginTop: 12,
            color: "#b32d2e",
            fontSize: 14
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Languages are not configured in WPML.", "wpml-translation-check "), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
            href: `${data.admin_url}?page=sitepress-multilingual-cms/menu/languages.php`,
            target: "_blank",
            rel: "noopener noreferrer",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(' Configure here ', 'wpml-translation-check-pro')
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "automlp-ai-wizard-card-language-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "automlp-ai-wizard-card-language-footer-icon",
            "aria-hidden": "true",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
              src: (data.home_url || "") + "/wp-content/plugins/wpml-translation-check/assets/images/star-icons.png",
              alt: "",
              width: 18,
              height: 18,
              style: {
                display: "block"
              }
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "automlp-ai-wizard-card-language-footer-content",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Translate all languages using AI", "wpml-translation-check")
              }), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(" - Have a website in multiple languages and want to translate them all using AI?", "wpml-translation-check")]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
              href: "https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=setup",
              target: "_blank",
              rel: "noopener noreferrer",
              className: "automlp-ai-wizard-card-language-footer-link",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Upgrade to Pro →", "wpml-translation-check")
            })]
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "automlp-ai-wizard-footer",
        style: {
          marginTop: 24
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_5__.SetupBackButton, {
          onClick: onBack
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_5__["default"], {
          onClick: async () => {
            const saved = await handleContinue();
            if (!saved) return;
            try {
              const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
                path: "automlp-bulk-translate/wizard-complete",
                method: "POST",
                headers: {
                  "X-WP-Nonce": (0,_utils__WEBPACK_IMPORTED_MODULE_4__.getNonce)()
                }
              });
              if (response.success) {
                window.location.href = dashboardUrl;
              }
            } catch (e) {
              // ignore, we'll still redirect
            }
          },
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Finish setup", "wpml-translation-check"),
          disabled: !selectedCode
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_WizardHelpFooter__WEBPACK_IMPORTED_MODULE_1__["default"], {})]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Languages);

/***/ },

/***/ "./includes/wizard/src/components/SetupContinueButton.jsx"
/*!****************************************************************!*\
  !*** ./includes/wizard/src/components/SetupContinueButton.jsx ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SetupBackButton: () => (/* binding */ SetupBackButton),
/* harmony export */   "default": () => (/* binding */ SetupContinueButton)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


function SetupContinueButton({
  onClick,
  label,
  disabled
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
    type: "button",
    className: "button button-primary automlp-ai-wizard-continue",
    onClick: onClick,
    disabled: disabled,
    style: {
      minWidth: 100
    },
    children: label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Continue', 'wpml-translation-check')
  });
}
function SetupBackButton({
  onClick
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
    type: "button",
    className: "button button-primary automlp-ai-wizard-back-button",
    onClick: onClick,
    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Back', 'wpml-translation-check')
  });
}

/***/ },

/***/ "./includes/wizard/src/components/SetupProgress.jsx"
/*!**********************************************************!*\
  !*** ./includes/wizard/src/components/SetupProgress.jsx ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _VideoIntro__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./VideoIntro */ "./includes/wizard/src/components/VideoIntro.jsx");
/* harmony import */ var _Languages__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./Languages */ "./includes/wizard/src/components/Languages.jsx");
/* harmony import */ var _AiTranslation__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./AiTranslation */ "./includes/wizard/src/components/AiTranslation.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const STEPS = [{
  key: "video_intro",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Intro", "wpml-translation-check")
}, {
  key: "ai_translation",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AI Translation", "wpml-translation-check")
}, {
  key: "languages",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Languages", "wpml-translation-check")
}];
const SetupProgress = ({
  currentStep,
  setCurrentStep,
  onGetStarted,
  onFinish
}) => {
  const currentIndex = STEPS.findIndex(s => s.key === currentStep);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "automlp-ai-wizard-steps",
      children: STEPS.map((step, index) => {
        const isActive = step.key === currentStep;
        const isPast = index < currentIndex;
        const circleClass = isActive ? "active" : isPast ? "past" : "inactive";
        const labelClass = isActive ? "active" : isPast ? "past" : "inactive";
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            className: "automlp-ai-wizard-step-item",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
              className: `automlp-ai-wizard-step-circle ${circleClass}`,
              children: isPast ? "✓" : index + 1
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
              className: `automlp-ai-wizard-step-label ${labelClass}`,
              children: step.label
            })]
          }), index < STEPS.length - 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: `automlp-ai-wizard-step-connector${currentIndex > index ? " automlp-ai-wizard-step-connector--filled" : ""}`,
            "aria-hidden": "true"
          })]
        }, step.key);
      })
    }), currentStep === "video_intro" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_VideoIntro__WEBPACK_IMPORTED_MODULE_2__["default"], {
      onGetStarted: onGetStarted
    }), currentStep === "ai_translation" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_AiTranslation__WEBPACK_IMPORTED_MODULE_4__["default"], {
      onBack: () => setCurrentStep("video_intro"),
      onContinue: () => setCurrentStep("languages")
    }), currentStep === "languages" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_Languages__WEBPACK_IMPORTED_MODULE_3__["default"], {
      onBack: () => setCurrentStep("ai_translation"),
      onContinue: onFinish
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SetupProgress);

/***/ },

/***/ "./includes/wizard/src/components/VideoIntro.jsx"
/*!*******************************************************!*\
  !*** ./includes/wizard/src/components/VideoIntro.jsx ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _WizardHelpFooter__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./WizardHelpFooter */ "./includes/wizard/src/components/WizardHelpFooter.jsx");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




const VideoIntro = ({
  onGetStarted
}) => {
  const data = window.automlp_ai_setup || {};
  const videoUrl = "https://www.youtube.com/embed/ZcSbNup4efw";
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "automlp-ai-wizard-card",
      style: {
        maxWidth: "600px",
        padding: "0 40px"
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        style: {
          textAlign: "center",
          marginBottom: 24
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h3", {
          className: "automlp-ai-wizard-card-title h3",
          style: {
            fontSize: "1.5rem",
            marginBottom: 12,
            paddingTop: 24
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Watch Setup Guide Video", "wpml-translation-check")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("p", {
          style: {
            color: "#6b7280",
            marginBottom: 24,
            textAlign: "center"
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Learn how to set up AutoMLP and start translating your pages", "wpml-translation-check"), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("automatically using AI.", "wpml-translation-check")]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        style: {
          position: 'relative',
          width: '100%',
          paddingBottom: '56.25%'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("iframe", {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('AutoMLP Setup Guide', 'wpml-translation-check'),
          style: {
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            borderRadius: 8
          },
          src: videoUrl,
          frameBorder: "0",
          allow: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share",
          allowFullScreen: true
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        style: {
          display: "flex",
          justifyContent: "center",
          padding: "2.5em"
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("button", {
          type: "button",
          className: "button button-primary button-hero",
          onClick: onGetStarted,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Get Started", "wpml-translation-check")
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_WizardHelpFooter__WEBPACK_IMPORTED_MODULE_1__["default"], {})]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VideoIntro);

/***/ },

/***/ "./includes/wizard/src/components/WizardHelpFooter.jsx"
/*!*************************************************************!*\
  !*** ./includes/wizard/src/components/WizardHelpFooter.jsx ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ WizardHelpFooter)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


function WizardHelpFooter({
  href = 'https://docs.coolplugins.net/plugin/ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup'
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
    className: "automlp-ai-wizard-card-footer",
    children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Need help? Visit our', 'wpml-translation-check'), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("a", {
      href: href,
      target: "_blank",
      rel: "noopener noreferrer",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Documentation', 'wpml-translation-check')
    })]
  });
}

/***/ },

/***/ "./includes/wizard/src/pages/setup-page.jsx"
/*!**************************************************!*\
  !*** ./includes/wizard/src/pages/setup-page.jsx ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_SetupProgress__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../components/SetupProgress */ "./includes/wizard/src/components/SetupProgress.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




const STEP_KEYS = ['video_intro', 'ai_translation', 'languages'];
function getStepFromUrl() {
  if (typeof window === 'undefined') return 'video_intro';
  const params = new URLSearchParams(window.location.search);
  const step = params.get('step');
  return STEP_KEYS.includes(step) ? step : 'video_intro';
}
function setStepInUrl(step) {
  const url = new URL(window.location.href);
  url.searchParams.set('step', step);
  window.history.replaceState({}, '', url.toString());
}
const SetupPage = () => {
  const [currentStep, setCurrentStep] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(getStepFromUrl);
  react__WEBPACK_IMPORTED_MODULE_0___default().useEffect(() => {
    setStepInUrl(currentStep);
  }, [currentStep]);
  const data = window.automlp_ai_setup || {};
  const dashboardUrl = data.dashboard_url || (data.admin_url || '').replace('admin.php', 'admin.php?page=automlp_ai_dashboard');
  const handleGetStarted = () => {
    setCurrentStep('ai_translation');
  };
  const handleFinish = () => {
    setShowReady(true);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h1", {
      style: {
        textAlign: 'center',
        paddingTop: 30,
        marginBottom: 16
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AutoMLP – AI Translation for WPML', 'wpml-translation-check')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_components_SetupProgress__WEBPACK_IMPORTED_MODULE_2__["default"], {
      currentStep: currentStep,
      setCurrentStep: setCurrentStep,
      onGetStarted: handleGetStarted,
      onFinish: handleFinish
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SetupPage);

/***/ },

/***/ "./includes/wizard/src/utils.js"
/*!**************************************!*\
  !*** ./includes/wizard/src/utils.js ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getNonce: () => (/* binding */ getNonce)
/* harmony export */ });
/**
 * Get nonce from localized script data.
 */
const getNonce = () => {
  return window.automlp_ai_setup?.nonce || '';
};

/***/ },

/***/ "./includes/wizard/src/wizard.css"
/*!****************************************!*\
  !*** ./includes/wizard/src/wizard.css ***!
  \****************************************/
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

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

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
/*!**************************************!*\
  !*** ./includes/wizard/src/setup.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _pages_setup_page_jsx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./pages/setup-page.jsx */ "./includes/wizard/src/pages/setup-page.jsx");
/* harmony import */ var _wizard_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./wizard.css */ "./includes/wizard/src/wizard.css");
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




function mount() {
  const el = document.getElementById('automlp-ai-setup');
  if (el) {
    const root = (0,react_dom_client__WEBPACK_IMPORTED_MODULE_2__.createRoot)(el);
    root.render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_pages_setup_page_jsx__WEBPACK_IMPORTED_MODULE_0__["default"], {}));
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
})();

/******/ })()
;
//# sourceMappingURL=setup.js.map