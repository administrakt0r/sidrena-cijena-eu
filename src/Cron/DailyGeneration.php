<?php
/**
 * Daily price-list generation.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the scheduled price-list generation.
 */
class DailyGeneration {

	/**
	 * Last generation result option.
	 */
	const RESULT_OPTION = 'sidrena_cijena_last_generation';

	/**
	 * Last generation failure option.
	 */
	const FAILURE_OPTION = 'sidrena_cijena_last_generation_failure';

	/**
	 * Dismiss failure option.
	 */
	const DISMISS_OPTION = 'sidrena_cijena_dismiss_gen_failure';

	/**
	 * Generate all due price lists.
	 *
	 * @return void
	 */
	public static function run_scheduled() {
		self::execute_generation( 'scheduled' );
		Scheduler::reschedule_daily();
	}

	/**
	 * Catch-up generation for missed runs.
	 *
	 * @return array<mixed>
	 */
	public static function run_catchup() {
		return self::execute_generation( 'catchup' );
	}

	/**
	 * Execute generation and record outcome.
	 *
	 * @param string $source Source ('scheduled', 'catchup', etc.).
	 * @return array<mixed>
	 */
	public static function execute_generation( $source = 'scheduled' ) {
		$results = Plugin::instance()->generation()->generate_all(
			array(
				'source' => $source,
				'force'  => true,
			)
		);

		$summary = self::summarize( $results );
		$errors  = self::extract_errors( $results );

		$record = array(
			'timestamp' => time(),
			'date'      => Dates::today(),
			'source'    => $source,
			'summary'   => $summary,
			'errors'    => $errors,
		);

		update_option( self::RESULT_OPTION, $record, false );

		if ( $summary['failed'] > 0 ) {
			update_option( self::FAILURE_OPTION, $record, false );
			delete_option( self::DISMISS_OPTION );

			Logger::error(
				'export_' . $source,
				'Daily price-list generation failed.',
				array(
					'summary' => $summary,
					'errors'  => $errors,
					'source'  => $source,
				)
			);
		} else {
			delete_option( self::FAILURE_OPTION );

			Logger::info(
				'export_' . $source,
				'scheduled' === $source ? 'Daily price-list generation finished.' : 'Catch-up price-list generation finished.',
				array(
					'summary' => $summary,
					'source'  => $source,
				)
			);
		}

		return $results;
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
	public static function summarize( array $results ) {
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

	/**
	 * Extract error messages from generation results.
	 *
	 * @param array<mixed> $results Generation results.
	 * @return string[]
	 */
	public static function extract_errors( array $results ) {
		$errors = array();

		foreach ( $results as $location_id => $location_results ) {
			if ( is_wp_error( $location_results ) ) {
				$errors[] = sprintf( 'Location #%d: %s', (int) $location_id, $location_results->get_error_message() );
				continue;
			}

			foreach ( (array) $location_results as $format => $result ) {
				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'Location #%d (%s): %s', (int) $location_id, strtoupper( (string) $format ), $result->get_error_message() );
				}
			}
		}

		return $errors;
	}
}
