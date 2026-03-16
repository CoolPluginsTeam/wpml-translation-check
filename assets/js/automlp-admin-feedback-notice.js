jQuery(document).ready(function ($) {
    $('.automlp_dismiss_notice').on('click', function (event) {
        var $this = $(this);
        var wrapper = $this.parents('.cool-feedback-notice-wrapper');
        var ajaxURL = wrapper.data('ajax-url');
        var ajaxCallback = wrapper.data('ajaxCallback');
        var ajaxNonce = wrapper.data("nonce");
        $.post(ajaxURL, {
            'action': ajaxCallback,
            'nonce': ajaxNonce
        }, function (data) {
            wrapper.slideUp('fast');
        }, "json");

    });
});