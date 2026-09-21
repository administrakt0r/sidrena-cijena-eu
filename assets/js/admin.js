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
} )();
