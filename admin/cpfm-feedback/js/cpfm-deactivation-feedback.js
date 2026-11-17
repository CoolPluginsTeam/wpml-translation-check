/**
 * CPFM Deactivation Feedback — modal behaviour (vanilla, no jQuery).
 *
 * Intercepts each registered plugin's Deactivate link on plugins.php, opens the
 * matching modal (focus-trapped, ESC/backdrop to close), and on submit POSTs the
 * reason to the plugin's feedback API before letting the deactivation proceed.
 * Feedback failure never blocks deactivation.
 */
( function () {
	'use strict';

	var cfg = window.cpfmDeactivation;
	if ( ! cfg || ! cfg.plugins ) {
		return;
	}

	var openModal = null;
	var deactivateUrl = null;
	var lastFocus = null;

	function qs( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}
	function qsa( sel, ctx ) {
		return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) );
	}

	function open( modal, url ) {
		openModal = modal;
		deactivateUrl = url;
		lastFocus = document.activeElement;
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		// Focus the DIALOG, not the first radio: focusing a radio draws a ring on
		// it and reads as "already selected". The dialog is focusable (tabindex
		// -1) so screen readers still land inside and the tab trap still works.
		var dialog = qs( '.cpfm-df__dialog', modal );
		if ( dialog ) {
			dialog.focus();
		}
		document.addEventListener( 'keydown', onKey );
	}

	function close() {
		if ( ! openModal ) {
			return;
		}
		openModal.classList.remove( 'is-open', 'is-sending' );
		openModal.setAttribute( 'aria-hidden', 'true' );
		// Restore the submit button. The send path disables it and swaps the
		// label to "Deactivating…"; closing mid-flight (ESC inside the sendBeacon
		// release window, or the fallback deadline) would otherwise leave a dead
		// button behind the next time the modal opens.
		var submitBtn = qs( '[data-cpfm-df-submit]', openModal );
		if ( submitBtn ) {
			submitBtn.disabled = false;
			var prevLabel = submitBtn.getAttribute( 'data-cpfm-df-label' );
			var labelEl   = qs( '.cpfm-df__submit-label', submitBtn );
			if ( labelEl && prevLabel ) {
				labelEl.textContent = prevLabel;
			}
		}
		document.removeEventListener( 'keydown', onKey );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
		openModal = null;
		deactivateUrl = null;
	}

	function onKey( e ) {
		var k = e.key || e.keyCode;
		if ( 'Escape' === k || 27 === k ) {
			close();
			return;
		}
		if ( ( 'Tab' === k || 9 === k ) && openModal ) {
			trapTab( e );
		}
	}

	function trapTab( e ) {
		var focusables = qsa( 'a[href], button:not([disabled]), textarea, input, [tabindex]:not([tabindex="-1"])', openModal )
			.filter( function ( el ) {
				return null !== el.offsetParent;
			} );
		if ( ! focusables.length ) {
			return;
		}
		var first = focusables[ 0 ];
		var last = focusables[ focusables.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function wireModal( modal, id ) {
		qsa( '[data-cpfm-df-close]', modal ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				close();
			} );
		} );

		var skip = qs( '[data-cpfm-df-skip]', modal );
		if ( skip ) {
			skip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( deactivateUrl ) {
					window.location.href = deactivateUrl;
				}
			} );
		}

		var submit = qs( '[data-cpfm-df-submit]', modal );
		if ( submit ) {
			submit.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				doSubmit( modal, id, submit );
			} );
		}

		// Picking a reason drops the caret straight into that reason's note, so
		// people can type without a second click. (Reasons with no note — e.g.
		// "temporary deactivation" — simply have nothing to focus.)
		qsa( '.cpfm-df__radio', modal ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				var err = qs( '.cpfm-df__error', modal );
				if ( err ) {
					err.hidden = true; // A choice was made — clear "choose a reason".
				}

				// Scope to the ROW, not radio.parentNode: the radio now sits in an
				// inner <label> that deliberately does not contain the textarea.
				var row  = radio.closest ? radio.closest( '.cpfm-df__option' ) : null;
				var note = row ? row.querySelector( '.cpfm-df__note' ) : null;
				if ( note ) {
					note.focus();
				}
			} );
		} );
	}

	/**
	 * Labels for one plugin. cfg.i18n is keyed by plugin id because each host
	 * ships its own translated copy; the fallbacks keep the modal usable if a
	 * host registered without any.
	 */
	function labels( id ) {
		var all = ( cfg && cfg.i18n ) ? cfg.i18n : {};
		return all[ id ] || {};
	}

	function doSubmit( modal, id, submit ) {
		var checked = qs( '.cpfm-df__radio:checked', modal );
		var err = qs( '.cpfm-df__error', modal );
		if ( ! checked ) {
			if ( err ) {
				err.textContent = labels( id ).pickReason || 'Please choose a reason.';
				err.hidden = false;
			}
			return;
		}
		if ( err ) {
			err.hidden = true;
		}

		var reason = checked.value;
		var note = qs( '.cpfm-df__note[data-reason="' + reason + '"]', modal );
		var message = note ? note.value : '';

		modal.classList.add( 'is-sending' );
		submit.disabled = true;
		var label = qs( '.cpfm-df__submit-label', submit );
		if ( label ) {
			// Remember the original so close() can put it back (see close()).
			if ( ! submit.getAttribute( 'data-cpfm-df-label' ) ) {
				submit.setAttribute( 'data-cpfm-df-label', label.textContent );
			}
			label.textContent = labels( id ).deactivating || 'Deactivating…';
		}

		var body = 'action=cpfm_deactivation_feedback'
			+ '&nonce=' + encodeURIComponent( cfg.nonce )
			+ '&plugin_id=' + encodeURIComponent( id )
			+ '&reason=' + encodeURIComponent( reason )
			+ '&message=' + encodeURIComponent( message );

		// Deactivation must NEVER wait on feedback. proceed() runs on success, on
		// failure, or after a short deadline — whichever comes first — and only
		// once. Sending feedback is best-effort; deactivating is the user's
		// actual intent.
		var done = false;
		var proceed = function () {
			if ( done ) {
				return;
			}
			done = true;
			if ( deactivateUrl ) {
				window.location.href = deactivateUrl;
			}
		};

		// Preferred path: hand the payload to the BROWSER, which delivers it
		// independently of this page's lifetime, then deactivate almost at once.
		//
		// Two traps this avoids:
		// 1. The body MUST be a Blob typed application/x-www-form-urlencoded. A
		//    plain string is sent as text/plain, PHP then leaves $_POST empty and
		//    admin-ajax dies with "0" — silent, total feedback loss.
		// 2. Do NOT navigate on the very next line. The beacon's admin-ajax
		//    request and the deactivation run concurrently, and this handler is
		//    registered BY the plugin being deactivated — if admin-ajax boots
		//    after active_plugins is rewritten, the action no longer exists. A
		//    short head start removes that race and is still ~10x faster than the
		//    fetch fallback.
		if ( window.navigator && navigator.sendBeacon && window.Blob ) {
			var queued = false;
			try {
				queued = navigator.sendBeacon(
					cfg.ajaxurl,
					new Blob( [ body ], {
						type: 'application/x-www-form-urlencoded;charset=UTF-8'
					} )
				);
			} catch ( e ) {
				queued = false;
			}

			if ( queued ) {
				window.setTimeout( proceed, 300 );
				return;
			}
			// Queueing failed (e.g. payload over the browser's beacon budget) —
			// fall through to the request path below.
		}

		// Fallback: wait for the request, but never longer than the deadline.
		window.setTimeout( proceed, 3000 );

		if ( window.fetch ) {
			window.fetch( cfg.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			} ).then( proceed, proceed );
		} else {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.ajaxurl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.onreadystatechange = function () {
				if ( 4 === xhr.readyState ) {
					proceed();
				}
			};
			xhr.send( body );
		}
	}

	function init() {
		Object.keys( cfg.plugins ).forEach( function ( id ) {
			var slug = cfg.plugins[ id ];
			var row = qs( '#the-list tr[data-slug="' + slug + '"]' ) || qs( 'tr[data-slug="' + slug + '"]' );
			var link = row ? qs( '.deactivate a', row ) : null;
			var modal = qs( '#cpfm-df-' + id );
			if ( ! modal ) {
				return;
			}
			if ( link ) {
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					open( modal, link.getAttribute( 'href' ) );
				} );
			}
			wireModal( modal, id );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
