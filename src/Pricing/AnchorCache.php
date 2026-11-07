<?php
/**
 * Anchor price cache.
 *
 * Layer 1 is the object cache (per request), layer 2 a transient (across
 * requests). Both are invalidated whenever anchor data, prices or settings
 * change.
 *
 * @package Titanworks\SidrenaCijena\Pricing
 */

namespace Titanworks\SidrenaCijena\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Caches resolved anchor records.
 */
class AnchorCache {

	/**
	 * Cache group.
	 */
	const GROUP = 'twbce_cijena';

	/**
	 * Transient lifetime.
	 */
	const TTL = DAY_IN_SECONDS;

	/**
	 * Whether the last lookup was a cache hit.
	 *
	 * @var bool
	 */
	protected $hit = false;

	/**
	 * Whether the last lookup was a cache hit.
	 *
	 * @return bool
	 */
	public function is_hit() {
		return $this->hit;
	}

	/**
	 * Get a cached anchor record.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return array|null Anchor record, or null when the item has no anchor.
	 */
	public function get_anchor( $product_id, $variation_id = 0 ) {
		$this->hit = false;

		$key = $this->key( $product_id, $variation_id );

		$cached = wp_cache_get( $key, self::GROUP );

		if ( false === $cached && ! ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) ) {
			$cached = get_transient( $key );

			if ( false !== $cached ) {
				wp_cache_set( $key, $cached, self::GROUP, self::TTL );
			}
		}

		if ( false === $cached || ! is_array( $cached ) || ! array_key_exists( 'anchor', $cached ) ) {
			return null;
		}

		$this->hit = true;

		return $cached['anchor'];
	}

	/**
	 * Store an anchor record.
	 *
	 * @param int        $product_id   Product ID.
	 * @param int        $variation_id Variation ID.
	 * @param array|null $anchor       Anchor record or null.
	 * @return void
	 */
	public function set_anchor( $product_id, $variation_id, $anchor ) {
		$key   = $this->key( $product_id, $variation_id );
		$value = array( 'anchor' => $anchor );

		wp_cache_set( $key, $value, self::GROUP, self::TTL );

		if ( ! ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) ) {
			set_transient( $key, $value, self::TTL );
		}
	}

	/**
	 * Store multiple anchor records in batch.
	 *
	 * @param array<int,array{product_id:int,variation_id:int,anchor:array|null}> $entries Entries to store.
	 * @return void
	 */
	public function set_anchors_batch( array $entries ) {
		foreach ( $entries as $entry ) {
			$this->set_anchor( $entry['product_id'], $entry['variation_id'], $entry['anchor'] );
		}
	}

	/**
	 * Invalidate one item (and its parent when a variation changed).
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public function invalidate( $product_id, $variation_id = 0 ) {
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;

		$this->forget( $this->key( $product_id, $variation_id ) );

		if ( $variation_id ) {
			$this->forget( $this->key( $product_id, 0 ) );
		}
	}

	/**
	 * Invalidate every cached anchor.
	 *
	 * Uses a cache generation marker so no key enumeration is required. The
	 * generation is monotonic: two flushes inside the same second must still
	 * invalidate entries written after the first flush.
	 *
	 * Uses an explicit positive TTL so WordPress creates the bookkeeping
	 * row as non-autoloaded (autoload = 'no') in wp_options.
	 *
	 * @return void
	 */
	public function flush() {
		$current    = get_transient( 'twbce_cijena_cache_generation' );
		$generation = max( time(), (int) $current + 1 );

		$ttl = defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000;
		set_transient( 'twbce_cijena_cache_generation', $generation, $ttl );
		wp_cache_set( 'twbce_cijena_cache_generation', $generation, self::GROUP, self::TTL );
	}

	/**
	 * Cache key.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	protected function key( $product_id, $variation_id ) {
		$generation = get_transient( 'twbce_cijena_cache_generation' );

		if ( false === $generation ) {
			$generation = 0;
		}

		return sprintf( 'twbce_anchor_%d_%d_%d', (int) $product_id, (int) $variation_id, (int) $generation );
	}

	/**
	 * Remove a key from both layers.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	protected function forget( $key ) {
		wp_cache_delete( $key, self::GROUP );
		delete_transient( $key );
	}
}
