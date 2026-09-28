<?php
/**
 * Scheduler and DailyGeneration unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Cron
 */

namespace SidrenaCijena\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Cron\DailyGeneration;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Support\Dates;
use WP_Error;

/**
 * @covers \SidrenaCijena\Cron\Scheduler
 * @covers \SidrenaCijena\Cron\DailyGeneration
 */
class SchedulerTest extends TestCase {

	/**
	 * Reset test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
		$GLOBALS['sidrena_test_is_admin']   = true;
		$_GET['page']                       = 'sidrena-cijena-publications';
	}

	/**
	 * Tear down test globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();

		unset( $_GET['page'] );
	}

	/**
	 * Summarize handles success, skipped, and error results.
	 *
	 * @return void
	 */
	public function test_summarize_counts_outcomes() {
		$results = array(
			1 => array(
				'csv' => array( 'filename' => 'P1.csv' ),
				'xml' => array( 'skipped' => true ),
			),
			2 => array(
				'csv' => new WP_Error( 'failed', 'CSV error' ),
			),
			3 => new WP_Error( 'missing', 'Location error' ),
		);

		$summary = DailyGeneration::summarize( $results );

		$this->assertSame( 1, $summary['published'] );
		$this->assertSame( 1, $summary['skipped'] );
		$this->assertSame( 2, $summary['failed'] );
	}

	/**
	 * Error extractor extracts messages from WP_Error results.
	 *
	 * @return void
	 */
	public function test_extract_errors_formats_messages() {
		$results = array(
			1 => array(
				'csv' => new WP_Error( 'err_csv', 'CSV header invalid' ),
			),
			2 => new WP_Error( 'missing_loc', 'Location missing' ),
		);

		$errors = DailyGeneration::extract_errors( $results );

		$this->assertCount( 2, $errors );
		$this->assertStringContainsString( 'Location #1 (CSV): CSV header invalid', $errors[0] );
		$this->assertStringContainsString( 'Location #2: Location missing', $errors[1] );
	}

	/**
	 * Catchup lock prevents concurrent catchup runs.
	 *
	 * @return void
	 */
	public function test_catchup_respects_lock() {
		set_transient( Scheduler::CATCHUP_LOCK, 1, 300 );

		// When locked, maybe_catchup must not throw or run.
		Scheduler::maybe_catchup();

		$this->assertSame( 1, get_transient( Scheduler::CATCHUP_LOCK ) );
	}

	/**
	 * Ordinary frontend requests skip catchup checks to avoid per-request queries.
	 *
	 * @return void
	 */
	public function test_catchup_skips_ordinary_frontend_requests() {
		$GLOBALS['sidrena_test_is_admin'] = false;

		Scheduler::maybe_catchup();

		$this->assertFalse( get_transient( Scheduler::CATCHUP_LOCK ) );
	}

	/**
	 * Queue catchup schedules a single event for background execution.
	 *
	 * @return void
	 */
	public function test_queue_catchup_schedules_event() {
		wp_clear_scheduled_hook( Scheduler::HOOK_CATCHUP );

		Scheduler::queue_catchup();

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_CATCHUP ) );

		wp_clear_scheduled_hook( Scheduler::HOOK_CATCHUP );
	}

	/**
	 * Reschedule daily clears the hook and reschedules.
	 *
	 * @return void
	 */
	public function test_reschedule_daily_registers_event() {
		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );
		$this->assertFalse( wp_next_scheduled( Scheduler::HOOK_DAILY ) );

		Scheduler::reschedule_daily();

		$this->assertNotFalse( wp_next_scheduled( Scheduler::HOOK_DAILY ) );
	}

	/**
	 * An event that is overdue beyond the grace period is not healthy.
	 *
	 * @return void
	 */
	public function test_is_healthy_reports_overdue_events() {
		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );
		$this->assertFalse( Scheduler::is_healthy() );

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Scheduler::HOOK_DAILY );
		$this->assertTrue( Scheduler::is_healthy() );

		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );
		wp_schedule_event( time() - ( 2 * HOUR_IN_SECONDS ), 'daily', Scheduler::HOOK_DAILY );
		$this->assertFalse( Scheduler::is_healthy() );

		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );
	}

	/**
	 * Inside the buffer window a prompt run is queued soon, while a
	 * reschedule after running must not queue another forced run.
	 *
	 * @return void
	 */
	public function test_prompt_window_queue_and_non_prompt_reschedule() {
		$now = Dates::now_site();

		if ( $now->modify( '-5 minutes' )->format( 'Y-m-d' ) !== $now->modify( '+10 minutes' )->format( 'Y-m-d' ) ) {
			$this->markTestSkipped( 'The buffer window crosses midnight on this run.' );
		}

		update_option( 'sidrena_cijena_settings', array( 'deadline' => $now->modify( '+10 minutes' )->format( 'H:i' ) ), false );

		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );

		Scheduler::schedule_daily( true );
		$prompt = wp_next_scheduled( Scheduler::HOOK_DAILY );

		$this->assertNotFalse( $prompt );
		$this->assertLessThanOrEqual( time() + 40, $prompt );

		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );

		Scheduler::schedule_daily( false );
		$next = wp_next_scheduled( Scheduler::HOOK_DAILY );

		$this->assertNotFalse( $next );
		$this->assertGreaterThan( time() + 600, $next );

		wp_clear_scheduled_hook( Scheduler::HOOK_DAILY );
	}

	/**
	 * Notice shows error when last generation failed.
	 *
	 * @return void
	 */
	public function test_notice_displays_failure_when_recorded() {
		$failure = array(
			'timestamp' => time(),
			'date'      => Dates::today(),
			'source'    => 'scheduled',
			'summary'   => array(
				'published' => 0,
				'skipped'   => 0,
				'failed'    => 1,
			),
			'errors'    => array( 'Location #1 (CSV): validation failed' ),
		);

		update_option( DailyGeneration::FAILURE_OPTION, $failure, false );

		ob_start();
		Scheduler::maybe_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( '1 price list failed to publish', $html );
		$this->assertStringContainsString( 'validation failed', $html );
	}

	/**
	 * Notice is hidden when dismissed.
	 *
	 * @return void
	 */
	public function test_notice_hidden_when_dismissed() {
		$failure = array(
			'timestamp' => time(),
			'date'      => Dates::today(),
			'source'    => 'scheduled',
			'summary'   => array(
				'published' => 0,
				'skipped'   => 0,
				'failed'    => 1,
			),
			'errors'    => array( 'Location #1: error' ),
		);

		update_option( DailyGeneration::FAILURE_OPTION, $failure, false );
		update_option( DailyGeneration::DISMISS_OPTION, 1, false );
		wp_schedule_event( time() + 3600, 'daily', Scheduler::HOOK_DAILY );

		ob_start();
		Scheduler::maybe_notice();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'notice-error', $html );
		$this->assertStringNotContainsString( 'failed to publish', $html );
	}
}
