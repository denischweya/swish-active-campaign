( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn    = document.getElementById( 'swish-ac-test-connection' );
		var result = document.getElementById( 'swish-ac-test-result' );
		if ( ! btn || ! result || typeof swishAcSettings === 'undefined' ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled    = true;
			result.style.color = '';
			result.textContent = swishAcSettings.i18n.testing;

			var body = new URLSearchParams();
			body.append( 'action', 'swish_ac_test_connection' );
			body.append( 'nonce', swishAcSettings.nonce );

			fetch( swishAcSettings.ajaxUrl, {
				method:      'POST',
				credentials: 'same-origin',
				headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:        body.toString()
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						result.style.color = '#46b450';
						result.textContent = res.data && res.data.message ? res.data.message : swishAcSettings.i18n.ok;
					} else {
						result.style.color = '#dc3232';
						var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Unknown error';
						result.textContent = swishAcSettings.i18n.fail + ' ' + msg;
					}
				} )
				.catch( function ( err ) {
					result.style.color = '#dc3232';
					result.textContent = swishAcSettings.i18n.fail + ' ' + ( err && err.message ? err.message : err );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	} );
} )();
