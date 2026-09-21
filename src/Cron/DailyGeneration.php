<?php
/**
 * Daily price-list generation.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the scheduled price-list generation.
 */
class DailyGeneration {

	/**
	 * Generate all due price lists.
	 *
	 * @return void
	 */
	public static function run_scheduled() {}

	/**
	 * Regenerate price lists after a service price change.
	 *
	 * @param array<mixed> $args Event arguments.
	 * @return void
	 */
	public static function run_service_regeneration( array $args = array() ) {
		unset( $args );
	}
}
