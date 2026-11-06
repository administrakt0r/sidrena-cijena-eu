( function () {
	if (
		! window.wc ||
		! window.wc.blocksCheckout ||
		typeof window.wc.blocksCheckout.registerCheckoutFilters !== 'function'
	) {
		return;
	}

	window.wc.blocksCheckout.registerCheckoutFilters( 'titanworks-sidrena-bazna-cijena', {
		cartItemPrice: function ( defaultValue, extensions ) {
			var extension = extensions && ( extensions[ 'titanworks-sidrena-bazna-cijena' ] || extensions[ 'sidrena-cijena-eu' ] );
			var reference = extension && extension.cartPriceSuffix;

			if ( typeof reference !== 'string' || reference === '' ) {
				return defaultValue;
			}

			return defaultValue + ' ' + reference;
		},
	} );
} )();
