<?php
/**
 * Retention cleanup.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

use SidrenaCijena\Database\LogRepository;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Expires and removes price-list files past their retention window.
 *
 * Archive rows are never deleted; only files are removed after `expires_at`.
 */
class CleanupJob {

	/**
	 * Run the cleanup job.
	 *
	 * @return void
	 */
	public static function run() {
		$publications = Plugin::instance()->publications();
		$candidates   = $publications->expired_candidates( Dates::now_utc() );

		$deleted = 0;

		foreach ( $candidates as $row ) {
			$path = isset( $row['path'] ) ? (string) $row['path'] : '';

			if ( '' !== $path && file_exists( $path ) ) {
				$deleted_file = wp_delete_file( $path );

				// WordPress < 6.7 returns nothing here; only a definitive
				// failure keeps the row active so the next run retries the
				// file instead of losing it forever.
				if ( false === $deleted_file ) {
					continue;
				}

				++$deleted;
			}

			$publications->mark_expired( (int) $row['id'] );
		}

		$log_days = max( 1, (int) Settings::get( 'log_retention_days', 180 ) );
		$pruned   = ( new LogRepository() )->prune( $log_days );

		Logger::info(
			'cleanup',
			'Retention cleanup finished.',
			array(
				'files_deleted'   => $deleted,
				'rows_expired'    => count( $candidates ),
				'logs_pruned'     => (int) $pruned,
				'log_retention_d' => $log_days,
			)
		);
	}
}
