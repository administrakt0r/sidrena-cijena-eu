<?php
/**
 * Retention cleanup.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Expires and removes price-list files past their retention window.
 */
class CleanupJob {

	/**
	 * Run the cleanup job.
	 *
	 * @return void
	 */
	public static function run() {}
}
