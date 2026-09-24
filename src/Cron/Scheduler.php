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
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
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
	 * Schedule daily generation with a 15-minute buffer before the configured deadline.
	 *
	 * @return void
	 */
	public static function schedule_daily() {
		if ( wp_next_scheduled( self::HOOK_DAILY ) ) {
			return;
		}

		$deadline = (string) Settings::get( 'deadline', '08:00' );
		$parsed   = \DateTimeImmutable::createFromFormat( '!H:i', $deadline, Dates::timezone() );
		$run_time = $parsed ? $parsed->modify( '-15 minutes' )->format( 'H:i' ) : '07:45';
		$now      = Dates::now_site();
		$run_at   = $now->setTime( (int) substr( $run_time, 0, 2 ), (int) substr( $run_time, 3, 2 ) );
		$due_by   = $now->setTime( (int) substr( $deadline, 0, 2 ), (int) substr( $deadline, 3, 2 ) );

		// If activated/re-scheduled after the buffer but before the deadline, run promptly rather than missing today's list.
		$timestamp = $now >= $run_at && $now < $due_by
			? time() + 30
			: Dates::next_occurrence( $run_time, false );

		wp_schedule_event( $timestamp, 'daily', self::HOOK_DAILY );
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
	public static function queue_service_regeneration( array $args = array(), $delay = 0 ) {
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
	 * Warn administrators when daily generation is not scheduled or overdue.
	 *
	 * @return void
	 */
	public static function maybe_notice() {
		if ( ! is_admin() || ! \SidrenaCijena\Admin\Capabilities::current_user_can() ) {
			return;
		}

		$next = self::next_run();

		if ( $next && (int) $next >= time() ) {
			return;
		}

		$url = admin_url( 'admin.php?page=' . \SidrenaCijena\Admin\PublicationsPage::SLUG );

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Sidrena cijena: daily price-list generation is not scheduled or is overdue. Publications may become stale.', 'sidrena-cijena' ),
			esc_url( $url ),
			esc_html__( 'Open the price lists screen', 'sidrena-cijena' )
		);
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
