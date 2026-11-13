/**
 * Translation queue screen.
 *
 * Sorting, status tabs, search, filters, paging and the row/bulk actions refresh the table in
 * place instead of reloading the admin page, as WPML's own tables do. Every control is still a
 * normal link or form, so the screen works the same without JavaScript, and any failed request
 * falls back to a regular page load.
 *
 * Settings come from the automlpQueue object printed by Queue_Screen::enqueue().
 */
( function () {
	'use strict';

	var settings = window.automlpQueue || {};
	var PARTIAL = 'automlp_partial';
	var requestId = 0;
	var controller = null;
	var refreshTimer = 0;
	var skeletonTimer = 0;
	var pushed = false;

	// A reply faster than this never shows the skeleton, so quick updates do not flicker.
	var SKELETON_DELAY = 140;

	function getRoot() {
		return document.querySelector( '.automlp-queue' );
	}

	if ( ! getRoot() || ! window.fetch ) {
		return;
	}

	function all( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	/* ------------------------------------------------------------------ selection */

	function rowChecks() {
		return all( '[data-row-check]', getRoot() );
	}

	function selectedCount() {
		return rowChecks().filter( function ( box ) {
			return box.checked;
		} ).length;
	}

	// Apply stays disabled until an action is chosen and at least one job is ticked.
	function syncBulk() {
		var root = getRoot();
		var checks = rowChecks();
		var count = selectedCount();
		var selectAll = root.querySelector( '[data-select-all]' );
		var bulkSelect = root.querySelector( '[data-bulk-select]' );
		var bulkApply = root.querySelector( '[data-bulk-apply]' );

		if ( bulkApply ) {
			bulkApply.disabled = ! ( count > 0 && bulkSelect && bulkSelect.value );
		}

		if ( selectAll && checks.length ) {
			selectAll.checked = count === checks.length;
			selectAll.indeterminate = count > 0 && count < checks.length;
		}
	}

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var root = getRoot();

		if ( ! root || ! root.contains( target ) ) {
			return;
		}

		if ( target.matches( '[data-select-all]' ) ) {
			rowChecks().forEach( function ( box ) {
				box.checked = target.checked;
			} );
			syncBulk();
		} else if ( target.matches( '[data-row-check], [data-bulk-select]' ) ) {
			syncBulk();
		} else if ( target.matches( '.automlp-queue__filters select' ) ) {
			// The dropdowns filter as soon as they change, like WPML's column filters.
			submitFilters( target.form );
		}
	} );

	/* --------------------------------------------------------------------- loading */

	function withoutPartial( url ) {
		var parsed = new window.URL( url, window.location.href );

		parsed.searchParams.delete( PARTIAL );

		return parsed.toString();
	}

	function withPartial( url ) {
		var parsed = new window.URL( url, window.location.href );

		parsed.searchParams.set( PARTIAL, '1' );

		return parsed.toString();
	}

	// Whether someone is in the middle of something the page must not overwrite.
	function isBusy() {
		var active = document.activeElement;
		var typing = active && /^(INPUT|SELECT|TEXTAREA)$/.test( active.tagName );
		var search = getRoot().querySelector( '.automlp-queue__search' );

		return selectedCount() > 0 || !! typing || ( search && search.value !== search.defaultValue );
	}

	function scheduleRefresh() {
		var root = getRoot();

		window.clearTimeout( refreshTimer );

		// Only while translations are running, so progress shows up without a manual reload.
		if ( ! root || ! parseInt( root.getAttribute( 'data-active' ), 10 ) ) {
			return;
		}

		refreshTimer = window.setTimeout( function () {
			if ( isBusy() || document.hidden ) {
				scheduleRefresh();
				return;
			}

			load( window.location.href, { push: false, silent: true } );
		}, settings.refreshMs || 15000 );
	}

	function afterSwap( options ) {
		var root = getRoot();
		var target = options.focus ? root.querySelector( options.focus ) : null;

		syncBulk();

		if ( target ) {
			target.focus();
		}

		if ( options.scroll ) {
			var card = root.querySelector( '.automlp-queue__card' );

			if ( card && card.getBoundingClientRect().top < 0 ) {
				card.scrollIntoView( { block: 'start' } );
			}
		}

		// WordPress makes notices dismissible on load only, so ask it to do it again.
		if ( window.jQuery ) {
			window.jQuery( document ).trigger( 'wp-updates-notice-added' );
		}

		scheduleRefresh();
	}

	function announce( message ) {
		if ( message && window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( message );
		}
	}

	function bar( modifier ) {
		return '<span class="automlp-queue__sk ' + modifier + '"></span>';
	}

	// One placeholder cell per column, with the width class of the real column, so nothing jumps.
	function skeletonCell( column ) {
		var inner = bar( 'is-md' );

		if ( 'col-check' === column ) {
			inner = bar( 'is-check' );
		} else if ( 'col-id' === column ) {
			inner = bar( 'is-xs' );
		} else if ( 'col-content' === column ) {
			inner = bar( 'is-lg' ) + bar( 'is-sm is-sub' );
		} else if ( 'col-status' === column ) {
			inner = bar( 'is-pill' );
		} else if ( 'col-actions' === column ) {
			inner = bar( 'is-btn' );
		}

		return '<td class="' + column + '">' + inner + '</td>';
	}

	/**
	 * Swap the table rows (or the empty message) for shimmering placeholders, as YouTube or
	 * Instagram do while a feed loads. The header, filters and tabs stay, so the screen keeps its shape.
	 */
	function showSkeleton() {
		var root = getRoot();
		var table = root.querySelector( '.automlp-queue__table' );
		var empty = root.querySelector( '.automlp-queue__empty' );
		var columns;
		var count;
		var html = '';
		var i;
		var j;

		root.classList.add( 'is-loading' );

		if ( table && table.tBodies[0] ) {
			columns = all( 'thead th, thead td', table ).map( function ( cell ) {
				var found = cell.className.match( /col-[a-z]+/ );

				return found ? found[0] : 'col-other';
			} );
			count = Math.min( Math.max( table.tBodies[0].rows.length, 6 ), 25 );

			for ( i = 0; i < count; i++ ) {
				html += '<tr class="automlp-queue__skeleton-row" aria-hidden="true">';

				for ( j = 0; j < columns.length; j++ ) {
					html += skeletonCell( columns[ j ] );
				}

				html += '</tr>';
			}

			table.tBodies[0].innerHTML = html;
		} else if ( empty ) {
			empty.classList.add( 'is-skeleton' );
			empty.innerHTML = bar( 'is-lg' ) + bar( 'is-md' ) + bar( 'is-lg' );
		}

		announce( settings.loading );
	}

	/**
	 * Fetch the queue fragment and swap it in.
	 *
	 * @param {string}   url      Page to show.
	 * @param {Object}   options  push: add a history entry. silent: no loading state (auto refresh).
	 * @param {Object=}  init     fetch() options, for form posts.
	 * @param {Function=} fallback Called when the request cannot be made (instead of a page load).
	 */
	function load( url, options, init, fallback ) {
		var root = getRoot();
		var id = ++requestId;
		var request = init || {};

		if ( controller && controller.abort ) {
			controller.abort();
		}

		controller = window.AbortController ? new window.AbortController() : null;

		if ( controller ) {
			request.signal = controller.signal;
		}

		request.credentials = 'same-origin';
		request.headers = { 'X-Requested-With': 'XMLHttpRequest' };

		window.clearTimeout( skeletonTimer );

		if ( ! options.silent ) {
			root.setAttribute( 'aria-busy', 'true' );

			skeletonTimer = window.setTimeout( function () {
				if ( id === requestId ) {
					showSkeleton();
				}
			}, SKELETON_DELAY );
		}

		window.fetch( 'POST' === request.method ? url : withPartial( url ), request )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.text().then( function ( html ) {
					return { html: html, url: response.url };
				} );
			} )
			.then( function ( result ) {
				var holder = document.createElement( 'div' );
				var fresh;

				if ( id !== requestId ) {
					return;
				}

				holder.innerHTML = result.html;
				fresh = holder.querySelector( '.automlp-queue' );

				if ( ! fresh ) {
					throw new Error( 'Unexpected response' );
				}

				root = getRoot();
				root.innerHTML = fresh.innerHTML;
				root.setAttribute( 'data-active', fresh.getAttribute( 'data-active' ) || '0' );

				if ( options.push ) {
					window.history.pushState( { automlpQueue: true }, '', withoutPartial( result.url ) );
					pushed = true;
				}

				window.clearTimeout( skeletonTimer );
				afterSwap( options );

				if ( ! options.silent ) {
					announce( settings.loaded );
				}
			} )
			.catch( function ( error ) {
				if ( id !== requestId || ( error && 'AbortError' === error.name ) ) {
					return;
				}

				if ( options.silent ) {
					scheduleRefresh();
					return;
				}

				// Never leave the screen stuck: do it the old way.
				if ( fallback ) {
					fallback();
				} else {
					window.location.href = withoutPartial( url );
				}
			} )
			.then( function () {
				if ( id === requestId ) {
					window.clearTimeout( skeletonTimer );
					root = getRoot();
					root.classList.remove( 'is-loading' );
					root.removeAttribute( 'aria-busy' );
				}
			} );
	}

	/* ---------------------------------------------------------------- navigation */

	// Where keyboard focus goes after the table is replaced.
	function focusFor( link ) {
		if ( link.closest( 'th.is-sortable' ) ) {
			return 'th.is-sorted a';
		}

		if ( link.closest( '.automlp-queue__views' ) ) {
			return '.automlp-queue__views a.is-active';
		}

		return '';
	}

	document.addEventListener( 'click', function ( event ) {
		var link;
		var root = getRoot();

		if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		link = event.target.closest ? event.target.closest( 'a' ) : null;

		if ( ! link || ! root || ! root.contains( link ) ) {
			return;
		}

		if ( ! link.matches( '.automlp-queue__views a, th.is-sortable a, .automlp-queue__pagination a, .automlp-queue__reset, .automlp-queue__empty a' ) ) {
			return;
		}

		event.preventDefault();
		load( link.href, {
			push: true,
			focus: focusFor( link ),
			scroll: !! link.closest( '.automlp-queue__pagination' ),
		} );
	} );

	function submitFilters( form ) {
		var params = new window.URLSearchParams();

		// Empty fields stay out of the URL, so it matches the one the links build.
		new window.FormData( form ).forEach( function ( value, key ) {
			if ( '' !== value ) {
				params.append( key, value );
			}
		} );

		// getAttribute(): a field named "action" would shadow form.action.
		load( form.getAttribute( 'action' ) + '?' + params.toString(), { push: true } );
	}

	function confirmationFor( form ) {
		var kind = form.getAttribute( 'data-confirm' );
		var select;

		if ( 'clear' === kind ) {
			return settings.confirmClear;
		}

		if ( 'delete-one' === kind ) {
			return settings.confirmDeleteOne;
		}

		if ( form.matches( '.automlp-queue__bulk-form' ) ) {
			select = form.querySelector( '[data-bulk-select]' );

			if ( select && 'delete' === select.value ) {
				return settings.confirmDelete;
			}
		}

		return '';
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var root = getRoot();
		var message;
		var data;

		if ( ! root || ! form.matches || ! root.contains( form ) ) {
			return;
		}

		if ( form.matches( '.automlp-queue__filters' ) ) {
			event.preventDefault();
			submitFilters( form );
			return;
		}

		if ( 'post' !== form.method.toLowerCase() ) {
			return;
		}

		message = confirmationFor( form );

		event.preventDefault();

		if ( message && ! window.confirm( message ) ) {
			return;
		}

		// Row, bulk and header actions: post in the background and show the result in place.
		data = new window.FormData( form );
		data.append( PARTIAL, '1' );

		load(
			form.getAttribute( 'action' ),
			{ push: true },
			{ method: 'POST', body: data },
			function () {
				form.submit();
			}
		);
	} );

	window.addEventListener( 'popstate', function () {
		// Back and forward between the views this script pushed.
		if ( pushed ) {
			load( window.location.href, { push: false } );
		}
	} );

	syncBulk();
	scheduleRefresh();
}() );
