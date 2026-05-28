( function () {
	'use strict';

	if ( typeof swishAcPopups === 'undefined' || ! Array.isArray( swishAcPopups.popups ) ) {
		return;
	}

	var cfg     = swishAcPopups;
	var popups  = cfg.popups;
	var storage = safeStorage();

	document.addEventListener( 'DOMContentLoaded', function () {
		popups.forEach( function ( popup ) {
			if ( isBlocked( popup ) ) {
				return;
			}
			attachTrigger( popup );
		} );
	} );

	function attachTrigger( popup ) {
		var t = popup.trigger || {};
		switch ( t.type ) {
			case 'time':
				setTimeout( function () { openModal( popup ); }, Math.max( 0, t.seconds * 1000 ) );
				break;
			case 'scroll':
				var onScroll = function () {
					var max = document.documentElement.scrollHeight - window.innerHeight;
					if ( max <= 0 ) return;
					var pct = ( window.scrollY / max ) * 100;
					if ( pct >= t.percent ) {
						window.removeEventListener( 'scroll', onScroll );
						openModal( popup );
					}
				};
				window.addEventListener( 'scroll', onScroll, { passive: true } );
				break;
			case 'exit':
				var onLeave = function ( e ) {
					if ( e.clientY <= 0 ) {
						document.removeEventListener( 'mouseout', onLeave );
						openModal( popup );
					}
				};
				document.addEventListener( 'mouseout', onLeave );
				break;
			case 'click':
				if ( ! t.selector ) return;
				document.addEventListener( 'click', function ( e ) {
					var target = e.target.closest( t.selector );
					if ( target ) {
						e.preventDefault();
						openModal( popup );
					}
				} );
				break;
		}
	}

	function openModal( popup ) {
		if ( document.querySelector( '.swish-ac-popup-modal[data-popup="' + popup.id + '"]' ) ) {
			return;
		}

		var modal = document.createElement( 'div' );
		modal.className = 'swish-ac-popup-modal';
		modal.setAttribute( 'data-popup', popup.id );
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );

		modal.innerHTML =
			'<div class="swish-ac-popup-modal__dialog">' + popup.html + '</div>';
		var popupEl = modal.querySelector( '.swish-ac-popup' );
		if ( popupEl ) {
			popupEl.insertAdjacentHTML( 'afterbegin',
				'<button type="button" class="swish-ac-popup-modal__close" aria-label="Close">&times;</button>'
			);
		}

		document.body.appendChild( modal );
		requestAnimationFrame( function () { modal.classList.add( 'is-open' ); } );

		var dialog = modal.querySelector( '.swish-ac-popup-modal__dialog' );
		var form   = modal.querySelector( '.swish-ac-popup-form__form' );
		var closeBtn = modal.querySelector( '.swish-ac-popup-modal__close' );

		var opener = document.activeElement;

		function close( dismissed ) {
			modal.classList.remove( 'is-open' );
			setTimeout( function () {
				modal.remove();
				if ( opener && typeof opener.focus === 'function' ) opener.focus();
			}, 150 );
			document.removeEventListener( 'keydown', onKey );
			if ( dismissed ) {
				setBlock( popup, 'dismiss' );
			}
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) { close( true ); return; }
			if ( e.key === 'Tab' ) {
				var focusables = modal.querySelectorAll( 'button, input, [tabindex]:not([tabindex="-1"])' );
				if ( ! focusables.length ) return;
				var first = focusables[0];
				var last  = focusables[ focusables.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault(); last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault(); first.focus();
				}
			}
		}

		closeBtn.addEventListener( 'click', function () { close( true ); } );
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) close( true );
		} );
		document.addEventListener( 'keydown', onKey );

		var firstField = dialog.querySelector( 'input' );
		if ( firstField ) setTimeout( function () { firstField.focus(); }, 50 );

		if ( ! form ) return;

		var submitBtn = form.querySelector( '.swish-ac-popup-form__submit' );
		var errorEl   = form.querySelector( '.swish-ac-popup-form__error' );
		var popupWrap = form.closest( '.swish-ac-popup' );
		var successMsg = ( popupWrap && popupWrap.getAttribute( 'data-success' ) ) || 'Thanks!';

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( errorEl ) errorEl.textContent = '';

			var emailField = form.querySelector( 'input[name="email"]' );
			var nameField  = form.querySelector( 'input[name="name"]' );
			var email = emailField ? emailField.value.trim() : '';
			var name  = nameField ? nameField.value.trim() : '';

			if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
				if ( errorEl ) errorEl.textContent = 'Please enter a valid email.';
				if ( emailField ) emailField.focus();
				return;
			}

			if ( submitBtn ) submitBtn.disabled = true;

			fetch( cfg.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   cfg.nonce
				},
				body: JSON.stringify( {
					source:   'popup',
					popup_id: popup.id,
					email:    email,
					name:     name
				} )
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.body && res.body.ok ) {
						setBlock( popup, 'submit' );
						showSuccess( dialog, successMsg );
					} else {
						if ( errorEl ) errorEl.textContent = ( res.body && res.body.message ) || 'Something went wrong.';
						if ( submitBtn ) submitBtn.disabled = false;
					}
				} )
				.catch( function () {
					if ( errorEl ) errorEl.textContent = 'Network error. Please try again.';
					if ( submitBtn ) submitBtn.disabled = false;
				} );
		} );
	}

	function showSuccess( dialog, message ) {
		var inner = dialog.querySelector( '.swish-ac-popup' );
		var html  = '<p class="swish-ac-popup__success">' + escapeHtml( message ) + '</p>';
		if ( inner ) {
			inner.innerHTML = html;
		} else {
			dialog.innerHTML = html;
		}
	}

	function storageKey( popupId ) { return 'swish_popup_' + popupId; }

	function isBlocked( popup ) {
		var raw = storage.get( storageKey( popup.id ) );
		if ( ! raw ) return false;
		try {
			var data = JSON.parse( raw );
			if ( data.until === 'forever' ) return true;
			if ( typeof data.until === 'number' && Date.now() < data.until ) return true;
		} catch ( e ) {}
		return false;
	}

	function setBlock( popup, reason ) {
		var freq = popup.freq || {};
		if ( reason === 'submit' && freq.hideAfterSubmit ) {
			storage.set( storageKey( popup.id ), JSON.stringify( { until: 'forever' } ) );
			return;
		}
		if ( reason === 'dismiss' && freq.days > 0 ) {
			var until = Date.now() + freq.days * 86400000;
			storage.set( storageKey( popup.id ), JSON.stringify( { until: until } ) );
		}
	}

	function safeStorage() {
		try {
			window.localStorage.setItem( '__swish_test', '1' );
			window.localStorage.removeItem( '__swish_test' );
			return {
				get: function ( k ) { return window.localStorage.getItem( k ); },
				set: function ( k, v ) { window.localStorage.setItem( k, v ); }
			};
		} catch ( e ) {
			var mem = {};
			return {
				get: function ( k ) { return mem[ k ] || null; },
				set: function ( k, v ) { mem[ k ] = v; }
			};
		}
	}

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}
} )();
