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
 */
class RestCache {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	const GROUP = 'sidrena_cijena_rest';

	/**
	 * Flush the REST cache.
	 *
	 * @return void
	 */
	public function flush() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
		}
	}
}
