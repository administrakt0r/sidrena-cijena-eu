<?php
/**
 * REST/page cache behavior tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Api
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Api\RestCache;

/**
 * @covers \Titanworks\SidrenaCijena\Api\RestCache
 */
class RestCacheTest extends TestCase {

	/**
	 * Reset option and cache state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options']    = array();
		$GLOBALS['twbce_test_transients'] = array();
	}

	/**
	 * The default generation starts at one.
	 *
	 * @return void
	 */
	public function test_generation_defaults_to_one() {
		$cache = new RestCache();

		$this->assertSame( 1, $cache->generation() );
	}

	/**
	 * Argument order does not change the key, but a flush does.
	 *
	 * @return void
	 */
	public function test_key_is_order_independent_and_generation_scoped() {
		$cache = new RestCache();

		$first  = $cache->key(
			array(
				'page'     => 'cjenik',
				'location' => 3,
			)
		);
		$second = $cache->key(
			array(
				'location' => 3,
				'page'     => 'cjenik',
			)
		);

		$this->assertSame( $first, $second );

		$cache->flush();

		$this->assertNotSame(
			$first,
			$cache->key(
				array(
					'page'     => 'cjenik',
					'location' => 3,
				)
			)
		);
	}

	/**
	 * Flushing bumps the generation so every old key misses.
	 *
	 * @return void
	 */
	public function test_flush_bumps_generation() {
		$cache  = new RestCache();
		$before = $cache->generation();

		$cache->flush();

		$this->assertGreaterThanOrEqual( $before, $cache->generation() );
		$this->assertGreaterThanOrEqual( time() - 5, $cache->generation() );
	}

	/**
	 * Two flushes inside the same second still invalidate entries written between them.
	 *
	 * @return void
	 */
	public function test_back_to_back_flushes_bump_generation() {
		$cache = new RestCache();
		$args  = array( 'page' => 'cjenik' );

		$cache->flush();

		$key = $cache->key( $args );
		$cache->set( $key, array( 'html' => 'between flushes' ) );

		$this->assertSame( array( 'html' => 'between flushes' ), $cache->get( $key ) );

		$cache->flush();

		$this->assertNull( $cache->get( $cache->key( $args ) ) );
	}

	/**
	 * Values survive a set/get round trip.
	 *
	 * @return void
	 */
	public function test_set_and_get_round_trip() {
		$cache = new RestCache();
		$key   = $cache->key( array( 'page' => 'cjenik' ) );

		$this->assertNull( $cache->get( $key ) );

		$cache->set( $key, array( 'html' => '<table></table>' ) );

		$this->assertSame( array( 'html' => '<table></table>' ), $cache->get( $key ) );
	}
}
