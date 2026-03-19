import Providers from "./providers";
import TranslateService from "../components/translate-provider";
import { __ } from "@wordpress/i18n";

const providerDescriptions = {
    openai_ai: __("Best for creative and localized content.", "automlp-ai-translation-for-wpml"),
    google_ai: __("Reliable, fast translation across 130+ languages.", "automlp-ai-translation-for-wpml"),
};

const SettingModalBody = (props) => {
    const { prefix } = props;
    const ServiceProviders = TranslateService();
    const openai_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('openai');
    const google_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('google');
    return (
        <div className={`${prefix}-setting-modal-body`}>
            <div className={`${prefix}-provider-cards`}>
            {Object.keys(ServiceProviders).map((provider) => (
                    <Providers
                        key={provider}
                        {...props}
                        layout="card"
                        description={providerDescriptions[provider]}
                        selectedProvider={props.selectedProvider}
                        onSelectProvider={props.onSelectProvider}
                        openai_aiDisabled={openai_aiDisabled}
                        google_aiDisabled={google_aiDisabled}
                        localAiTranslatorDisabled={true}
                        openErrorModalHandler={props.errorModalHandler}
                        Service={provider}
                    />
                ))}
            </div>
        </div>
    );
};

export default SettingModalBody;
