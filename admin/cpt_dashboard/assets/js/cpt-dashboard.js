jQuery(document).ready(function ($) {
    $('.cpt-dashboard-tab').click(function () {
        var tab = $(this).data('tab');
        $('.cpt-dashboard-table').hide();
        $('#cpt-' + tab + '-table').show();

        $('.cpt-dashboard-tab').removeClass('cpt-active');
        $(this).addClass('cpt-active');

        $('.cpt-dashboard-tables').find('table').hide();
        $('#cpt-' + tab + '-table').show();
    });

    // Dismiss review notice (Already Reviewed / Not Interested)
    $(document).on('click', '.cpt-not-interested', function (e) {
        e.preventDefault();
        hideReviewNotice($(this).closest('.cpt-review-notice'));
    });
    $(document).on('click', '.cpt-review-notice .button-primary', function () {
        hideReviewNotice($(this).closest('.cpt-review-notice'));
    });
    
    // Shared function to fire AJAX and slide up
    function hideReviewNotice($wrap) {
        var prefix = $wrap.data('prefix');
        var nonce  = $wrap.data('nonce');
    
        $.post(
            ajaxurl,
            {
                action: 'automlp_ai_hide_review_notice',
                prefix: prefix,
                nonce: nonce
            },
            function () {
                $wrap.slideUp();
            }
        );
    }
});