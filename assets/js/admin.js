/**
 * Sidrena cijena - admin helpers.
 */
( function () {
	'use strict';

	function confirmDestructive( event ) {
		var message = event.target.getAttribute( 'data-sidrena-confirm' );

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		while ( target && target !== document ) {
			if ( target.hasAttribute && target.hasAttribute( 'data-sidrena-confirm' ) ) {
				confirmDestructive( { target: target, preventDefault: event.preventDefault.bind( event ) } );
				return;
			}

			target = target.parentNode;
		}
	} );

	function focusFeedback() {
		var invalid = document.querySelector( '[aria-invalid="true"]' );

		if ( invalid && invalid.focus ) {
			invalid.focus();
			return;
		}

		var notice = document.querySelector( '.sidrena-cijena-notice[data-sidrena-notice-focus]' );

		if ( notice && notice.focus ) {
			notice.focus();
		}
	}

	function bindStylePreviews() {
		var size = document.getElementById( 'sidrena-display-size' );
		var value = document.getElementById( 'sidrena-display-size-value' );

		if ( ! size || ! value ) {
			return;
		}

		size.addEventListener( 'input', function () {
			value.textContent = size.value + '%';
			document.querySelectorAll( '.sidrena-style-preview' ).forEach( function ( preview ) {
				preview.style.setProperty( '--sidrena-size', Number( size.value ) / 100 );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			focusFeedback();
			bindStylePreviews();
		} );
	} else {
		focusFeedback();
		bindStylePreviews();
	}
} )();
