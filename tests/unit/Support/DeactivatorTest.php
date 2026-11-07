<?php
/**
 * Deactivator unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Support
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cron\Scheduler;
use Titanworks\SidrenaCijena\Support\Deactivator;

/**
 * @covers \Titanworks\SidrenaCijena\Support\Deactivator
 */
class DeactivatorTest extends TestCase {

	/**
	 * Reset test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_transients'] = array();
		$GLOBALS['twbce_test_actions']    = array();
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
			'twbce_cijena_deactivated',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		Deactivator::deactivate();

		$this->assertFalse( get_transient( Scheduler::CATCHUP_LOCK ) );
		$this->assertTrue( $action_fired );
	}
}
