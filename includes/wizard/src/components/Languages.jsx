import React from "react";
import WizardHelpFooter from "./WizardHelpFooter";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { getNonce } from "../utils";
import SetupContinueButton, { SetupBackButton } from "./SetupContinueButton";

const Languages = ({ onBack, onContinue }) => {
  const data = window.automlp_ai_setup || {};
  const dashboardUrl = data.dashboard_url || ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?automlp_ai_dashboard&tab=settings' );
  const defaultCode = (data.default_language || "").toLowerCase();
  const allLanguages = Array.isArray(data.wpml_languages)
    ? data.wpml_languages
    : [];
  const wpmlLanguages = defaultCode
    ? allLanguages.filter(
        (lang) => (lang.code || "").toLowerCase() !== defaultCode,
      )
    : allLanguages;
    const savedCode =
    data.saved_language && data.saved_language.code
      ? data.saved_language.code
      : "";
  const [selectedCode, setSelectedCode] = React.useState(savedCode);
  
  const handleLanguageChange = (code) => {
    setSelectedCode(code);
    // Persist immediately so Back → Continue restores the selection.
    if (window.automlp_ai_setup) {
      const lang = wpmlLanguages.find((l) => l.code === code) || null;
      window.automlp_ai_setup.saved_language = lang
        ? { code: lang.code, name: lang.name || lang.code, flag_url: lang.flag_url, locale: lang.locale }
        : { code, name: "", flag_url: "" };
    }
  };

  // Options for dropdown.
  const languageOptions = wpmlLanguages.map((lang) => ({
    value: lang.code,
    label: lang.name || lang.code,
  }));

  const selectedLang =
    wpmlLanguages.find((lang) => (lang.code || "") === selectedCode) || null;

  const selectedLabel =
    selectedLang?.name ||
    selectedLang?.code ||
    __("Select an option", "wpml-translation-check");

	const handleContinue = async () => {
		if (selectedCode) {
		  const selectedLangObj = wpmlLanguages.find(
			(lang) => (lang.code || "") === selectedCode,
		  );
		  const payload = selectedLangObj
			? {
				selected_language: {
				  code: selectedLangObj.code,
				  name: selectedLangObj.name || selectedLangObj.code,
				  flag_url: selectedLangObj.flag_url,
				  locale: selectedLangObj.locale,
				},
			  }
			: {
				selected_language: {
				  code: selectedCode,
				  name: "",
				  flag_url: "",
				},
			  };
	
		  try {
			await apiFetch({
			  path: "automlp-bulk-translate/wizard-save-language",
			  method: "POST",
			  headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": getNonce(),
			  },
			  body: JSON.stringify(payload),
			});
	
			// Persist selection in memory so Back button restores it.
			if ( window.automlp_ai_setup ) {
			  window.automlp_ai_setup.saved_language = payload.selected_language;
			}
		  } catch (err) {
			// Ignore and continue anyway.
		  }
		}
		return true;
	  };

  return (
    <>
      <div className="automlp-ai-wizard-card" style={{ maxWidth: '680px' }}>
        <div
          className="automlp-ai-wizard-language-container"
          style={{ flex: 1, marginBottom: 20 }}
        >
          <h2 style={{ marginTop: 0 }}>
            {__("Select Language for AI Translation", "wpml-translation-check")}
          </h2>

          <p className="automlp-ai-wizard-intro" style={{ color: "#6b7280" }}>
            {__(
              "Choose your website language you want to translate using AI. The free version of AutoMLP allows AI translation for one language only.",
              "wpml-translation-check",
            )}
          </p>
          {wpmlLanguages.length !== 0 && (
            <>
          <label
            htmlFor="automlp-ai-wizard-language-select"
            style={{
              display: "block",
              fontSize: ".85rem",
              margin: "25px 0 8px 0",
              fontWeight: 500,
              color: "#333",
            }}
          >
            {__("Choose a language", "wpml-translation-check")}
          </label>

          <select
            id="automlp-ai-wizard-language-select"
            value={selectedCode}
            onChange={(event) => handleLanguageChange(event.target.value)}
            className="automlp-ai-wizard-select"
          >
            <option value="">
              {__("Select an option", "wpml-translation-check")}
            </option>

            {languageOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          </>
          )}
          {wpmlLanguages.length === 0 && (
            <p
              style={{
                marginTop: 12,
                color: "#b32d2e",
                fontSize: 14,
              }}
            >
              {__(
                "Languages are not configured in WPML.",
                "wpml-translation-check ",
              )}
              <a href={`${data.admin_url}?page=sitepress-multilingual-cms/menu/languages.php`} target="_blank" rel="noopener noreferrer">{__( ' Configure here ', 'wpml-translation-check-pro' )}</a>

            </p>
          )}

          <div className="automlp-ai-wizard-card-language-footer">
            <span
              className="automlp-ai-wizard-card-language-footer-icon"
              aria-hidden="true"
            >
              <img
                src={
                  (data.home_url || "") +
                  "/wp-content/plugins/wpml-translation-check/assets/images/star-icons.png"
                }
                alt=""
                width={18}
                height={18}
                style={{ display: "block" }}
              />
            </span>
            <div className="automlp-ai-wizard-card-language-footer-content">
              <p>
                <strong>
                  {__(
                    "Translate all languages using AI",
                    "wpml-translation-check",
                  )}
                </strong>
                {__(
                  " - Have a website in multiple languages and want to translate them all using AI?",
                  "wpml-translation-check",
                )}
              </p>
              <a
                href="https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=setup"
                target="_blank"
                rel="noopener noreferrer"
                className="automlp-ai-wizard-card-language-footer-link"
              >
                {__("Upgrade to Pro →", "wpml-translation-check")}
              </a>
            </div>
          </div>
        </div>

        <div className="automlp-ai-wizard-footer" style={{ marginTop: 24 }}>
          <SetupBackButton onClick={onBack} />
          <SetupContinueButton
            onClick={async () => {
              const saved = await handleContinue();
              if (!saved) return;
              try {
                const response = await apiFetch({
                  path: "automlp-bulk-translate/wizard-complete",
                  method: "POST",
                  headers: { "X-WP-Nonce": getNonce() },
                });
                if(response.success){
                  window.location.href = dashboardUrl;
                }
              } catch (e) {
                // ignore, we'll still redirect
              }          
            }}
            label={__("Finish setup", "wpml-translation-check")}
            disabled={!selectedCode}
          />
        </div>
      </div>

      <WizardHelpFooter />
    </>
  );
};

export default Languages;
