// CPFM_Review_Assets — shared click handler for all three review surfaces
// (notice, plugin row, inline embed). See
// admin/cpfm-feedback/review/class-cpfm-review-assets.php.
( function () {
	function key( id ) { return 'cpfmRvHideUntil_' + id; }

	function hidden( id ) {
		try {
			var until = parseInt( window.localStorage.getItem( key( id ) ), 10 );
			return until && new Date().getTime() < until;
		} catch ( e ) { return false; }
	}

	function hideFor( id, hours ) {
		try {
			var ms = ( parseInt( hours, 10 ) || 24 ) * 60 * 60 * 1000;
			window.localStorage.setItem( key( id ), String( new Date().getTime() + ms ) );
		} catch ( e ) {}
	}

	// Browser-side hides are re-applied as soon as the markup parses,
	// so a snoozed element never flashes before being hidden.
	function applyHidden() {
		var boxes = document.querySelectorAll( '[data-cpfm-rv]' );
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( hidden( boxes[ i ].getAttribute( 'data-cpfm-rv-id' ) ) ) {
				boxes[ i ].style.display = 'none';
			} else {
				boxes[ i ].removeAttribute( 'hidden' );
			}
		}
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', applyHidden );
	} else {
		applyHidden();
	}

	function post( box, what ) {
		if ( ! window.fetch || 'undefined' === typeof ajaxurl ) { return; }
		var body = 'action=cpfm_review_answer'
			+ '&id=' + encodeURIComponent( box.getAttribute( 'data-cpfm-rv-id' ) )
			+ '&what=' + encodeURIComponent( what )
			+ '&_wpnonce=' + encodeURIComponent( box.getAttribute( 'data-cpfm-rv-nonce' ) );
		window.fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		} );
	}

	// Hide every surface for this plugin at once: answering in the
	// notice must also clear the row and any inline embed on the page.
	function hideAllFor( id ) {
		var all = document.querySelectorAll( '[data-cpfm-rv][data-cpfm-rv-id="' + id + '"]' );
		for ( var i = 0; i < all.length; i++ ) { all[ i ].style.display = 'none'; }
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) { return; }
		var box = e.target.closest( '[data-cpfm-rv]' );
		if ( ! box ) { return; }
		var id = box.getAttribute( 'data-cpfm-rv-id' );

		// "Yes, I like it" - reveal step 2 in place. Records nothing:
		// liking the plugin is not an answer, submitting or dismissing is.
		if ( e.target.closest( '[data-cpfm-rv-yes]' ) ) {
			e.preventDefault();
			var s1 = box.querySelector( '[data-cpfm-rv-step="1"]' );
			var s2 = box.querySelector( '[data-cpfm-rv-step="2"]' );
			if ( s1 ) { s1.hidden = true; }
			if ( s2 ) {
				s2.hidden = false;
				// Restart the pulse explicitly: relying on the
				// display:none -> visible transition to (re)start a CSS
				// animation is not dependable across browsers.
				var cta = s2.querySelector( '.cpfm-rv__cta' );
				if ( cta ) {
					cta.style.animation = 'none';
					void cta.offsetWidth;
					cta.style.animation = '';
				}
			}
			return;
		}

		// "Ask me later" - SERVER-side, so it follows the user to
		// another browser or device.
		if ( e.target.closest( '[data-cpfm-rv-later]' ) ) {
			e.preventDefault();
			post( box, 'snooze' );
			hideAllFor( id );
			return;
		}

		// Close - browser-only. Nothing is saved server-side, so a
		// stray click can never hide the ask permanently.
		if ( e.target.closest( '[data-cpfm-rv-close]' ) ) {
			e.preventDefault();
			hideFor( id, box.getAttribute( 'data-cpfm-rv-hours' ) || 24 );
			box.style.display = 'none';
			return;
		}

		var answer = e.target.closest( '[data-cpfm-rv-answer]' );
		if ( ! answer ) { return; }

		post( box, 'answer' );

		// The review link must still open its new tab.
		if ( 'A' !== answer.tagName || '#' === answer.getAttribute( 'href' ) ) {
			e.preventDefault();
		}
		hideAllFor( id );
	} );
}() );
