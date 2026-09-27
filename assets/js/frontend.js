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

	function closeAllTooltips() {
		var activePopups = document.querySelectorAll( '.sidrena-cijena__tooltip-popup.is-active' );
		for ( var k = 0; k < activePopups.length; k++ ) {
			activePopups[ k ].classList.remove( 'is-active' );
			activePopups[ k ].setAttribute( 'aria-hidden', 'true' );
		}
		var activeTriggers = document.querySelectorAll( '[data-sidrena-cijena-tooltip-trigger][aria-expanded="true"]' );
		for ( var l = 0; l < activeTriggers.length; l++ ) {
			activeTriggers[ l ].setAttribute( 'aria-expanded', 'false' );
		}
	}

	function toggleTooltip( trigger, event ) {
		if ( event ) {
			event.preventDefault();
			event.stopPropagation();
		}

		var wrap = trigger.closest ? trigger.closest( '.sidrena-cijena__tooltip-wrap' ) : trigger.parentNode;
		if ( ! wrap ) {
			return;
		}

		var popup = wrap.querySelector( '[data-sidrena-cijena-tooltip-popup]' );
		if ( ! popup ) {
			return;
		}

		var isOpen = popup.classList.contains( 'is-active' );
		closeAllTooltips();

		if ( ! isOpen ) {
			popup.classList.add( 'is-active' );
			popup.setAttribute( 'aria-hidden', 'false' );
			trigger.setAttribute( 'aria-expanded', 'true' );
		}
	}

	function bindTooltipEvents() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target && event.target.closest
				? event.target.closest( '[data-sidrena-cijena-tooltip-trigger]' )
				: null;

			if ( trigger ) {
				toggleTooltip( trigger, event );
			} else if ( ! ( event.target && event.target.closest && event.target.closest( '.sidrena-cijena__tooltip-popup' ) ) ) {
				closeAllTooltips();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				closeAllTooltips();
			} else if ( ( 'Enter' === event.key || ' ' === event.key ) && event.target && event.target.hasAttribute && event.target.hasAttribute( 'data-sidrena-cijena-tooltip-trigger' ) ) {
				toggleTooltip( event.target, event );
			}
		} );
	}

	function bind() {
		bindTooltipEvents();

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
