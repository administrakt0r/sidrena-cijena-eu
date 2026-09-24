<?php
/**
 * Daily price-list generation.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;

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
	public static function run_scheduled() {
		$results = Plugin::instance()->generation()->generate_all(
			array(
				'source' => 'scheduled',
				'force'  => true,
			)
		);

		Logger::info(
			'export_scheduled',
			'Daily price-list generation finished.',
			array( 'summary' => self::summarize( $results ) )
		);
	}

	/**
	 * Regenerate price lists after a service price change.
	 *
	 * @param array<mixed> $args Event arguments: `location_id`, `product_id`.
	 * @return void
	 */
	public static function run_service_regeneration( array $args = array() ) {
		$generation = Plugin::instance()->generation();

		if ( ! empty( $args['location_id'] ) ) {
			$results = array( (int) $args['location_id'] => $generation->generate_location( (int) $args['location_id'], array( 'source' => 'scheduled' ) ) );
		} else {
			$results = $generation->generate_all( array( 'source' => 'scheduled' ) );
		}

		Logger::info(
			'export_scheduled',
			'Queued service price change regenerated the price lists.',
			array(
				'summary' => self::summarize( $results ),
				'trigger' => 'service_change',
			)
		);
	}

	/**
	 * Summarize generation results for logging.
	 *
	 * @param array<mixed> $results Generation results.
	 * @return array<string,int>
	 */
	protected static function summarize( array $results ) {
		$summary = array(
			'published' => 0,
			'skipped'   => 0,
			'failed'    => 0,
		);

		foreach ( $results as $location ) {
			if ( is_wp_error( $location ) ) {
				++$summary['failed'];
				continue;
			}

			foreach ( (array) $location as $result ) {
				if ( is_wp_error( $result ) ) {
					++$summary['failed'];
				} elseif ( ! empty( $result['skipped'] ) ) {
					++$summary['skipped'];
				} else {
					++$summary['published'];
				}
			}
		}

		return $summary;
	}
}
