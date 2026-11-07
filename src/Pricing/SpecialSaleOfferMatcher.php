<?php
/**
 * Match a WooCommerce sale classification to its captured price interval.
 *
 * @package SidrenaCijena\Pricing
 */

namespace SidrenaCijena\Pricing;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps an older open-ended classification from being reused for a later offer.
 */
final class SpecialSaleOfferMatcher {

	/**
	 * Whether a classification describes the currently open WooCommerce offer.
	 * Standalone items retain their explicit date-based sale association.
	 *
	 * @param CatalogItem              $item   Catalog item.
	 * @param array<string,mixed>      $sale Sale classification.
	 * @param array<string,mixed>|null $period First row in the open sale interval.
	 * @return bool
	 */
	public static function matches( CatalogItem $item, array $sale, $period = null ) {
		if ( 'woocommerce' !== $item->source ) {
			return true;
		}

		// When no price-history interval is captured yet (e.g. newly created sale,
		// unrecorded history, or manual meta-box classification), the active sale applies.
		if ( ! is_array( $period ) || empty( $period['valid_from'] ) ) {
			return true;
		}

		if ( empty( $sale['start_date'] ) ) {
			return false;
		}

		$sale_start   = substr( (string) $sale['start_date'], 0, 10 );
		$period_start = substr( (string) $period['valid_from'], 0, 10 );

		// Date-only sale records cannot identify which of two same-day offers
		// they describe if there was an intervening closure.
		if ( ! empty( $period['prior_closure_at'] ) && substr( (string) $period['prior_closure_at'], 0, 10 ) === $period_start ) {
			return false;
		}

		$sale_price  = isset( $sale['promo_price'] ) && '' !== $sale['promo_price']
			? Currency::to_storage( $sale['promo_price'] )
			: null;
		$first_price = isset( $period['price'] ) && '' !== $period['price']
			? Currency::to_storage( $period['price'] )
			: null;

		// When price history captures the offer interval, verify that the start date
		// matches so the 30-day window calculated from the classification is legally aligned.
		if ( $sale_start !== $period_start ) {
			return false;
		}

		if ( null !== $sale_price && null !== $first_price && $sale_price !== $first_price ) {
			return false;
		}

		return true;
	}
}
