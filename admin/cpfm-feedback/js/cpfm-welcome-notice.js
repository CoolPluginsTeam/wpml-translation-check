// CPFM_Welcome_Notice — see admin/cpfm-feedback/class-cpfm-welcome-notice.php.
//
// The x is a soft close: hide it for now, save nothing. Only
// "See new settings" and "Dismiss" retire the notice for good.
document.addEventListener( 'click', function ( e ) {
	if ( ! e.target.closest ) { return; }
	var x = e.target.closest( '[data-cpfm-welcome-close]' );
	if ( ! x ) { return; }
	e.preventDefault();
	var box = x.closest( '[data-cpfm-welcome]' );
	if ( box ) { box.style.display = 'none'; }
} );
