import React from "react";
import { Dropdown } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { getNonce } from "../utils";
import SetupContinueButton, { SetupBackButton } from "./SetupContinueButton";

const Languages = ({ onBack, onContinue }) => {
  const data = window.wpml_at_setup || {};
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
			if ( window.wpml_at_setup ) {
			  window.wpml_at_setup.saved_language = payload.selected_language;
			}
		  } catch (err) {
			// Ignore and continue anyway.
		  }
		}
	
		window.location.href = dashboardUrl;
		return true;
	  };

  return (
    <>
      <div className="automlp-ai-wizard-card">
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
            onChange={(event) => setSelectedCode(event.target.value)}
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

          {wpmlLanguages.length === 0 && (
            <p
              style={{
                marginTop: 12,
                color: "#b32d2e",
                fontSize: 14,
              }}
            >
              {__(
                "No languages found. Add languages in WPML → Languages first.",
                "wpml-translation-check",
              )}
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
                href={data.upgrade_url || "#"}
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
                await apiFetch({
                  path: "automlp-bulk-translate/wizard-complete",
                  method: "POST",
                  headers: { "X-WP-Nonce": getNonce() },
                });
              } catch (e) {
                // ignore, we'll still redirect
              }
              setTimeout(() => {
              window.location.href = dashboardUrl;
            }, 1500);
            }}
            label={__("Finish setup", "wpml-translation-check")}
            disabled={!selectedCode}
          />
        </div>
      </div>

      <div className="automlp-ai-wizard-card-footer">
        {__("Need help? Visit our", "wpml-translation-check")}{" "}
        <a
          href={data.doc_url || "https://docs.coolplugins.net/"}
          target="_blank"
          rel="noopener noreferrer"
        >
          {__("Documentation", "wpml-translation-check")}
        </a>
      </div>
    </>
  );
};

export default Languages;
