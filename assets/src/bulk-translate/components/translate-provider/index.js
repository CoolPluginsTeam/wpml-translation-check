// import YandexTranslater from "./yandex";
import localAiTranslator from "./local-ai";
import AIService from "./ai-services";
import { sprintf, __ } from "@wordpress/i18n";

/**
 * Provides translation services using Yandex Translate.
 */
export default (props) => {
    props=props || {};
    const { Service = false, openErrorModalHandler=()=>{}, prefix='' } = props;
    const adminUrl = window.automl_wpml_bulk_translate_object.admin_url;
    const assetsUrl = window.automl_wpml_bulk_translate_object.automl_wpml_url+'assets/images/';
    const errorIcon = assetsUrl + 'error-icon.svg';

    const Services = {
        localAiTranslator: {
            Provider: localAiTranslator,
            title: "Chrome Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Chrome AI Translator",
            heading: sprintf(__("Translate Using %s", "automl-ai-translation-for-wpml"), "Chrome built-in API"),
            Docs: "https://docs.coolplugins.net/doc/chrome-ai-translation-polylang/?utm_source=automl_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_chrome",
            BetaEnabled: true,
            ButtonDisabled: props.localAiTranslatorButtonDisabled,
            ErrorMessage: props.localAiTranslatorButtonDisabled ? <div className={`${prefix}-provider-error`} onClick={() => openErrorModalHandler(props.localAiTranslatorButtonDisabled)}>{__('View Error', 'automl-ai-translation-for-wpml')}</div> : <></>,
            Logo: 'chrome.png',
            filterHtmlContent: true
        },
        openai_ai: {
            Provider: AIService,
            title: "OpenAI Model",
            SettingBtnText: "Translate",
            serviceLabel: "OpenAI",
            heading: sprintf(__("Translate Using %s Model", "automl-ai-translation-for-wpml"), "OpenAI"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-open-ai-polylang/?utm_source=automl_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_openai",
            BetaEnabled: true,
            ButtonDisabled: props.openai_aiButtonDisabled,
            ErrorMessage: props.openai_aiButtonDisabled ? <a className={`${prefix}-provider-error`} href={adminUrl + 'admin.php?page=automl_ai_dashboard&tab=settings'} target="_blank">{__('Add API Key', 'automl-ai-translation-for-wpml')}</a> : <></>,
            Logo: 'openai.png',
            filterHtmlContent: true
        },
        google_ai: {
            Provider: AIService,
            title: "Gemini Model",
            SettingBtnText: "Translate",
            serviceLabel: "Gemini",
            heading: sprintf(__("Translate Using %s Model", "automl-ai-translation-for-wpml"), "Gemini"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-gemini-ai-polylang/?utm_source=automl_wpml_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_gemini",
            BetaEnabled: true,
            ButtonDisabled: props.google_aiButtonDisabled,
            ErrorMessage: props.google_aiButtonDisabled ? <a className={`${prefix}-provider-error`} href={adminUrl + 'admin.php?page=automl_ai_dashboard&tab=settings'} target="_blank">{__('Add API Key', 'automl-ai-translation-for-wpml')}</a> : <></>,
            Logo: 'gemini.png',
            filterHtmlContent: true
        }
    };

    if (!Service) {
        return Services;
    }
    return Services[Service];
};
