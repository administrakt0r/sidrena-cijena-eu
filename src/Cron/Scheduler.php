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

use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Admin\PublicationsPage;
use SidrenaCijena\Plugin;
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
	 * Dismiss failure action.
	 */
	const DISMISS_FAILURE_ACTION = 'sidrena_cijena_dismiss_generation_failure';

	/**
	 * Catch-up transient lock.
	 */
	const CATCHUP_LOCK = 'sidrena_cijena_catchup_lock';

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
		add_action( 'admin_post_' . self::DISMISS_FAILURE_ACTION, array( __CLASS__, 'handle_dismiss_failure' ) );
		add_action( 'init', array( __CLASS__, 'maybe_catchup' ) );
		add_action( 'update_option_timezone_string', array( __CLASS__, 'reschedule_daily' ) );
		add_action( 'update_option_gmt_offset', array( __CLASS__, 'reschedule_daily' ) );
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
	 * Warn administrators when daily generation failed, is missed, or is overdue.
	 *
	 * @return void
	 */
	public static function maybe_notice() {
		if ( ! is_admin() || ! Capabilities::current_user_can() ) {
			return;
		}

		$failure   = get_option( DailyGeneration::FAILURE_OPTION, null );
		$dismissed = (bool) get_option( DailyGeneration::DISMISS_OPTION, false );

		if ( $failure && ! $dismissed && is_array( $failure ) ) {
			$dismiss_url  = wp_nonce_url(
				add_query_arg( array( 'action' => self::DISMISS_FAILURE_ACTION ), admin_url( 'admin-post.php' ) ),
				self::DISMISS_FAILURE_ACTION
			);
			$pub_url      = admin_url( 'admin.php?page=' . PublicationsPage::SLUG );
			$failed_count = isset( $failure['summary']['failed'] ) ? (int) $failure['summary']['failed'] : 1;
			$details      = ! empty( $failure['errors'] ) ? implode( '; ', (array) $failure['errors'] ) : '';
			$message      = 1 === $failed_count
				? __( '1 price list failed to publish.', 'sidrena-cijena' )
				: sprintf(
					/* translators: %d: count of failed price lists */
					__( '%d price lists failed to publish.', 'sidrena-cijena' ),
					$failed_count
				);

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s %3$s <a href="%4$s">%5$s</a> | <a href="%6$s">%7$s</a></p></div>',
				esc_html__( 'Sidrena cijena: daily price-list generation failed.', 'sidrena-cijena' ),
				esc_html( $message ),
				$details ? esc_html( '(' . $details . ')' ) : '',
				esc_url( $pub_url ),
				esc_html__( 'Open the price lists screen', 'sidrena-cijena' ),
				esc_url( $dismiss_url ),
				esc_html__( 'Dismiss', 'sidrena-cijena' )
			);

			return;
		}

		if ( self::is_missed_run_today() ) {
			$deadline = (string) Settings::get( 'deadline', '08:00' );
			$url      = admin_url( 'admin.php?page=' . PublicationsPage::SLUG );

			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				sprintf(
					/* translators: %s: deadline time */
					esc_html__( 'Sidrena cijena: today\'s %s deadline passed without a successful price-list publication. Catch-up generation is pending.', 'sidrena-cijena' ),
					esc_html( $deadline )
				),
				esc_url( $url ),
				esc_html__( 'Open the price lists screen', 'sidrena-cijena' )
			);

			return;
		}

		$next = self::next_run();

		if ( $next && (int) $next >= time() ) {
			return;
		}

		$url = admin_url( 'admin.php?page=' . PublicationsPage::SLUG );

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Sidrena cijena: daily price-list generation is not scheduled or is overdue. Publications may become stale.', 'sidrena-cijena' ),
			esc_url( $url ),
			esc_html__( 'Open the price lists screen', 'sidrena-cijena' )
		);
	}

	/**
	 * Dismiss the generation failure notice.
	 *
	 * @return void
	 */
	public static function handle_dismiss_failure() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::DISMISS_FAILURE_ACTION );

		update_option( DailyGeneration::DISMISS_OPTION, 1, false );

		$back = wp_get_referer();

		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=' . PublicationsPage::SLUG );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Check if today's scheduled run was missed.
	 *
	 * A run is missed when today is a working day, current site time is past
	 * the deadline, and any active location is missing today's publication.
	 *
	 * @return bool
	 */
	public static function is_missed_run_today() {
		$today = Dates::today();

		if ( ! Dates::is_working_day( $today ) ) {
			return false;
		}

		$deadline = (string) Settings::get( 'deadline', '08:00' );
		$now      = Dates::now_site();
		$due      = Dates::parse_site( $today . ' ' . $deadline );

		if ( ! $due || $now < $due ) {
			return false;
		}

		$missed = self::missed_locations_today();

		return ! empty( $missed );
	}

	/**
	 * Active locations that are missing today's publication.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function missed_locations_today() {
		$locations = Plugin::instance()->locations()->all( true );
		$pubs      = Plugin::instance()->publications();
		$today     = Dates::today();
		$missed    = array();

		foreach ( $locations as $location ) {
			$loc_id = (int) $location['id'];
			if ( ! $pubs->has_published_on_date( $loc_id, $today ) ) {
				$missed[] = $location;
			}
		}

		return $missed;
	}

	/**
	 * Run catch-up generation if a run was missed today.
	 *
	 * @return void
	 */
	public static function maybe_catchup() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( ! self::is_missed_run_today() ) {
			return;
		}

		if ( get_transient( self::CATCHUP_LOCK ) ) {
			return;
		}

		set_transient( self::CATCHUP_LOCK, 1, 300 );

		DailyGeneration::run_catchup();
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
