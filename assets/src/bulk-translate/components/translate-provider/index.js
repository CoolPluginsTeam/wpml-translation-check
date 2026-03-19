// import YandexTranslater from "./yandex";
import AIService from "./ai-services";
import { sprintf, __ } from "@wordpress/i18n";

/**
 * Provides translation services using Yandex Translate.
 */
export default (props) => {
    props=props || {};
    const { Service = false, openErrorModalHandler=()=>{}, prefix='' } = props;
    const adminUrl = window.automlp_wpml_bulk_translate_object.admin_url;
    const assetsUrl = window.automlp_wpml_bulk_translate_object.automlp_wpml_url+'assets/images/';
    const errorIcon = assetsUrl + 'error-icon.svg';

    const Services = {
        openai_ai: {
            Provider: AIService,
            title: "OpenAI Model",
            SettingBtnText: "Translate",
            serviceLabel: "OpenAI",
            heading: sprintf(__("Translate Using %s Model", "automlp-ai-translation-for-wpml"), "OpenAI"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-open-ai-polylang/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_openai",
            BetaEnabled: true,
            ButtonDisabled: props.openai_aiButtonDisabled,
            ErrorMessage: props.openai_aiButtonDisabled ? <a className={`${prefix}-provider-error`} href={adminUrl + 'admin.php?page=automlp_ai_dashboard&tab=settings'} target="_blank">{__('Add API Key', 'wpml-translation-check')}</a> : <></>,
            Logo: 'openai.png',
            filterHtmlContent: true
        },
        google_ai: {
            Provider: AIService,
            title: "Gemini Model",
            SettingBtnText: "Translate",
            serviceLabel: "Gemini",
            heading: sprintf(__("Translate Using %s Model", "automlp-ai-translation-for-wpml"), "Gemini"),
            Docs: "https://docs.coolplugins.net/doc/translate-via-gemini-ai-polylang/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_gemini",
            BetaEnabled: true,
            ButtonDisabled: props.google_aiButtonDisabled,
            ErrorMessage: props.google_aiButtonDisabled ? <a className={`${prefix}-provider-error`} href={adminUrl + 'admin.php?page=automlp_ai_dashboard&tab=settings'} target="_blank">{__('Add API Key', 'wpml-translation-check')}</a> : <></>,
            Logo: 'gemini.png',
            filterHtmlContent: true
        },
        localAiTranslator: {
            title: "Chrome Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Chrome AI Translator",
            heading: sprintf(__("Translate Using %s", "automlp-ai-translation-for-wpml"), "Chrome built-in API"),
            Docs: "https://docs.coolplugins.net/doc/chrome-ai-translation-polylang/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chrome",
            BetaEnabled: true,
            ButtonDisabled: true,
            ErrorMessage: <a className='atfp-provider-error' href='https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=bulk_translate_chrome' target="_blank">{__('Buy Pro', 'automlp-ai-translation-for-wpml')}</a>,
            Logo: 'chrome.png',
            filterHtmlContent: true
        },
    };

    if (!Service) {
        return Services;
    }
    return Services[Service];
};
