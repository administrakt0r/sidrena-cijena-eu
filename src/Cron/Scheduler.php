<?php
/**
 * Scheduling abstraction.
 *
 * WP-Cron is used for the daily run and cleanup; Action Scheduler (bundled
 * with WooCommerce) is preferred for queued service regeneration when
 * available. Production documentation recommends a real system cron entry.
 *
 * @package SidrenaCijena\Cron
 */

namespace SidrenaCijena\Cron;

use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and manages plugin cron events.
 */
final class Scheduler {

	/**
	 * Daily generation hook.
	 */
	const HOOK_DAILY = 'sidrena_cijena_daily_generation';

	/**
	 * Queued service regeneration hook.
	 */
	const HOOK_SERVICE = 'sidrena_cijena_service_regeneration';

	/**
	 * Daily cleanup hook.
	 */
	const HOOK_CLEANUP = 'sidrena_cijena_daily_cleanup';

	/**
	 * Action Scheduler group.
	 */
	const AS_GROUP = 'sidrena-cijena';

	/**
	 * Register cron callbacks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK_DAILY, array( DailyGeneration::class, 'run_scheduled' ) );
		add_action( self::HOOK_SERVICE, array( DailyGeneration::class, 'run_service_regeneration' ) );
		add_action( self::HOOK_CLEANUP, array( CleanupJob::class, 'run' ) );
	}

	/**
	 * Schedule all recurring events.
	 *
	 * @return void
	 */
	public static function schedule_all() {
		self::schedule_daily();
		self::schedule_cleanup();
	}

	/**
	 * Schedule the daily generation event before the configured deadline.
	 *
	 * @return void
	 */
	public static function schedule_daily() {
		if ( wp_next_scheduled( self::HOOK_DAILY ) ) {
			return;
		}

		$deadline = (string) Settings::get( 'deadline', '08:00' );

		wp_schedule_event( Dates::next_occurrence( $deadline, false ), 'daily', self::HOOK_DAILY );
	}

	/**
	 * Schedule the daily cleanup event.
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			return;
		}

		wp_schedule_event( Dates::next_occurrence( '03:30', false ), 'daily', self::HOOK_CLEANUP );
	}

	/**
	 * Unschedule every plugin event.
	 *
	 * @return void
	 */
	public static function unschedule_all() {
		wp_clear_scheduled_hook( self::HOOK_DAILY );
		wp_clear_scheduled_hook( self::HOOK_SERVICE );
		wp_clear_scheduled_hook( self::HOOK_CLEANUP );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_SERVICE, array(), self::AS_GROUP );
		}
	}

	/**
	 * Queue a service regeneration run.
	 *
	 * @param array<mixed> $args  Arguments passed to the handler.
	 * @param int          $delay Seconds to wait before running.
	 * @return void
	 */
	public static function queue_service_regeneration( array $args = array(), $delay = 300 ) {
		if ( function_exists( 'as_schedule_single_action' ) && apply_filters( 'sidrena_cijena_use_action_scheduler', true ) ) {
			as_schedule_single_action( time() + max( 0, (int) $delay ), self::HOOK_SERVICE, $args, self::AS_GROUP );

			return;
		}

		wp_schedule_single_event( time() + max( 0, (int) $delay ), self::HOOK_SERVICE, $args );
	}

	/**
	 * Timestamp of the next daily run.
	 *
	 * @return int|false
	 */
	public static function next_run() {
		return wp_next_scheduled( self::HOOK_DAILY );
	}

	/**
	 * Whether the daily event is scheduled.
	 *
	 * @return bool
	 */
	public static function is_healthy() {
		return (bool) wp_next_scheduled( self::HOOK_DAILY );
	}

	/**
	 * Re-schedule the daily event when the deadline setting changed.
	 *
	 * @return void
	 */
	public static function reschedule_daily() {
		wp_clear_scheduled_hook( self::HOOK_DAILY );
		self::schedule_daily();
	}
}
