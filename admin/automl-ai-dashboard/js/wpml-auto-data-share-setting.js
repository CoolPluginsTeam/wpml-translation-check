jQuery(function ($) {
	// Toggle extra “terms” text in the settings screen (if present).
	const $termsLink = $('.wpml-auto-see-terms'); 
	const $termsBox  = $('#wpml-auto-terms-box, #termsBox');

	if (!$termsLink.length || !$termsBox.length) {
		return;
	}

	$termsLink.on('click', function (e) {
		e.preventDefault();

		const isVisible = $termsBox.toggle().is(':visible');

		$(this).html(isVisible ? 'Hide Terms' : 'See terms');
		$(this).attr('aria-expanded', isVisible);
	});
});