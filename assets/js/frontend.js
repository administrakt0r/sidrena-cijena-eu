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
		var isoDate = data && data.sidrena_cijena_reference_date ? data.sidrena_cijena_reference_date : '';

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

			if ( date ) {
				dateNodes[ j ].textContent = template ? template.replace( '%s', date ) : date;
				dateNodes[ j ].style.display = '';
			} else {
				dateNodes[ j ].textContent = '';
				dateNodes[ j ].style.display = 'none';
			}
		}

		var labelNodes = root.querySelectorAll( '[data-sidrena-cijena-label]' );
		for ( var k = 0; k < labelNodes.length; k++ ) {
			var labelWrapper = labelNodes[ k ].closest
				? labelNodes[ k ].closest( '[data-sidrena-cijena-label-template]' )
				: null;

			if ( labelWrapper ) {
				var labelTemplate = labelWrapper.getAttribute( 'data-sidrena-cijena-label-template' ) || '';
				if ( labelTemplate ) {
					var replaced = labelTemplate
						.replace( /\{datum\}|\{date\}/g, date )
						.replace( /\{datum_iso\}/g, isoDate );
					if ( ! date && ! isoDate ) {
						replaced = replaced.replace( /\s+/g, ' ' ).replace( /^[\s:()]+|[\s:()]+$/g, '' );
					}
					labelNodes[ k ].textContent = replaced;
				}
			}
		}

		var anchorBlocks = root.querySelectorAll( '.sidrena-cijena__anchor' );
		for ( var a = 0; a < anchorBlocks.length; a++ ) {
			anchorBlocks[ a ].style.display = display ? '' : 'none';
		}
	}

	function repositionTooltip( popup ) {
		if ( ! popup || ! popup.getBoundingClientRect ) {
			return;
		}

		var wrap = popup.closest ? popup.closest( '.sidrena-cijena__tooltip-wrap' ) : popup.parentNode;

		popup.classList.remove( 'sidrena-cijena__tooltip-popup--flipped-down' );
		if ( wrap && wrap.classList ) {
			wrap.classList.remove( 'has-flipped-down' );
		}
		popup.style.left = '';
		popup.style.removeProperty( '--sidrena-arrow-left' );

		var rect = popup.getBoundingClientRect();
		var viewportWidth = window.innerWidth || document.documentElement.clientWidth;

		if ( rect.top < 10 ) {
			popup.classList.add( 'sidrena-cijena__tooltip-popup--flipped-down' );
			if ( wrap && wrap.classList ) {
				wrap.classList.add( 'has-flipped-down' );
			}
			rect = popup.getBoundingClientRect();
		}

		if ( rect.left < 8 ) {
			var shiftLeft = 8 - rect.left;
			popup.style.left = 'calc(50% + ' + Math.round( shiftLeft ) + 'px)';
			popup.style.setProperty( '--sidrena-arrow-left', 'calc(50% - ' + Math.round( shiftLeft ) + 'px)' );
		} else if ( rect.right > viewportWidth - 8 ) {
			var shiftRight = rect.right - ( viewportWidth - 8 );
			popup.style.left = 'calc(50% - ' + Math.round( shiftRight ) + 'px)';
			popup.style.setProperty( '--sidrena-arrow-left', 'calc(50% + ' + Math.round( shiftRight ) + 'px)' );
		}
	}

	function closeAllTooltips() {
		var activePopups = document.querySelectorAll( '.sidrena-cijena__tooltip-popup.is-active' );
		for ( var k = 0; k < activePopups.length; k++ ) {
			activePopups[ k ].classList.remove( 'is-active' );
			activePopups[ k ].classList.remove( 'sidrena-cijena__tooltip-popup--flipped-down' );
			activePopups[ k ].style.left = '';
			activePopups[ k ].style.removeProperty( '--sidrena-arrow-left' );
			activePopups[ k ].setAttribute( 'aria-hidden', 'true' );
		}
		var activeWraps = document.querySelectorAll( '.sidrena-cijena__tooltip-wrap.has-flipped-down' );
		for ( var m = 0; m < activeWraps.length; m++ ) {
			activeWraps[ m ].classList.remove( 'has-flipped-down' );
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
			repositionTooltip( popup );
		}
	}

	function bindTooltipEvents() {
		document.addEventListener( 'mouseenter', function ( event ) {
			var trigger = event.target && event.target.closest
				? event.target.closest( '[data-sidrena-cijena-tooltip-trigger]' )
				: null;
			if ( trigger ) {
				var wrap = trigger.closest ? trigger.closest( '.sidrena-cijena__tooltip-wrap' ) : trigger.parentNode;
				var popup = wrap ? wrap.querySelector( '[data-sidrena-cijena-tooltip-popup]' ) : null;
				if ( popup ) {
					repositionTooltip( popup );
				}
			}
		}, true );

		document.addEventListener( 'focusin', function ( event ) {
			var trigger = event.target && event.target.closest
				? event.target.closest( '[data-sidrena-cijena-tooltip-trigger]' )
				: null;
			if ( trigger ) {
				var wrap = trigger.closest ? trigger.closest( '.sidrena-cijena__tooltip-wrap' ) : trigger.parentNode;
				var popup = wrap ? wrap.querySelector( '[data-sidrena-cijena-tooltip-popup]' ) : null;
				if ( popup ) {
					repositionTooltip( popup );
				}
			}
		} );

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
