import React, { useState, useEffect } from "react";
import { __, sprintf } from "@wordpress/i18n";
import StatusModal from "./status-modal";
import { useDispatch, useSelector } from "react-redux";
import {
  resetStore,
  updateServiceProvider,
} from "./redux-store/features/actions";
import { selectCountInfo } from "./redux-store/features/selectors";
import ChromeAiTranslator from "./components/translate-provider/local-ai/local-ai-translate";
import ErrorModalBox from "./components/error-modal-box";
import SettingModal from "./setting-modal";
import DOMPurify from "dompurify";
import Notice from "./components/notice";

const App = ({ onDestory, prefix, postIds }) => {
  const dispatch = useDispatch();
  const { languageObject = {}, selected_language_object = {} } = automl_wpml_bulk_translate_object || {};
  const wizardSelectedCode = Object.keys(selected_language_object)[0] || '';
  const wizardLanguagesUrl = (automl_wpml_bulk_translate_object?.admin_url || '').replace(/\/?$/, '') + '/admin.php?page=automl_ai_wizard&step=languages';
  const emptyPostIdsErrorMessage = sprintf(
    __(
      "Please select at least one %s for translation.",
      "automl-ai-translation-for-wpml",
    ),
    automl_wpml_bulk_translate_object.post_label,
  );
  const [selectedLanguages, setSelectedLanguages] = useState([]);
  // Don't show error for string translation page even if postIds is empty
  const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;
  const [errorMessage, setErrorMessage] = useState(
    postIds.length === 0 && !isStringTranslationPage
      ? emptyPostIdsErrorMessage
      : "",
  );
  const [settingModalVisibility, setSettingModalVisibility] = useState(false);
  const [statusModalVisibility, setStatusModalVisibility] = useState(false);
  const translatePostsCount = useSelector(selectCountInfo).totalPosts;
  const [isLoading, setIsLoading] = useState(true);
  const [errorModal, setErrorModal] = useState(false);
  const [localAiModalError, setLocalAiModalError] = useState(false);
  const targetLanguages=JSON.parse(JSON.stringify(languageObject));
  delete targetLanguages[automl_wpml_bulk_translate_object.default_language_slug];

  const destroyApp = (e) => {
    setStatusModalVisibility(false);
    setSettingModalVisibility(false);
    onDestory(e);
  };

  useEffect(() => {
    const checkStatus = async () => {
      const status = await ChromeAiTranslator.languageSupportedStatus(
        "en",
        "hi",
        "English",
        "Hindi",
      );
      if (
        status.type === "browser-not-supported" ||
        status.type === "translation-api-not-available" ||
        status.type === "browser-not-supported"
      ) {
        setLocalAiModalError(
          __(status.html[0].outerHTML, "automl-ai-translation-for-wpml"),
        );
      }

      setIsLoading(false);
    };

    checkStatus();
  }, [statusModalVisibility]);

  useEffect(() => {
    if (!statusModalVisibility && !settingModalVisibility) {
      dispatch(resetStore());
    }
  }, [statusModalVisibility, settingModalVisibility, dispatch]);

  const settingModalVisibilityHandler = async () => {
    if (selectedLanguages.length === 0 && !settingModalVisibility) {
      setErrorMessage(
        __(
          "Please select at least one language",
          "automl-ai-translation-for-wpml",
        ),
      );
      setErrorModal(true);
      return;
    }

    setSettingModalVisibility((prev) => !prev);
  };

  const handleLanguageChange = (e) => {
    const { value } = e.target;
    const checked = e.target.checked;
    if (checked) {
      setSelectedLanguages([...selectedLanguages, value]);
    } else {
      setSelectedLanguages(
        selectedLanguages.filter((language) => language !== value),
      );
    }
  };

  const closeErrorModal = (e) => {
    setErrorModal(false);
  };

  const updateProviderHandler = (services) => {
    dispatch(updateServiceProvider(services));
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

    if (
      !translatePostsCount &&
      !settingModalVisibility &&
      statusModalVisibility
    ) {
      cls.push(`${prefix}-empty-posts`);
    }

    return cls.join(" ");
  };

  const SelectLanguageNotice = () => {
    const notices = [];

    const noticeLength = notices.length;

    if (notices.length > 0) {
      return notices.map((notice, index) => (
        <Notice
          className={notice.className}
          key={index}
          lastNotice={index === noticeLength - 1}
        >
          {notice.message}
        </Notice>
      ));
    }

    return;
  };

  return (
    <div id={`${prefix}-container`} className={containerCls()}>
      {settingModalVisibility && (
        <SettingModal
          prefix={prefix}
          onDestory={destroyApp}
          onCloseHandler={settingModalVisibilityHandler}
          updateProviderHandler={updateProviderHandler}
          localAiModalError={localAiModalError}
        />
      )}

      {statusModalVisibility &&
        !settingModalVisibility &&
        (isLoading ? (
          <div className={`${prefix}-skeleton-loader`}></div>
        ) : (
          <StatusModal
            postIds={postIds}
            selectedLanguages={selectedLanguages}
            prefix={prefix}
            onDestory={destroyApp}
          />
        ))}
      {!statusModalVisibility && !settingModalVisibility && (
        <div className={`${prefix}-language-container`}>
        <div className={`${prefix}-header`}>
            <div className={`${prefix}-modal-header-inner`}>
              <span className={`${prefix}-step-label`}>
                {__("STEP 1 OF 3", "automl-ai-translation-for-wpml")}
              </span>
              <h2>{__("Select Languages", "automl-ai-translation-for-wpml")}</h2>
            </div>
            <button
              type="button"
              className={`${prefix}-modal-close`}
              onClick={destroyApp}
              title={__("Close", "automl-ai-translation-for-wpml")}
              aria-label={__("Close", "automl-ai-translation-for-wpml")}
            >
              &times;
            </button>
          </div>
          {errorMessage && errorMessage !== "" ? (
            errorModal ? (
              <ErrorModalBox message={errorMessage} onClose={closeErrorModal} />
            ) : (
              <div
                className={`${prefix}-error-message`}
                dangerouslySetInnerHTML={{
                  __html: DOMPurify.sanitize(errorMessage),
                }}
              />
            )
          ) : (
            <>
            <div className={`${prefix}-body`}>
  <SelectLanguageNotice />
  {wizardSelectedCode ? (
    <div className={`${prefix}-languages`}>
      <div className={`${prefix}-languages-enabled-list`}>
        {(() => {
          const defaultSlug = automl_wpml_bulk_translate_object.default_language_slug;
          const allCodes = Object.keys(languageObject).filter(
            (lang) => !defaultSlug || defaultSlug !== lang
          );
          const selectedFirst =
            wizardSelectedCode && allCodes.includes(wizardSelectedCode)
              ? [wizardSelectedCode, ...allCodes.filter((l) => l !== wizardSelectedCode)]
              : allCodes;

          return selectedFirst.map((language) => {
            if (!languageObject[language]) return null;
            const isDisabled =
              (!postIds.length && !isStringTranslationPage) ||
              (wizardSelectedCode && language !== wizardSelectedCode);

            // Only show enabled items in this column
            if (isDisabled) return null;

            const isSelected = selectedLanguages.includes(language);
            return (
              <React.Fragment key={language}>
                <div
                  className={`${prefix}-language ${
                    isDisabled ? `${prefix}-language-item--disabled` : ''
                  } ${isSelected ? `${prefix}-language-item--selected` : ''}`}
                  title={
                    !postIds.length && !isStringTranslationPage
                      ? emptyPostIdsErrorMessage
                      : languageObject[language].name
                  }
                  onClick={(e) => {
                    if (e.target.closest('input') || e.target.closest('label')) return;
                    if (isDisabled) return;
                    if (isSelected) {
                      setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                    } else {
                      setSelectedLanguages([...selectedLanguages, language]);
                    }
                  }}
                  role="button"
                  tabIndex={isDisabled ? -1 : 0}
                  onKeyDown={(e) => {
                    if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                      e.preventDefault();
                      if (isSelected) {
                        setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                      } else {
                        setSelectedLanguages([...selectedLanguages, language]);
                      }
                    }
                  }}
                >
                  <div className={`${prefix}-language-item`}>
                    <input
                      type="checkbox"
                      name="languages"
                      id={language}
                      value={language}
                      onChange={handleLanguageChange}
                      disabled={isDisabled}
                      checked={isSelected}
                      className={`${prefix}-language-checkbox-input`}
                    />
                    <span className={`${prefix}-check-visual`} aria-hidden="true" />
                    <label
                      htmlFor={language}
                      className={`${prefix}-language-label`}
                      title={languageObject[language].name}
                    >
                      <img
                        src={languageObject[language].flag}
                        alt={languageObject[language].name}
                      />
                      &nbsp; {languageObject[language].name}
                    </label>
                  </div>
                </div>
              </React.Fragment>
            );
          });
        })()}
      </div>

      <div className={`${prefix}-languages-disabled-lists`}>
      <p>{__('Multiple language translation available in Pro.', 'automl-ai-translation-for-wpml')}
       &nbsp;
      <a href='#' title={__('Buy Pro Version to Unlock All Languages', 'automl-ai-translation-for-wpml')} className={`${prefix}-buy-pro-version-link`}>{__('Upgrade now', 'automl-ai-translation-for-wpml')}</a>
      </p>
      <div>
        {(() => {
          const defaultSlug = automl_wpml_bulk_translate_object.default_language_slug;
          const allCodes = Object.keys(languageObject).filter(
            (lang) => !defaultSlug || defaultSlug !== lang
          );
          const selectedFirst =
            wizardSelectedCode && allCodes.includes(wizardSelectedCode)
              ? [wizardSelectedCode, ...allCodes.filter((l) => l !== wizardSelectedCode)]
              : allCodes;

          return selectedFirst.map((language) => {
            if (!languageObject[language]) return null;
            const isDisabled =
              (!postIds.length && !isStringTranslationPage) ||
              (wizardSelectedCode && language !== wizardSelectedCode);

            // Only show disabled items in this column
            if (!isDisabled) return null;

            const isSelected = selectedLanguages.includes(language);
            return (
              <React.Fragment key={language}>
                <div
                  className={`${prefix}-language ${
                    isDisabled ? `${prefix}-language-item--disabled` : ''
                  } ${isSelected ? `${prefix}-language-item--selected` : ''}`}
                  title={
                    !postIds.length && !isStringTranslationPage
                      ? emptyPostIdsErrorMessage
                      : languageObject[language].name
                  }
                  onClick={(e) => {
                    if (e.target.closest('input') || e.target.closest('label')) return;
                    if (isDisabled) return;
                    if (isSelected) {
                      setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                    } else {
                      setSelectedLanguages([...selectedLanguages, language]);
                    }
                  }}
                  role="button"
                  tabIndex={isDisabled ? -1 : 0}
                  onKeyDown={(e) => {
                    if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                      e.preventDefault();
                      if (isSelected) {
                        setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                      } else {
                        setSelectedLanguages([...selectedLanguages, language]);
                      }
                    }
                  }}
                >
                  <div className={`${prefix}-language-item`}>
                    <input
                      type="checkbox"
                      name="languages"
                      id={language}
                      value={language}
                      onChange={handleLanguageChange}
                      disabled={isDisabled}
                      checked={isSelected}
                      className={`${prefix}-language-checkbox-input`}
                    />
                    <span className={`${prefix}-check-visual`} aria-hidden="true" />
                    <label
                      htmlFor={language}
                      className={`${prefix}-language-label`}
                      title={languageObject[language].name}
                    >
                      <img
                        src={languageObject[language].flag}
                        alt={languageObject[language].name}
                      />
                      &nbsp; {languageObject[language].name}
                    </label>
                  </div>
                </div>
              </React.Fragment>
            );
          });
        })()}
        </div>
      </div>
    </div>
  ) : (
    <div
      className={`${prefix}-wizard-language-notice`}
      style={{
        padding: '12px 16px',
        marginTop: 8,
        background: '#f0f6fc',
        border: '1px solid #c3c4c7',
        borderRadius: 4,
      }}
    >
      <p style={{ margin: '0 0 8px', fontSize: 14 }}>
        {__('Please select a translation language first.', 'automl-ai-translation-for-wpml')}
      </p>
      <a href={wizardLanguagesUrl} style={{ fontSize: 14 }}>
        {__('Select language in Setup Wizard (Languages step)', 'automl-ai-translation-for-wpml')}
      </a>
    </div>
  )}
</div>
              <div className={`${prefix}-footer`}>
                <button
                  className={`${prefix}-footer-button button button-primary`}
                  onClick={destroyApp}
                  title={
                    !postIds.length && !isStringTranslationPage
                      ? emptyPostIdsErrorMessage
                      : ""
                  }
                >
                  {__("Cancel", "automl-ai-translation-for-wpml")}
                </button>
                <button
                  className={`${prefix}-footer-button button button-primary`}
                  onClick={settingModalVisibilityHandler}
                  disabled={
                    (!postIds.length && !isStringTranslationPage) ||
                    !selectedLanguages.length
                  }
                  title={
                    !postIds.length && !isStringTranslationPage
                      ? emptyPostIdsErrorMessage
                      : !selectedLanguages.length
                      ? __(
                          "Please select at least one language",
                          "automl-ai-translation-for-wpml",
                        )
                      : ""
                  }
                >
                                    {__("Next", "automl-ai-translation-for-wpml")} <span className={`${prefix}-next-arrow`}>&#8594;</span>
                </button>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
};

export default App;
