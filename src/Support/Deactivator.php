<?php
/**
 * Deactivation routine.
 *
 * Deactivation preserves every piece of data (FR-077).
 *
 * @package Titanworks\SidrenaCijena\Support
 */

namespace Titanworks\SidrenaCijena\Support;

use Titanworks\SidrenaCijena\Cron\Scheduler;

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

		delete_transient( Scheduler::CATCHUP_LOCK );

		flush_rewrite_rules();

		/**
		 * Fires after the plugin has been deactivated.
		 */
		do_action( 'twbce_cijena_deactivated' );
	}
}
