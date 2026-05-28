( function () {
	'use strict';

	if ( typeof swishAcSaveTrip === 'undefined' ) {
		return;
	}

	var cfg  = swishAcSaveTrip;
	var copy = cfg.copy || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.querySelector( '.swish-save-trip' );
		if ( ! button ) {
			return;
		}
		button.addEventListener( 'click', function () {
			openModal( {
				productId:   button.getAttribute( 'data-product-id' ),
				productSlug: button.getAttribute( 'data-product-slug' ),
				productName: button.getAttribute( 'data-product-name' )
			} );
		} );
	} );

	function openModal( product ) {
		var existing = document.querySelector( '.swish-ac-modal[data-source="save_trip"]' );
		if ( existing ) {
			existing.remove();
		}

		var modal = document.createElement( 'div' );
		modal.className = 'swish-ac-modal';
		modal.setAttribute( 'data-source', 'save_trip' );
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-labelledby', 'swish-ac-modal-heading' );

		modal.innerHTML =
			'<div class="swish-ac-modal__dialog">' +
				'<button type="button" class="swish-ac-modal__close" aria-label="' + esc( copy.close || 'Close' ) + '">&times;</button>' +
				'<h2 id="swish-ac-modal-heading" class="swish-ac-modal__heading">' + esc( copy.heading || '' ) + '</h2>' +
				'<p class="swish-ac-modal__description">' + esc( copy.description || '' ) + '</p>' +
				'<form class="swish-ac-modal__form" novalidate>' +
					'<label class="swish-ac-modal__field">' +
						'<span>' + esc( copy.emailLabel || 'Email' ) + '</span>' +
						'<input type="email" name="email" required autocomplete="email" placeholder="' + esc( copy.emailPh || '' ) + '" value="' + esc( cfg.prefill || '' ) + '">' +
					'</label>' +
					'<button type="submit" class="swish-ac-modal__submit">' + esc( copy.submit || 'Submit' ) + '</button>' +
					'<p class="swish-ac-modal__error" role="alert"></p>' +
				'</form>' +
			'</div>';

		document.body.appendChild( modal );
		requestAnimationFrame( function () { modal.classList.add( 'is-open' ); } );

		var emailInput = modal.querySelector( 'input[name="email"]' );
		var form       = modal.querySelector( 'form' );
		var closeBtn   = modal.querySelector( '.swish-ac-modal__close' );
		var errorEl    = modal.querySelector( '.swish-ac-modal__error' );
		var submitBtn  = modal.querySelector( '.swish-ac-modal__submit' );
		var opener     = document.activeElement;

		setTimeout( function () { emailInput.focus(); }, 50 );

		function close() {
			modal.classList.remove( 'is-open' );
			setTimeout( function () {
				modal.remove();
				if ( opener && typeof opener.focus === 'function' ) opener.focus();
			}, 150 );
			document.removeEventListener( 'keydown', onKey );
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				close();
				return;
			}
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

		closeBtn.addEventListener( 'click', close );
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', onKey );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			errorEl.textContent = '';
			var email = ( emailInput.value || '' ).trim();
			if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
				errorEl.textContent = copy.errorEmail || 'Invalid email.';
				emailInput.focus();
				return;
			}

			submitBtn.disabled = true;

			fetch( cfg.restUrl, {
				method:      'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   cfg.nonce
				},
				body: JSON.stringify( {
					source:        'save_trip',
					email:         email,
					product_id:    product.productId,
					product_slug:  product.productSlug,
					product_name:  product.productName
				} )
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.body && res.body.ok ) {
						showSuccess( modal, res.body.message || copy.success || 'Saved.' );
					} else {
						errorEl.textContent = ( res.body && res.body.message ) || copy.errorGeneric || 'Error';
						submitBtn.disabled  = false;
					}
				} )
				.catch( function () {
					errorEl.textContent = copy.errorGeneric || 'Error';
					submitBtn.disabled  = false;
				} );
		} );
	}

	function showSuccess( modal, message ) {
		var dialog = modal.querySelector( '.swish-ac-modal__dialog' );
		dialog.innerHTML =
			'<button type="button" class="swish-ac-modal__close" aria-label="' + esc( ( swishAcSaveTrip.copy && swishAcSaveTrip.copy.close ) || 'Close' ) + '">&times;</button>' +
			'<h2 class="swish-ac-modal__heading">' + esc( ( swishAcSaveTrip.copy && swishAcSaveTrip.copy.heading ) || '' ) + '</h2>' +
			'<p class="swish-ac-modal__success">' + esc( message ) + '</p>';
		dialog.querySelector( '.swish-ac-modal__close' ).addEventListener( 'click', function () {
			modal.classList.remove( 'is-open' );
			setTimeout( function () { modal.remove(); }, 150 );
		} );
	}

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}
} )();
