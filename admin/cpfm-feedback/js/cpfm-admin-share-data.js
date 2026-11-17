jQuery(function($) {
    $(document).on('click', '.cpfm-see-terms, .ect-see-terms, .tecc-see-terms', function(e) {
        e.preventDefault();
        const $termsBox = $(this).siblings('#termsBox, .ect-terms-box, .tecc-terms-box');
        const $targetBox = $termsBox.length ? $termsBox : $('#termsBox, .ect-terms-box, .tecc-terms-box');
        const isVisible = $targetBox.toggle().is(':visible');
        $(this).html(isVisible ? 'Hide Terms' : 'See terms');
    });
});