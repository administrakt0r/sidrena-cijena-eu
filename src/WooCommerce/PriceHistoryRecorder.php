<?php
/**
 * Price-history capture for WooCommerce products.
 *
 * @package Titanworks\SidrenaCijena\WooCommerce
 */

namespace Titanworks\SidrenaCijena\WooCommerce;

use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Currency;
use Titanworks\SidrenaCijena\Support\Dates;

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
	 * Sale transitions currently being applied by WooCommerce.
	 *
	 * @var array<int,string>
	 */
	protected $scheduled_sale_transitions = array();

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

		// WooCommerce 10.5+ applies scheduled sales through per-product
		// Action Scheduler callbacks. The batch hooks cover older WooCommerce
		// versions that still apply scheduled sales from the daily cron.
		add_action( 'wc_product_start_scheduled_sale', array( $this, 'begin_scheduled_sale_start' ), 1 );
		add_action( 'wc_product_start_scheduled_sale', array( $this, 'capture_scheduled_sale_transition' ), 20 );
		add_action( 'wc_product_start_scheduled_sale', array( $this, 'end_scheduled_sale_transition' ), PHP_INT_MAX );
		add_action( 'wc_product_end_scheduled_sale', array( $this, 'begin_scheduled_sale_end' ), 1 );
		add_action( 'wc_product_end_scheduled_sale', array( $this, 'capture_scheduled_sale_transition' ), 20 );
		add_action( 'wc_product_end_scheduled_sale', array( $this, 'end_scheduled_sale_transition' ), PHP_INT_MAX );
		add_action( 'wc_before_products_starting_sales', array( $this, 'begin_scheduled_sales' ), 1 );
		add_action( 'wc_after_products_starting_sales', array( $this, 'capture_scheduled_sales' ), 10 );
		add_action( 'wc_after_products_starting_sales', array( $this, 'end_scheduled_sales' ), PHP_INT_MAX );
		add_action( 'wc_before_products_ending_sales', array( $this, 'begin_scheduled_sales_end' ), 1 );
		add_action( 'wc_after_products_ending_sales', array( $this, 'capture_scheduled_sales' ), 10 );
		add_action( 'wc_after_products_ending_sales', array( $this, 'end_scheduled_sales' ), PHP_INT_MAX );
	}

	/**
	 * Capture after WooCommerce has persisted the scheduled active price.
	 *
	 * WooCommerce updates `_price` just after the product CRUD save that emits
	 * `woocommerce_update_product`, so the ordinary capture hook can run too
	 * early for scheduled transitions.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function capture_scheduled_sale_transition( $product_id ) {
		$this->capture( $product_id );
	}

	/**
	 * Capture products after WooCommerce has applied a legacy scheduled-sale batch.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return void
	 */
	public function capture_scheduled_sales( $product_ids ) {
		foreach ( (array) $product_ids as $product_id ) {
			$this->capture( $product_id );
		}
	}

	/**
	 * Mark a per-product scheduled sale start while WooCommerce saves the new state.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function begin_scheduled_sale_start( $product_id ) {
		$this->scheduled_sale_transitions[ (int) $product_id ] = 'start';
	}

	/**
	 * Mark a per-product scheduled sale end while WooCommerce saves the new state.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function begin_scheduled_sale_end( $product_id ) {
		$this->scheduled_sale_transitions[ (int) $product_id ] = 'end';
	}

	/**
	 * Mark the products in a legacy scheduled-sale batch as starting.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return void
	 */
	public function begin_scheduled_sales( $product_ids ) {
		$this->mark_scheduled_sales( $product_ids, 'start' );
	}

	/**
	 * Mark the products in a legacy scheduled-sale batch as ending.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return void
	 */
	public function begin_scheduled_sales_end( $product_ids ) {
		$this->mark_scheduled_sales( $product_ids, 'end' );
	}

	/**
	 * Clear per-product or batch transition context after WooCommerce finishes.
	 *
	 * @param int|int[] $product_ids Product ID or IDs.
	 * @return void
	 */
	public function end_scheduled_sale_transition( $product_ids ) {
		$this->end_scheduled_sales( $product_ids );
	}

	/**
	 * Clear the products in a legacy scheduled-sale batch.
	 *
	 * @param int|int[] $product_ids Product ID or IDs.
	 * @return void
	 */
	public function end_scheduled_sales( $product_ids ) {
		foreach ( (array) $product_ids as $product_id ) {
			unset( $this->scheduled_sale_transitions[ (int) $product_id ] );
		}
	}

	/**
	 * Set transition context for products in a scheduled-sale batch.
	 *
	 * @param int[]  $product_ids Product IDs.
	 * @param string $transition Transition (`start`|`end`).
	 * @return void
	 */
	protected function mark_scheduled_sales( $product_ids, $transition ) {
		foreach ( (array) $product_ids as $product_id ) {
			$this->scheduled_sale_transitions[ (int) $product_id ] = $transition;
		}
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

		$sale_price    = $product->get_sale_price( 'edit' );
		$sale_amount   = Currency::to_storage( $sale_price );
		$current_price = Currency::to_storage( $product->get_price( 'edit' ) );
		$sale_active   = $product->is_on_sale( 'edit' )
			&& null !== $sale_amount
			&& $current_price === $sale_amount;

		if ( $sale_active ) {
			$this->capture_price( $item, 'sale', $sale_price, $this->transition_valid_from( $product, 'start' ) );
		} elseif ( 'start' !== ( $this->scheduled_sale_transitions[ (int) $product->get_id() ] ?? null ) ) {
			// The CRUD save inside a scheduled start runs before WooCommerce
			// persists the new active `_price`. Defer closing the previous sale
			// until the post-transition capture sees the applied sale price.
			$this->capture_price( $item, 'sale', null, $this->transition_valid_from( $product, 'end' ) );
		}
	}

	/**
	 * Get the site-local effective boundary for a scheduled sale transition.
	 *
	 * WooCommerce stores scheduled values as UTC timestamps. An end date is
	 * inclusive, so its first inactive second is one second after that timestamp.
	 * Manual edits use the time at which WooCommerce saved the new state.
	 *
	 * @param object $product Product object.
	 * @param string $expected Transition type expected (`start`|`end`).
	 * @return string
	 */
	protected function transition_valid_from( $product, $expected ) {
		$now        = Dates::now_site();
		$product_id = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;

		if ( empty( $this->scheduled_sale_transitions[ $product_id ] ) || $expected !== $this->scheduled_sale_transitions[ $product_id ] ) {
			return $now->format( 'Y-m-d H:i:s' );
		}

		$date_method = 'start' === $expected ? 'get_date_on_sale_from' : 'get_date_on_sale_to';
		$date        = method_exists( $product, $date_method ) ? $product->{$date_method}( 'edit' ) : null;

		if ( ! is_object( $date ) || ! method_exists( $date, 'getTimestamp' ) ) {
			return $now->format( 'Y-m-d H:i:s' );
		}

		$timestamp = (int) $date->getTimestamp();

		if ( 'end' === $expected ) {
			++$timestamp;
		}

		return ( new \DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( Dates::timezone() )
			->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Append a history row when the price differs from the stored one.
	 *
	 * A null amount appends a closure observation when a previous open price
	 * exists. The ledger stays append-only, and the calculator uses the marker
	 * as the next same-type observation that closes the prior interval.
	 *
	 * @param \Titanworks\SidrenaCijena\Catalog\CatalogItem $item       Catalog item.
	 * @param string                                        $price_type Price type (`regular`|`sale`).
	 * @param string|null                                   $price      Raw WooCommerce price, or null when no price is applied.
	 * @param string|null                                   $valid_from Effective site-local boundary.
	 * @return void
	 */
	protected function capture_price( $item, $price_type, $price, $valid_from = null ) {
		$amount  = Currency::to_storage( $price );
		$history = Plugin::instance()->history();
		$last    = $history->latest( (int) $item->id, (int) $item->variation_id, $price_type );

		if ( null === $amount ) {
			if ( ! $last || null === $last['price'] || ! empty( $last['valid_to'] ) ) {
				return;
			}
		} elseif ( $last && null !== $last['price'] && (float) $last['price'] === (float) $amount ) {
			return;
		}

		$previous = $last && null !== $last['price'] ? (float) $last['price'] : null;

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
				'valid_from'    => $valid_from ? $valid_from : Dates::now_site()->format( 'Y-m-d H:i:s' ),
				'source'        => 'woocommerce',
				'created_by'    => get_current_user_id(),
				'metadata'      => null === $amount ? array( 'event' => 'price_interval_closed' ) : null,
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
		 * @param float|null  $current      New stored price, or null when its interval closed.
		 * @param string      $source       Price source.
		 */
		do_action( 'twbce_cijena_price_changed', (int) $item->id, (int) $item->variation_id, $previous, null === $amount ? null : (float) $amount, 'woocommerce' );
	}
}
