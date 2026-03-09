import { __ } from "@wordpress/i18n";

const SettingModalHeader = ({ setSettingVisibility, prefix }) => {
    return (
        <div className={`${prefix}-header`}>
         <div className={`${prefix}-modal-header-inner`}>
          <span className={`${prefix}-step-label`}>
            {__("STEP 2 OF 3", "automl-ai-translation-for-wpml")}
          </span>
          <h2>{__("Select Translation Engine", 'wpml-translation-check')}</h2>
          <p className={`${prefix}-modal-desc`}>{__("Select an AI provider to automatically translate your content.", 'wpml-translation-check')}</p>
         </div>
            <button type="button" aria-label={__('Close', 'wpml-translation-check')} className={`${prefix}-modal-close`} onClick={(e) => setSettingVisibility(e)}>&times;</button>
        </div>
    );
}

export default SettingModalHeader;
