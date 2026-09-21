/**
 * Sidrena cijena - variation anchor updates.
 *
 * Updates the anchor price shown on variable product pages when a variation
 * is selected. Uses WooCommerce's variation events when jQuery is present.
 */
( function () {
	'use strict';

	function update( data ) {
		var nodes = document.querySelectorAll( '[data-sidrena-cijena-anchor]' );
		var payload = window.sidrenaCijenaVariations;

		if ( ! nodes.length || ! payload || ! payload.variations ) {
			return;
		}

		var entry = data && data.variation_id ? payload.variations[ data.variation_id ] : null;

		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].textContent = entry ? entry.display : '';
		}
	}

	function bind() {
		if ( window.jQuery ) {
			window.jQuery( document ).on( 'found_variation', function ( event, variation ) {
				update( variation );
			} );

			window.jQuery( document ).on( 'reset_data', function () {
				update( null );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
} )();
