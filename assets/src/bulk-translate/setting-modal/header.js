import { __ } from "@wordpress/i18n";

const SettingModalHeader = ({ setSettingVisibility, prefix }) => {
    return (
        <div className={`${prefix}-header`}>
         <div className={`${prefix}-modal-header-inner`}>
          <span className={`${prefix}-step-label`}>
            {__("STEP 2 OF 3", "automl-ai-translation-for-wpml")}
          </span>
          <h2>{__("Select Translation Engine", 'automl-ai-translation-for-wpml')}</h2>
          <p className={`${prefix}-modal-desc`}>{__("Select an AI provider to automatically translate your content.", 'automl-ai-translation-for-wpml')}</p>
         </div>
            <button type="button" aria-label={__('Close', 'automl-ai-translation-for-wpml')} className={`${prefix}-modal-close`} onClick={(e) => setSettingVisibility(e)}>&times;</button>
        </div>
    );
}

export default SettingModalHeader;
