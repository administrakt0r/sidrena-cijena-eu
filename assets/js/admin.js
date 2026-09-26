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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', focusFeedback );
	} else {
		focusFeedback();
	}
} )();
