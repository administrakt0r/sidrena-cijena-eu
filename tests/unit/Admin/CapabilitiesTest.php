<?php
/**
 * Capabilities unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\Capabilities;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\Capabilities
 */
class CapabilitiesTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		unset(
			$GLOBALS['twbce_test_roles'],
			$GLOBALS['twbce_test_can'],
			$GLOBALS['wp_filter']['twbce_cijena_capability']
		);
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['twbce_test_roles'],
			$GLOBALS['twbce_test_can'],
			$GLOBALS['wp_filter']['twbce_cijena_capability']
		);
		parent::tearDown();
	}

	/**
	 * Base capability defaults to twbce_manage_prices.
	 *
	 * @return void
	 */
	public function test_default_capability(): void {
		$this->assertSame( 'twbce_manage_prices', Capabilities::capability() );
	}

	/**
	 * Capability filter allows overriding required capability.
	 *
	 * @return void
	 */
	public function test_capability_filter(): void {
		add_filter(
			'twbce_cijena_capability',
			static function () {
				return 'custom_manage_cap';
			}
		);

		$this->assertSame( 'custom_manage_cap', Capabilities::capability() );
	}

	/**
	 * Current user can reflects user permissions.
	 *
	 * @return void
	 */
	public function test_current_user_can(): void {
		$this->assertTrue( Capabilities::current_user_can() );

		$GLOBALS['twbce_test_can'] = false;
		$this->assertFalse( Capabilities::current_user_can() );
	}

	/**
	 * Grant adds capability to administrator role.
	 *
	 * @return void
	 */
	public function test_grant(): void {
		$admin = get_role( 'administrator' );
		$this->assertNotNull( $admin );
		$this->assertFalse( $admin->has_cap( Capabilities::CAPABILITY ) );

		Capabilities::grant();
		$this->assertTrue( $admin->has_cap( Capabilities::CAPABILITY ) );
	}

	/**
	 * Revoke removes capability from administrator role.
	 *
	 * @return void
	 */
	public function test_revoke(): void {
		Capabilities::grant();
		$admin = get_role( 'administrator' );
		$this->assertNotNull( $admin );
		$this->assertTrue( $admin->has_cap( Capabilities::CAPABILITY ) );

		Capabilities::revoke();
		$this->assertFalse( $admin->has_cap( Capabilities::CAPABILITY ) );
	}
}
