<?php
/**
 * `wp sidrena-cijena cleanup` command.
 *
 * @package Titanworks\SidrenaCijena\Cli
 */

namespace Titanworks\SidrenaCijena\Cli;

use Titanworks\SidrenaCijena\Cron\CleanupJob;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Expires retained publications whose retention has passed and prunes logs.
 */
final class CleanupCommand {

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
	 * Run or preview the retention cleanup.
	 *
	 * Exit codes: 0 success, 2 operational failure.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments (--dry-run).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		unset( $args );

		try {
			$candidates = Plugin::instance()->publications()->expired_candidates( Dates::now_utc() );

			if ( isset( $assoc_args['dry-run'] ) ) {
				Cli::out( sprintf( 'Dry run: %d publication(s) would expire now.', count( $candidates ) ) );

				return 0;
			}

			CleanupJob::run();

			Cli::out( sprintf( 'Cleanup finished: %d publication(s) expired.', count( $candidates ) ), 'success' );

			return 0;
		} catch ( \Throwable $exception ) {
			Cli::out( 'Cleanup failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}
}
