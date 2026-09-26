<?php
/**
 * Variation anchor-price support for variable products.
 *
 * @package SidrenaCijena\WooCommerce\Display
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds anchor data to variation JSON and updates it on variation selection.
 */
class VariationDisplay {

	/**
	 * Anchor price service.
	 *
	 * @var AnchorPriceService
	 */
	protected $anchors;

	/**
	 * Constructor.
	 *
	 * @param AnchorPriceService $anchors Anchor service.
	 */
	public function __construct( AnchorPriceService $anchors ) {
		$this->anchors = $anchors;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! Settings::get( 'display_variations' ) ) {
			return;
		}

		add_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation' ), 10, 3 );
	}

	/**
	 * Add anchor data to the variation JSON.
	 *
	 * @param array                 $data      Variation data.
	 * @param \WC_Product_Variable  $parent    Parent product.
	 * @param \WC_Product_Variation $variation Variation.
	 * @return array<mixed>
	 */
	public function filter_available_variation( $data, $parent, $variation ) {
		unset( $parent );

		$item = Plugin::instance()->catalog()->get_variation( $variation->get_parent_id(), $variation->get_id() );

		if ( ! $item ) {
			return $data;
		}

		$anchor = $this->anchors->get_anchor( $item );

		if ( ! $anchor || null === $anchor['price'] ) {
			$data['sidrena_cijena_anchor']     = null;
			$data['sidrena_cijena_anchor_raw'] = null;

			return $data;
		}

		$price = PriceDisplay::display_price( (float) $anchor['price'], $item );

		$data['sidrena_cijena_anchor']                 = Currency::format( $price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) );
		$data['sidrena_cijena_anchor_raw']             = $price;
		$data['sidrena_cijena_reference_date']         = isset( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';
		$date_parts                                    = explode( '-', $data['sidrena_cijena_reference_date'] );
		$data['sidrena_cijena_reference_date_display'] = 3 === count( $date_parts )
			? sprintf( '%d. %d. %d.', (int) $date_parts[2], (int) $date_parts[1], (int) $date_parts[0] )
			: '';

		return $data;
	}
}
