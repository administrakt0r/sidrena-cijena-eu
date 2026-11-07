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
use SidrenaCijena\Support\Dates;
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

		if ( ! Settings::get( 'display_enabled' ) || ! Settings::get( 'display_product_pages' ) ) {
			return $data;
		}

		$item = Plugin::instance()->catalog()->get_variation( $variation->get_parent_id(), $variation->get_id() );

		if ( ! $item ) {
			return $data;
		}

		// WooCommerce replaces this HTML when a variation is chosen, including
		// when its price equals the parent range and core leaves price_html empty.
		$price_html = isset( $data['price_html'] ) ? (string) $data['price_html'] : '';
		if ( '' === $price_html ) {
			$price_html = $variation->get_price_html();
		}

		$price_display = new PriceDisplay( $this->anchors );

		if ( false === strpos( $price_html, 'sidrena-cijena' ) ) {
			$price_html .= $price_display->render_item( $item );
		}

		$data['price_html'] = $price_html;

		$sale = $price_display->active_sale_for_item( $item );
		if ( $sale && null !== $sale['lowest_30_day_price'] ) {
			$lowest_30                         = PriceDisplay::display_price( (float) $sale['lowest_30_day_price'], $item );
			$data['sidrena_cijena_30_day']     = Currency::format( $lowest_30, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) );
			$data['sidrena_cijena_30_day_raw'] = $lowest_30;
		} else {
			$data['sidrena_cijena_30_day']     = null;
			$data['sidrena_cijena_30_day_raw'] = null;
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
		$data['sidrena_cijena_reference_date_display'] = Dates::format_date_display( $data['sidrena_cijena_reference_date'] );

		return $data;
	}
}
