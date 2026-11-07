<?php
/**
 * Contemporaneous first-offer evidence for newly created catalog entries.
 *
 * @package Titanworks\SidrenaCijena\Pricing
 */

namespace Titanworks\SidrenaCijena\Pricing;

use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Catalog\StandaloneCatalogProvider;
use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Compliance\ReferenceDateResolver;
use Titanworks\SidrenaCijena\Logging\Logger;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Dates;
use Titanworks\SidrenaCijena\WooCommerce\WooCommerceCatalogProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Observes new entries prospectively; never scans or backfills existing entries.
 */
final class FirstOfferRecorder {

	/** Eligibility is tied to the actual post ID, so copied metadata cannot qualify another item. */
	const ELIGIBLE = '_twbce_first_offer_eligible';

	/** Immutable observation, retained even if writing the history row fails. */
	const OBSERVATION = '_twbce_first_offer_observation';

	/**
	 * Register after plugin activation, before catalog writes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_insert_post', array( $this, 'observe_creation' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( $this, 'capture_standalone' ), 20, 2 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'capture_woocommerce' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'exclude_import' ) );
	}

	/**
	 * An import is not evidence of a product's first offer for sale.
	 *
	 * @param \WC_Product $product Imported product.
	 * @return \WC_Product
	 */
	public function exclude_import( $product ) {
		$product->update_meta_data( self::ELIGIBLE, '0' );
		return $product;
	}

	/**
	 * Mark only insertions actually witnessed with this recorder active.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post.
	 * @param bool     $update Whether this is an existing entry.
	 * @return void
	 */
	public function observe_creation( $post_id, $post, $update ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}
		if ( ! $update && in_array( $post->post_type, array( 'product', 'product_variation', StandalonePostType::POST_TYPE ), true ) ) {
			update_post_meta( $post_id, self::ELIGIBLE, (int) $post_id );
		}
	}

	/**
	 * Capture standalone items after their fields and terms have been saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function capture_standalone( $post_id, $post ) {
		if ( StandalonePostType::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$item = ( new StandaloneCatalogProvider() )->get_item( $post_id );
		if ( $item && $item->tax_inclusive ) {
			$this->capture( $item );
		}
	}

	/**
	 * Capture only a saved, published offer; never use a variable parent's range.
	 *
	 * @param \WC_Product $product Saved product.
	 * @return void
	 */
	public function capture_woocommerce( $product ) {
		// WooCommerce writes imported object metadata after wp_insert_post.
		// Remove insertion eligibility before this or any later save can use it.
		if ( '0' === (string) $product->get_meta( self::ELIGIBLE ) ) {
			delete_post_meta( $product->get_id(), self::ELIGIBLE );
			return;
		}
		if ( 'publish' !== $product->get_status() ) {
			return;
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					$this->capture_woocommerce( $child );
				}
			}
			return;
		}
		if ( $product->is_type( 'variation' ) && 'publish' !== get_post_status( $product->get_parent_id() ) ) {
			return;
		}
		if ( '' === $product->get_price( 'edit' ) ) {
			return;
		}
		$provider = new WooCommerceCatalogProvider();
		$item     = $product->is_type( 'variation' )
			? $provider->get_variation( $product->get_parent_id(), $product->get_id() )
			: $provider->get_item( $product->get_id() );
		if ( $item ) {
			$item->current_price = (float) wc_get_price_including_tax( $product );
			$item->tax_inclusive = true;
			$this->capture( $item );
		}
	}

	/**
	 * Freeze the witnessed offer before recording its anchor, once only.
	 *
	 * @param CatalogItem $item Published catalog offer.
	 * @return void
	 */
	private function capture( CatalogItem $item ) {
		$post_id = $item->variation_id ? $item->variation_id : $item->id;
		if ( (int) get_post_meta( $post_id, self::ELIGIBLE, true ) !== $post_id
			|| get_post_meta( $post_id, self::OBSERVATION, true )
			|| null === $item->current_price || $item->current_price < 0 ) {
			return;
		}
		$now      = Dates::now_site();
		$baseline = ReferenceDateResolver::date( $item );
		$evidence = array(
			'product_id'   => $item->id,
			'variation_id' => $item->variation_id,
			'observed_at'  => $now->format( DATE_ATOM ),
			'price'        => $item->current_price,
			'currency'     => $item->currency,
			'baseline'     => $baseline,
		);
		if ( ! add_post_meta( $post_id, self::OBSERVATION, $evidence, true ) ) {
			return;
		}
		// An offer before the baseline does not prove its price on that later date.
		if ( $now->format( 'Y-m-d' ) < $baseline || Plugin::instance()->anchors()->get_anchor( $item ) ) {
			return;
		}
		update_post_meta( $post_id, '_twbce_first_seen', $now->format( 'Y-m-d' ) );
		$result = Plugin::instance()->anchors()->record(
			$item,
			$item->current_price,
			array(
				'reference_date' => $now->format( 'Y-m-d' ),
				'valid_from'     => Dates::to_utc( $now ),
				'source'         => 'observed_first_offer',
				'tax_inclusive'  => true,
				'metadata'       => $evidence,
			)
		);
		if ( is_wp_error( $result ) || ! $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : 'The first-offer history row could not be written.';
			Logger::error( 'first_offer_record_failed', $message, $evidence );
		}
	}
}
