jQuery(document).ready(function($){
    $('.cpt-dashboard-tab').click(function(){
        var tab = $(this).data('tab');
        $('.cpt-dashboard-table').hide();
        $('#cpt-'+tab+'-table').show();

        $('.cpt-dashboard-tab').removeClass('cpt-active');
        $(this).addClass('cpt-active');

        $('.cpt-dashboard-tables').find('table').hide();
        $('#cpt-'+tab+'-table').show();
    });

    $('.atfpp-review-notice-dismiss button').click(function(){
        var prefix = $(this).closest('.atfpp-review-notice-dismiss').data('prefix');
        var nonce = $(this).closest('.atfpp-review-notice-dismiss').data('nonce');

        $.post(ajaxurl, {action: 'atfpp_hide_review_notice', prefix: prefix, nonce: nonce}, (response)=>{
            $(this).closest('.cpt-review-notice').slideUp();
        });
    });
});