<?php
/**
 * Price-history capture for WooCommerce products.
 *
 * @package SidrenaCijena\WooCommerce
 */

namespace SidrenaCijena\WooCommerce;

use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Records regular and sale price changes of WooCommerce products.
 *
 * The standalone catalog does this in `StandalonePostType::capture_price_changes()`.
 * Without the WooCommerce counterpart no `regular` or `sale` rows ever exist for
 * WooCommerce products, so the 30-day lowest price can never be calculated
 * (FR-019) and every special sale produces a `missing_30_day_price` finding that
 * blocks price-list publication.
 */
class PriceHistoryRecorder {

	/**
	 * Register the capture hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_new_product', array( $this, 'capture' ) );
		add_action( 'woocommerce_update_product', array( $this, 'capture' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'capture' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'capture' ) );
	}

	/**
	 * Append history rows when the stored prices changed.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	public function capture( $product_id ) {
		if ( ! WooCommerceDetector::is_active() ) {
			return;
		}

		$product = wc_get_product( (int) $product_id );

		if ( ! $product ) {
			return;
		}

		$is_variation = $product->is_type( 'variation' );
		$parent_id    = $is_variation ? (int) $product->get_parent_id() : (int) $product->get_id();
		$variation_id = $is_variation ? (int) $product->get_id() : 0;

		$item = $variation_id
			? Plugin::instance()->catalog()->get_variation( $parent_id, $variation_id )
			: Plugin::instance()->catalog()->get_item( $parent_id );

		if ( ! $item ) {
			return;
		}

		$this->capture_price( $item, 'regular', $product->get_regular_price( 'edit' ) );
		$this->capture_price( $item, 'sale', $product->get_sale_price( 'edit' ) );
	}

	/**
	 * Append a history row when the price differs from the stored one.
	 *
	 * An empty sale price simply has no row to append; the previous sale rows
	 * keep their validity period so the 30-day window still sees them.
	 *
	 * @param \SidrenaCijena\Catalog\CatalogItem $item       Catalog item.
	 * @param string                             $price_type Price type (`regular`|`sale`).
	 * @param string                             $price      Raw WooCommerce price.
	 * @return void
	 */
	protected function capture_price( $item, $price_type, $price ) {
		$amount = Currency::to_storage( $price );

		if ( null === $amount ) {
			return;
		}

		$history = Plugin::instance()->history();
		$last    = $history->latest( (int) $item->id, (int) $item->variation_id, $price_type );

		if ( $last && (float) $last['price'] === (float) $amount ) {
			return;
		}

		$inserted = $history->insert(
			array(
				'product_id'    => (int) $item->id,
				'variation_id'  => (int) $item->variation_id,
				'item_type'     => $item->is_service() ? 'service' : 'product',
				'sku'           => $item->sku,
				'price_type'    => $price_type,
				'price'         => $amount,
				'currency'      => $item->currency,
				'tax_inclusive' => $item->tax_inclusive,
				'valid_from'    => Dates::now_site()->format( 'Y-m-d H:i:s' ),
				'source'        => 'woocommerce',
				'created_by'    => get_current_user_id(),
			)
		);

		if ( ! $inserted ) {
			return;
		}

		/**
		 * Fires when a catalog price was recorded from WooCommerce.
		 *
		 * Mirrors the standalone catalog signal so REST caches are flushed and
		 * service-price changes queue a regeneration for WooCommerce stores too.
		 *
		 * @param int         $product_id   Product ID.
		 * @param int         $variation_id Variation ID (0 for none).
		 * @param float|null  $previous     Previous stored price.
		 * @param float       $current      New stored price.
		 * @param string      $source       Price source.
		 */
		do_action( 'sidrena_cijena_price_changed', (int) $item->id, (int) $item->variation_id, $last ? (float) $last['price'] : null, (float) $amount, 'woocommerce' );
	}
}
