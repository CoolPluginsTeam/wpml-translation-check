jQuery(document).ready(function($) {
    $('#ect_cpfm_feedback_data, #tecc-cpfm-data-sharing, #ctl_cpfm_feedback_data').on('change', function() {
        let isChecked = $(this).is(':checked') ? 'yes' : 'no';
        if (typeof ajaxurl !== 'undefined' && typeof cpfm_ajax_obj !== 'undefined') {
            $.post(ajaxurl, {
                action: 'cpfm_save_usage_data_sharing',
                opt_in: isChecked,
                nonce: cpfm_ajax_obj.nonce
            });
        }
    });
});
