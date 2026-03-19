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
    
        const applyAnimation = () => {
            const oldStyle = document.getElementById('ai-pro-btn-style');
            if (oldStyle) oldStyle.remove();

            const style = document.createElement('style');
            style.id = 'ai-pro-btn-style';
            style.innerHTML = `
                .pro-attention-btn {
                    position: relative;
                    transition: all 0.3s ease !important;
                    animation: cleanPulse 2s infinite;
                    border: 1px solid #2271b1 !important;
                    z-index: 1;
                }
                @keyframes cleanPulse {
                    0%   { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0.7); transform: scale(1); }
                    70%  { box-shadow: 0 0 0 10px rgba(34, 113, 177, 0); transform: scale(1.02); }
                    100% { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0); transform: scale(1); }
                }
                .pro-attention-btn:hover {
                    animation: none;
                    box-shadow: 0 3px 6px rgba(0,0,0,0.2) !important;
                    transform: translateY(-1px);
                }
                .pro-attention-btn:active {
                    transform: translateY(0);
                }
            `;
            document.head.appendChild(style);

            jQuery('.automlp-wpml-bulk-translate-btn').addClass('pro-attention-btn');
        };

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

        // Apply animation only if user came from the settings button
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('automlp_translation')){
            applyAnimation();
        }
    }
    
    window.addEventListener('load', () => {
        enableBulkTranslateBtn();
    });
})();