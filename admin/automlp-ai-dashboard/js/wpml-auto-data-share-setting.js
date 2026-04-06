jQuery(function ($) {
	// Toggle extra “terms” text in the settings screen (if present).
	const $termsLink = $('.wpml-auto-see-terms'); 
	const $termsBox  = $('#wpml-auto-terms-box, #termsBox');

	$termsLink.on('click', function (e) {
		e.preventDefault();

		const isVisible = $termsBox.toggle().is(':visible');

		$(this).html(isVisible ? 'Hide Terms' : 'See terms');
		$(this).attr('aria-expanded', isVisible);
	});

	$('.automlp-provider-toggle').on('change', function() {	
        const checkedProviders = $('.automlp-provider-toggle:checked');
        const enabledProviders={};
        console.log(checkedProviders);
        checkedProviders.each(function() {
            enabledProviders[$(this).data('provider')] = true;
        });

        $.ajax({
            url: automlpSettingsScriptData.ajax_url,
            type: 'POST',
            data: {
                action: 'automlp_update_enabled_providers',
                enabled_providers: JSON.stringify(enabledProviders),
                update_providers_key: automlpSettingsScriptData.nonce
            },
            success: function(response) {
                if(response.success === true && response.data.providers){
                    const updatedProviders = response.data.providers;
                    checkedProviders.each(function() {
                        if(updatedProviders.includes($(this).data('provider'))){
                            $(this).prop('checked', true);
                        }else{
                            $(this).prop('checked', false);
                        }
                    });
                }else{
                    console.log(response.data.message);
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });

    $('.automlp-provider-switch-container[data-provider]').on('click', function(e) {
        const provider = $(this).data('provider');
        const utm_link=automlpSettingsScriptData.buy_pro_url + '?utm_source=automlp_plugin&utm_campaign=get_pro&utm_content=dashboard_'+provider;
        window.open(utm_link, '_blank');
        e.preventDefault();
    });
});