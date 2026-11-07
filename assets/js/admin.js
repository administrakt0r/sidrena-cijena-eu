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

		if ( size && value ) {
			size.addEventListener( 'input', function () {
				value.textContent = size.value + '%';
				document.querySelectorAll( '.sidrena-style-preview' ).forEach( function ( preview ) {
					preview.style.setProperty( '--sidrena-size', Number( size.value ) / 100 );
				} );
			} );
		}

		var cardSize = document.getElementById( 'sidrena-card-display-size' );
		var cardValue = document.getElementById( 'sidrena-card-display-size-value' );

		if ( cardSize && cardValue ) {
			cardSize.addEventListener( 'input', function () {
				cardValue.textContent = cardSize.value + '%';
				document.querySelectorAll( '.sidrena-card-style-preview' ).forEach( function ( preview ) {
					preview.style.setProperty( '--sidrena-size', Number( cardSize.value ) / 100 );
				} );
			} );
		}
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

	function bindTimeInputs() {
		var inputs = document.querySelectorAll( '.sidrena-time-input' );
		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].addEventListener( 'blur', function ( event ) {
				var val = event.target.value.trim();
				// Format 8:00 -> 08:00
				var match1 = val.match( /^([0-9]):([0-5][0-9])$/ );
				if ( match1 ) {
					event.target.value = '0' + match1[ 1 ] + ':' + match1[ 2 ];
					return;
				}
				// Format 800 -> 08:00 or 1430 -> 14:30
				var match2 = val.match( /^([0-1]?[0-9]|2[0-3])([0-5][0-9])$/ );
				if ( match2 ) {
					var h = match2[ 1 ];
					if ( 1 === h.length ) {
						h = '0' + h;
					}
					event.target.value = h + ':' + match2[ 2 ];
					return;
				}
				// Convert AM/PM if pasted
				var match3 = val.match( /^([0-1]?[0-9]):([0-5][0-9])\s*(AM|PM)$/i );
				if ( match3 ) {
					var hour = parseInt( match3[ 1 ], 10 );
					var min = match3[ 2 ];
					var ampm = match3[ 3 ].toUpperCase();
					if ( 'PM' === ampm && hour < 12 ) {
						hour += 12;
					} else if ( 'AM' === ampm && 12 === hour ) {
						hour = 0;
					}
					event.target.value = ( hour < 10 ? '0' : '' ) + hour + ':' + min;
				}
			} );
		}
	}

	function bindEvidenceForms() {
		var forms = document.querySelectorAll( '.sidrena-cijena-anchor-form, .sidrena-cijena-sale-form' );
		for ( var i = 0; i < forms.length; i++ ) {
			forms[ i ].addEventListener( 'keydown', function ( event ) {
				if ( 13 === event.keyCode && 'INPUT' === event.target.tagName ) {
					var btn = this.querySelector( 'button[type="submit"]' );
					if ( btn ) {
						event.preventDefault();
						btn.click();
					}
				}
			} );
		}
	}

	function bindLabelPresets() {
		var input = document.getElementById( 'anchor_label' ) || document.getElementById( 'sidrena-anchor-label-input' );
		if ( ! input ) {
			return;
		}

		var previewFood = document.getElementById( 'sidrena-preview-food' );
		var previewGeneral = document.getElementById( 'sidrena-preview-general' );
		var presetBtns = document.querySelectorAll( '.sidrena-preset-btn' );
		var tokenBtn = document.querySelector( '.sidrena-token-btn' );

		function updatePreview() {
			var val = input.value || input.getAttribute( 'placeholder' ) || 'Sidrena cijena';
			if ( previewFood ) {
				previewFood.textContent = val.replace( /\{datum\}|\{date\}/g, '02.05.2025.' ).replace( /\{datum_iso\}/g, '2025-05-02' );
			}
			if ( previewGeneral ) {
				previewGeneral.textContent = val.replace( /\{datum\}|\{date\}/g, '10.09.2026.' ).replace( /\{datum_iso\}/g, '2026-09-10' );
			}

			for ( var i = 0; i < presetBtns.length; i++ ) {
				if ( presetBtns[ i ].getAttribute( 'data-preset' ) === input.value ) {
					presetBtns[ i ].classList.add( 'is-active' );
				} else {
					presetBtns[ i ].classList.remove( 'is-active' );
				}
			}
		}

		input.addEventListener( 'input', updatePreview );
		updatePreview();

		for ( var j = 0; j < presetBtns.length; j++ ) {
			presetBtns[ j ].addEventListener( 'click', function () {
				input.value = this.getAttribute( 'data-preset' );
				updatePreview();
				input.focus();
			} );
		}

		if ( tokenBtn ) {
			tokenBtn.addEventListener( 'click', function () {
				var token = this.getAttribute( 'data-token' ) || '{datum}';
				if ( input.value.indexOf( token ) === -1 ) {
					input.value = input.value ? ( input.value + ' ' + token ) : token;
					updatePreview();
				}
				input.focus();
			} );
		}
	}

	function bindDatepickers() {
		var inputs = document.querySelectorAll( '.sidrena-datepicker' );
		if ( ! inputs.length ) {
			return;
		}

		for ( var i = 0; i < inputs.length; i++ ) {
			var input = inputs[ i ];
			var val = input.value ? input.value.trim() : '';

			// If input contains raw ISO YYYY-MM-DD, convert to European DD.MM.YYYY.
			var iso = val.match( /^(\d{4})-(\d{1,2})-(\d{1,2})$/ );
			if ( iso ) {
				var y = iso[ 1 ];
				var m = iso[ 2 ].length === 1 ? '0' + iso[ 2 ] : iso[ 2 ];
				var d = iso[ 3 ].length === 1 ? '0' + iso[ 3 ] : iso[ 3 ];
				input.value = d + '.' + m + '.' + y + '.';
			}

			// Format on blur if manually entered
			if ( ! input.hasAttribute( 'data-sidrena-datepicker-bound' ) ) {
				input.setAttribute( 'data-sidrena-datepicker-bound', '1' );
				input.addEventListener( 'blur', function ( event ) {
					var v = event.target.value ? event.target.value.trim() : '';
					if ( ! v ) {
						return;
					}
					var clean = v.replace( /\.$/, '' );
					var eu = clean.match( /^(\d{1,2})[./-]\s*(\d{1,2})[./-]\s*(\d{4})$/ );
					if ( eu ) {
						var ed = eu[ 1 ].length === 1 ? '0' + eu[ 1 ] : eu[ 1 ];
						var em = eu[ 2 ].length === 1 ? '0' + eu[ 2 ] : eu[ 2 ];
						var ey = eu[ 3 ];
						event.target.value = ed + '.' + em + '.' + ey + '.';
						return;
					}
					var isov = clean.match( /^(\d{4})-(\d{1,2})-(\d{1,2})$/ );
					if ( isov ) {
						var iy = isov[ 1 ];
						var im = isov[ 2 ].length === 1 ? '0' + isov[ 2 ] : isov[ 2 ];
						var id = isov[ 3 ].length === 1 ? '0' + isov[ 3 ] : isov[ 3 ];
						event.target.value = id + '.' + im + '.' + iy + '.';
					}
				} );
			}
		}

		if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.datepicker ) {
			var $ = window.jQuery;
			var i18n = window.sidrenaDatepickerI18n || {
				closeText: 'Zatvori',
				prevText: '&#x3C;Prethodno',
				nextText: 'Sljedeće&#x3E;',
				currentText: 'Danas',
				monthNames: [ 'Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac' ],
				monthNamesShort: [ 'Sij', 'Velj', 'Ožu', 'Tra', 'Svi', 'Lip', 'Srp', 'Kol', 'Ruj', 'Lis', 'Stu', 'Pro' ],
				dayNames: [ 'Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota' ],
				dayNamesShort: [ 'Ned', 'Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub' ],
				dayNamesMin: [ 'Ne', 'Po', 'Ut', 'Sr', 'Če', 'Pe', 'Su' ],
				dateFormat: 'dd.mm.yy.',
				firstDay: 1
			};

			var opts = Object.assign ? Object.assign( {}, i18n, {
				dateFormat: 'dd.mm.yy.',
				firstDay: 1,
				changeMonth: true,
				changeYear: true
			} ) : i18n;

			$( '.sidrena-datepicker' ).each( function () {
				var $el = $( this );
				if ( ! $el.hasClass( 'hasDatepicker' ) ) {
					$el.datepicker( opts );
				}
			} );
		}
	}

	function init() {
		focusFeedback();
		bindStylePreviews();
		bindBulkAnchorSelection();
		bindTimeInputs();
		bindEvidenceForms();
		bindLabelPresets();
		bindDatepickers();

		if ( window.jQuery ) {
			window.jQuery( document ).on( 'woocommerce_variations_loaded', bindDatepickers );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
