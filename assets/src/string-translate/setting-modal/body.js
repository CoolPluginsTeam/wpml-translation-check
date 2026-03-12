import Providers from "./providers";
import TranslateService from "../components/translate-provider";
import { __ } from "@wordpress/i18n";


const SettingModalBody = (props) => {
    const { prefix, localAiModalError } = props;
    const ServiceProviders = TranslateService();
    const openai_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('openai');
    const google_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('google');
    const deepl_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('deepl');
    const openrouter_aiDisabled = !automlp_wpml_bulk_translate_object?.AIServices?.includes('openrouter');
    return (
        <div className={`${prefix}-setting-modal-body`}>
            <div className={`${prefix}-provider-cards`}>
            {Object.keys(ServiceProviders).map((provider) => (
                    <Providers
                        key={provider}
                        {...props}
                        layout="card"
                        selectedProvider={props.selectedProvider}
                        onSelectProvider={props.onSelectProvider}
                        openai_aiDisabled={openai_aiDisabled}
                        google_aiDisabled={google_aiDisabled}
                        openrouter_aiDisabled={openrouter_aiDisabled}
                        deepl_aiDisabled={deepl_aiDisabled}
                        localAiTranslatorDisabled={localAiModalError}
                        localAiModalError={localAiModalError}
                        openErrorModalHandler={props.errorModalHandler}
                        Service={provider}
                    />
                ))}
            </div>
        </div>
    );
};

export default SettingModalBody;