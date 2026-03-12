(()=>{
    const enableBulkTranslateBtn=()=>{
        const AutoMlSubsubsubList = jQuery('.automlp_wpml_subsubsub');
        const AutoMlBulkTranslateBtn = jQuery('.automlp-wpml-bulk-translate-btn');

        const automlp_wpml_admin_object = window.automlp_wpml_admin_object;
        const wizardLanguage = automlp_wpml_admin_object.wizardLanguage;
        const currentLanguage = automlp_wpml_admin_object.currentLanguage;

        if(currentLanguage === wizardLanguage) {
            AutoMlBulkTranslateBtn.prop('disabled', true);
            AutoMlBulkTranslateBtn.prop('title', 'Bulk translate is unavailable in (' + currentLanguage + ') language.');
        }
    
        if(AutoMlSubsubsubList.length){
            const $defaultSubsubsub = jQuery('ul.subsubsub:not(.automlp_wpml_subsubsub_list)');
    
            if($defaultSubsubsub.length){
                $defaultSubsubsub.after(AutoMlSubsubsubList);
                AutoMlSubsubsubList.show();
            }
        }
    
        const appendButton=(btn)=>{
            if(!btn.length || btn.length < 0){
                return;
            }
    
            const $defaultFilter = jQuery('.actions:not(.bulkactions)');
            const $bulkAction=jQuery('.actions.bulkactions');
    
            if($defaultFilter.length){
                $defaultFilter.each(function(){
                    const clone=btn.clone(true);
                    jQuery(this).append(clone);
                    clone.show();
                });
    
                btn.remove();
            }else if($bulkAction.length){
                $bulkAction.each(function(){
                    const clone=btn.clone(true);
                    jQuery(this).after(clone);
                    clone.show();
                });
            }
        }
    
        appendButton(AutoMlBulkTranslateBtn);
    }
    
    window.addEventListener('load', () => {
        enableBulkTranslateBtn();
    });
})();