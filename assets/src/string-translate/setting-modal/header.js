import { __ } from "@wordpress/i18n";

const SettingModalHeader = ({ setSettingVisibility, prefix }) => {
    return (
        <div className={`${prefix}-header`}>
         <div className={`${prefix}-modal-header-inner`}>
          <span className={`${prefix}-step-label`}>
            {__("STEP 2 OF 3", "automl-ai-translation-for-wpml")}
          </span>
          <h2>{__("Select Translation Engine", 'wpml-translation-check')}</h2>
          <p className={`${prefix}-modal-desc`}>{__("Choose the Ai provider you want to use for your this translation batch.", 'wpml-translation-check')}</p>
         </div>
            <span className={`${prefix}-modal-close`} onClick={(e) => setSettingVisibility(e)}>&times;</span>
        </div>
    );
}

export default SettingModalHeader;
