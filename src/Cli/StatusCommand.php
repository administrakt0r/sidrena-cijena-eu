<?php
/**
 * `wp sidrena-cijena status` command.
 *
 * @package Titanworks\SidrenaCijena\Cli
 */

namespace Titanworks\SidrenaCijena\Cli;

use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Cron\Scheduler;
use Titanworks\SidrenaCijena\Database\Schema;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Reports schema version, legal rules, locations and scheduler health.
 */
final class StatusCommand {

	/**
	 * WP-CLI entry point.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public static function run( $args, $assoc_args ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exit code, not output.
		exit( self::execute( (array) $args, (array) $assoc_args ) );
	}

	/**
	 * Build the status report.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments.
	 * @return int Exit code (always 0 unless the report fails).
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		unset( $args );

		try {
			$plugin    = Plugin::instance();
			$rule      = RuleConfig::all();
			$next      = Scheduler::next_run();
			$locations = array();

			foreach ( $plugin->locations()->all( true ) as $location ) {
				$last = $plugin->publications()->last_successful( (int) $location['id'] );

				$locations[] = array(
					'id'             => (int) $location['id'],
					'name'           => (string) $location['name'],
					'identifier'     => (string) $location['identifier'],
					'active'         => (bool) $location['active'],
					'last_published' => $last && ! empty( $last['published_at'] ) ? Dates::from_utc( (string) $last['published_at'] )->format( 'c' ) : null,
					'last_filename'  => $last ? (string) $last['filename'] : null,
				);
			}

			$data = array(
				'schema_version' => Schema::DB_VERSION,
				'rule_version'   => isset( $rule['rule_version'] ) ? (string) $rule['rule_version'] : '',
				'general_date'   => isset( $rule['general_reference_date'] ) ? (string) $rule['general_reference_date'] : '',
				'legacy_date'    => isset( $rule['legacy_reference_date'] ) ? (string) $rule['legacy_reference_date'] : '',
				'locations'      => $locations,
				'cron'           => array(
					'healthy'         => Scheduler::is_healthy(),
					'next_run'        => $next ? Dates::from_utc( gmdate( 'Y-m-d H:i:s', (int) $next ) )->format( 'c' ) : null,
					'last_generation' => get_option( \Titanworks\SidrenaCijena\Cron\DailyGeneration::RESULT_OPTION, null ),
					'missed_today'    => count( Scheduler::missed_locations_today() ),
				),
			);

			$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

			if ( 'json' === $format ) {
				Cli::out( Cli::as_json( $data ), 'line' );
			} else {
				$last_gen = $data['cron']['last_generation'];
				$missed   = Scheduler::missed_locations_today();

				Cli::out( sprintf( 'Schema: %s | Rules: %s', $data['schema_version'], $data['rule_version'] ) );
				Cli::out( sprintf( 'Reference dates: general %s, legacy %s', $data['general_date'], $data['legacy_date'] ) );
				Cli::out( sprintf( 'Scheduler: %s%s', $data['cron']['healthy'] ? 'healthy' : 'NOT SCHEDULED OR OVERDUE', $data['cron']['next_run'] ? ', next run ' . $data['cron']['next_run'] : '' ) );

				if ( $last_gen && is_array( $last_gen ) ) {
					$gen_date = ! empty( $last_gen['timestamp'] ) ? Dates::from_utc( gmdate( 'Y-m-d H:i:s', (int) $last_gen['timestamp'] ) )->format( 'c' ) : 'unknown';
					$summary  = isset( $last_gen['summary'] ) && is_array( $last_gen['summary'] ) ? $last_gen['summary'] : array();
					$pub_cnt  = isset( $summary['published'] ) ? (int) $summary['published'] : 0;
					$fail_cnt = isset( $summary['failed'] ) ? (int) $summary['failed'] : 0;
					Cli::out( sprintf( 'Last generation: %s (%s, %d published, %d failed)', $gen_date, isset( $last_gen['source'] ) ? $last_gen['source'] : 'scheduled', $pub_cnt, $fail_cnt ) );
				}

				if ( ! empty( $missed ) ) {
					Cli::out( sprintf( 'Missed runs today: %d active location(s) missing today\'s price list', count( $missed ) ), 'warning' );
				}

				foreach ( $locations as $location ) {
					Cli::out( sprintf( 'Location #%d %s (%s): last published %s', $location['id'], $location['name'], $location['identifier'], $location['last_published'] ? $location['last_published'] : 'never' ) );
				}
			}

			return 0;
		} catch ( \Throwable $exception ) {
			Cli::out( 'Status failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}
}
