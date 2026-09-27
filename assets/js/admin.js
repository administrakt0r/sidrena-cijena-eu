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

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var message = form.getAttribute( 'data-sidrena-confirm' );

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
			return;
		}

		var submitBtns = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );
		for ( var i = 0; i < submitBtns.length; i++ ) {
			var btn = submitBtns[ i ];
			if ( btn.hasAttribute( 'data-sidrena-busy' ) || btn.classList.contains( 'sidrena-btn-busy' ) || form.classList.contains( 'sidrena-busy-form' ) ) {
				setTimeout( function ( b ) {
					b.setAttribute( 'disabled', 'disabled' );
					b.classList.add( 'is-busy' );
				}, 0, btn );
			}
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

	function bindBulkAnchorSelection() {
		var master = document.getElementById( 'sidrena-select-all' );
		var cbs = document.querySelectorAll( '.sidrena-row-cb' );

		if ( master ) {
			master.addEventListener( 'change', function () {
				for ( var i = 0; i < cbs.length; i++ ) {
					cbs[ i ].checked = master.checked;
				}
			} );
		}

		var btnAll = document.getElementById( 'sidrena-btn-select-all' );
		if ( btnAll ) {
			btnAll.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				for ( var i = 0; i < cbs.length; i++ ) {
					cbs[ i ].checked = true;
				}
				if ( master ) {
					master.checked = true;
				}
			} );
		}

		var btnNone = document.getElementById( 'sidrena-btn-deselect-all' );
		if ( btnNone ) {
			btnNone.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				for ( var i = 0; i < cbs.length; i++ ) {
					cbs[ i ].checked = false;
				}
				if ( master ) {
					master.checked = false;
				}
			} );
		}
	}

	function init() {
		focusFeedback();
		bindStylePreviews();
		bindBulkAnchorSelection();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
