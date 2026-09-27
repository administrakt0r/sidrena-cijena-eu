/**
 * Sidrena cijena - variation anchor updates.
 *
 * Updates the anchor price shown on variable product pages when a variation
 * is selected. Uses WooCommerce's variation events when jQuery is present.
 */
( function () {
	'use strict';

	function update( data, root ) {
		var nodes = root.querySelectorAll( '[data-sidrena-cijena-anchor]' );
		var display = data && data.sidrena_cijena_anchor ? data.sidrena_cijena_anchor : '';
		var date = data && data.sidrena_cijena_reference_date_display ? data.sidrena_cijena_reference_date_display : '';

		if ( ! nodes.length ) {
			return;
		}

		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].textContent = display;
			if ( ! nodes[ i ].hasAttribute( 'aria-live' ) ) {
				nodes[ i ].setAttribute( 'aria-live', 'polite' );
			}
		}

		var dateNodes = root.querySelectorAll( '[data-sidrena-cijena-reference-date]' );
		for ( var j = 0; j < dateNodes.length; j++ ) {
			var template = '';
			var wrapper = dateNodes[ j ].closest
				? dateNodes[ j ].closest( '[data-sidrena-cijena-date-template]' )
				: null;

			if ( wrapper ) {
				template = wrapper.getAttribute( 'data-sidrena-cijena-date-template' ) || '';
			}

			dateNodes[ j ].textContent = date && template
				? template.replace( '%s', date )
				: date;
		}
	}

	function bind() {
		if ( window.jQuery ) {
			window.jQuery( document ).on( 'found_variation', function ( event, variation ) {
				update( variation, event.target.closest( '.product' ) || event.target );
			} );

			window.jQuery( document ).on( 'reset_data', function ( event ) {
				update( null, event.target.closest( '.product' ) || event.target );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
} )();
