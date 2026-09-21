<?php
/**
 * REST response cache.
 *
 * @package SidrenaCijena\Api
 */

namespace SidrenaCijena\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Caches public REST responses and invalidates them when price data changes.
 *
 * A generation marker is part of every cache key, so flushing is a single
 * option update and never leaves stale entries behind.
 */
class RestCache {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	const GROUP = 'sidrena_cijena_rest';

	/**
	 * Cache lifetime in seconds.
	 *
	 * @var int
	 */
	const TTL = 300;

	/**
	 * Option holding the cache generation marker.
	 *
	 * @var string
	 */
	const GENERATION_OPTION = 'sidrena_cijena_rest_generation';

	/**
	 * Current cache generation.
	 *
	 * @return int
	 */
	public function generation() {
		return (int) get_option( self::GENERATION_OPTION, 1 );
	}

	/**
	 * Cache key for a set of request arguments.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return string
	 */
	public function key( array $args ) {
		ksort( $args );

		return 'sidrena_rest_' . $this->generation() . '_' . md5( (string) wp_json_encode( $args ) );
	}

	/**
	 * Read a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$cached = wp_cache_get( $key, self::GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$transient = get_transient( $key );

		if ( false !== $transient ) {
			wp_cache_set( $key, $transient, self::GROUP, self::TTL );

			return $transient;
		}

		return null;
	}

	/**
	 * Store a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime in seconds.
	 * @return void
	 */
	public function set( $key, $value, $ttl = self::TTL ) {
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		set_transient( $key, $value, $ttl );
	}

	/**
	 * Flush every cached response.
	 *
	 * @return void
	 */
	public function flush() {
		update_option( self::GENERATION_OPTION, time(), false );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
		}
	}
}
