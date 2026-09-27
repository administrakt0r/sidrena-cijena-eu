<?php
/**
 * REST controller throttle unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Api
 */

namespace SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\PriceListController;
use WP_Error;

/**
 * Probe to access protected throttle method.
 */
class ThrottleProbe extends PriceListController {

	/**
	 * Expose throttle for testing.
	 *
	 * @return true|WP_Error
	 */
	public static function test_throttle() {
		return self::throttle();
	}
}

/**
 * @covers \SidrenaCijena\Api\PriceListController::throttle
 */
class PriceListControllerThrottleTest extends TestCase {

	/**
	 * Reset test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_transients']       = array();
		$GLOBALS['sidrena_test_cache']            = array();
		$GLOBALS['sidrena_test_ext_object_cache'] = false;
		$_SERVER['REMOTE_ADDR']                   = '192.168.1.100';
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		$GLOBALS['sidrena_test_ext_object_cache'] = false;

		parent::tearDown();
	}

	/**
	 * Missing IP bypasses throttling.
	 *
	 * @return void
	 */
	public function test_empty_ip_bypasses_throttle() {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertTrue( ThrottleProbe::test_throttle() );
	}

	/**
	 * Transient-based throttle increments and blocks at limit when ext object cache is disabled.
	 *
	 * @return void
	 */
	public function test_transient_throttle_increments_and_blocks() {
		wp_using_ext_object_cache( false );

		$result = ThrottleProbe::test_throttle();
		$this->assertTrue( $result );

		$key = 'sidrena_cijena_throttle_' . md5( '192.168.1.100' );
		$this->assertSame( 1, get_transient( $key ) );

		// Simulate reaching the limit (60).
		set_transient( $key, 60, 60 );

		$blocked = ThrottleProbe::test_throttle();
		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 'sidrena_cijena_throttled', $blocked->get_error_code() );
		$this->assertSame( 429, $blocked->get_error_data()['status'] );
	}

	/**
	 * Object-cache based throttle uses wp_cache and avoids transients when ext cache is active.
	 *
	 * @return void
	 */
	public function test_wp_cache_throttle_when_ext_object_cache_active() {
		wp_using_ext_object_cache( true );

		$result = ThrottleProbe::test_throttle();
		$this->assertTrue( $result );

		$key = 'sidrena_cijena_throttle_' . md5( '192.168.1.100' );

		// Transients should not be used.
		$this->assertFalse( get_transient( $key ) );

		// Object cache should have count 1.
		$cached = wp_cache_get( $key, 'sidrena_cijena_throttle' );
		$this->assertSame( 1, $cached );

		// Reach limit in object cache.
		wp_cache_set( $key, 60, 'sidrena_cijena_throttle', 60 );

		$blocked = ThrottleProbe::test_throttle();
		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 'sidrena_cijena_throttled', $blocked->get_error_code() );
		$this->assertSame( 429, $blocked->get_error_data()['status'] );
	}
}
