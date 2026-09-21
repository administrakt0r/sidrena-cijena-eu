<?php
/**
 * Deactivation routine.
 *
 * Deactivation preserves every piece of data (FR-077).
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

use SidrenaCijena\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation.
 */
final class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Scheduler::unschedule_all();

		flush_rewrite_rules();

		/**
		 * Fires after the plugin has been deactivated.
		 */
		do_action( 'sidrena_cijena_deactivated' );
	}
}
