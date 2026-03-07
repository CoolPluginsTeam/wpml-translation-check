import { __ } from "@wordpress/i18n";

const SettingModalFooter = ({ setSettingVisibility, prefix, selectedProvider, onStartTranslation }) => {
    return (
        <div className={`${prefix}-setting-modal-footer`}>
            <button type="button" className={`${prefix}-setting-close button`} onClick={() => setSettingVisibility(false)}>{__("Back", 'automl-ai-translation-for-wpml')}</button>
            <button
                type="button"
                className={`${prefix}-setting-start-translation button button-primary`}
                disabled={!selectedProvider}
                onClick={() => {
                    if (selectedProvider && onStartTranslation) onStartTranslation();
                }}
            >
                {__("Start Translation", 'automl-ai-translation-for-wpml')} <span className={`${prefix}-next-arrow`}>&#8594;</span>
            </button>
        </div>
    );
}

export default SettingModalFooter;
