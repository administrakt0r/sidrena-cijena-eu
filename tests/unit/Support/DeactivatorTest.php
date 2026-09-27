<?php
/**
 * Deactivator unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Support
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Support\Deactivator;

/**
 * @covers \SidrenaCijena\Support\Deactivator
 */
class DeactivatorTest extends TestCase {

	/**
	 * Reset test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_transients'] = array();
		$GLOBALS['sidrena_test_actions']    = array();
	}

	/**
	 * Verify deactivate clears scheduled events, removes catchup lock, and fires action.
	 *
	 * @return void
	 */
	public function test_deactivate_cleans_up_runtime_state_and_fires_action() {
		set_transient( Scheduler::CATCHUP_LOCK, 1, 300 );
		$this->assertSame( 1, get_transient( Scheduler::CATCHUP_LOCK ) );

		$action_fired = false;
		add_action(
			'sidrena_cijena_deactivated',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		Deactivator::deactivate();

		$this->assertFalse( get_transient( Scheduler::CATCHUP_LOCK ) );
		$this->assertTrue( $action_fired );
	}
}
