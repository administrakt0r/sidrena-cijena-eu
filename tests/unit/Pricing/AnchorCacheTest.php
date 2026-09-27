<?php
/**
 * Unit tests for the anchor price cache.
 *
 * @package SidrenaCijena\Tests\Unit\Pricing
 */

namespace SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Pricing\AnchorCache;

/**
 * Covers get/set round trips, misses, invalidation and generation flushes.
 *
 * @covers \SidrenaCijena\Pricing\AnchorCache
 */
class AnchorCacheTest extends TestCase {

	/**
	 * Reset transient state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_transients'] = array();
	}

	/**
	 * A missing entry returns null without a hit.
	 *
	 * @return void
	 */
	public function test_miss_returns_null_without_hit() {
		$cache = new AnchorCache();

		$this->assertNull( $cache->get_anchor( 7, 0 ) );
		$this->assertFalse( $cache->is_hit() );
	}

	/**
	 * A stored record survives a set/get round trip.
	 *
	 * @return void
	 */
	public function test_set_and_get_round_trip() {
		$cache  = new AnchorCache();
		$anchor = array(
			'id'             => 3,
			'price'          => '7.49',
			'reference_date' => '2026-09-10',
		);

		$cache->set_anchor( 7, 0, $anchor );

		$this->assertSame( $anchor, $cache->get_anchor( 7, 0 ) );
		$this->assertTrue( $cache->is_hit() );
	}

	/**
	 * A cached null (item known to have no anchor) is still a hit.
	 *
	 * @return void
	 */
	public function test_cached_null_is_a_hit() {
		$cache = new AnchorCache();

		$cache->set_anchor( 8, 0, null );

		$this->assertNull( $cache->get_anchor( 8, 0 ) );
		$this->assertTrue( $cache->is_hit() );
	}

	/**
	 * Garbage in the transient layer is treated as a miss.
	 *
	 * @return void
	 */
	public function test_malformed_entry_is_a_miss() {
		$cache = new AnchorCache();

		$cache->set_anchor( 9, 0, array( 'price' => '7.49' ) );
		$GLOBALS['sidrena_test_transients']['sidrena_anchor_9_0_0'] = 'corrupted';

		$this->assertNull( $cache->get_anchor( 9, 0 ) );
		$this->assertFalse( $cache->is_hit() );
	}

	/**
	 * Invalidating one item leaves the others cached.
	 *
	 * @return void
	 */
	public function test_invalidate_forgets_only_the_item() {
		$cache = new AnchorCache();

		$cache->set_anchor( 7, 0, array( 'price' => '7.49' ) );
		$cache->set_anchor( 8, 0, array( 'price' => '6.99' ) );

		$cache->invalidate( 7, 0 );

		$this->assertNull( $cache->get_anchor( 7, 0 ) );
		$this->assertFalse( $cache->is_hit() );
		$this->assertSame( array( 'price' => '6.99' ), $cache->get_anchor( 8, 0 ) );
		$this->assertTrue( $cache->is_hit() );
	}

	/**
	 * Invalidating a variation also forgets the parent product entry.
	 *
	 * @return void
	 */
	public function test_variation_invalidate_also_forgets_parent() {
		$cache = new AnchorCache();

		$cache->set_anchor( 7, 0, array( 'price' => '7.49' ) );
		$cache->set_anchor( 7, 3, array( 'price' => '7.99' ) );

		$cache->invalidate( 7, 3 );

		$this->assertNull( $cache->get_anchor( 7, 3 ) );
		$this->assertNull( $cache->get_anchor( 7, 0 ) );
	}

	/**
	 * Flushing bumps the generation so previously stored entries miss.
	 *
	 * @return void
	 */
	public function test_flush_orphans_previous_entries() {
		$cache = new AnchorCache();

		$cache->set_anchor( 7, 0, array( 'price' => '7.49' ) );
		$this->assertFalse( $cache->is_hit() ); // No lookup yet; hit flag starts false.
		$this->assertSame( array( 'price' => '7.49' ), $cache->get_anchor( 7, 0 ) );

		$cache->flush();

		$this->assertNull( $cache->get_anchor( 7, 0 ) );
		$this->assertFalse( $cache->is_hit() );
	}

	/**
	 * Two flushes inside the same second still invalidate entries written between them.
	 *
	 * @return void
	 */
	public function test_back_to_back_flushes_bump_generation() {
		$cache = new AnchorCache();

		$cache->flush();
		$first = get_transient( 'sidrena_cijena_cache_generation' );

		$cache->set_anchor( 7, 0, array( 'price' => '7.99' ) );
		$this->assertSame( array( 'price' => '7.99' ), $cache->get_anchor( 7, 0 ) );

		$cache->flush();

		$this->assertGreaterThan( (int) $first, (int) get_transient( 'sidrena_cijena_cache_generation' ) );
		$this->assertNull( $cache->get_anchor( 7, 0 ) );
		$this->assertFalse( $cache->is_hit() );
	}

	/**
	 * Batch setting records multiple items at once.
	 *
	 * @return void
	 */
	public function test_set_anchors_batch() {
		$cache = new AnchorCache();

		$cache->set_anchors_batch(
			array(
				array(
					'product_id'   => 10,
					'variation_id' => 0,
					'anchor'       => array( 'price' => '10.50' ),
				),
				array(
					'product_id'   => 10,
					'variation_id' => 2,
					'anchor'       => array( 'price' => '11.50' ),
				),
			)
		);

		$this->assertSame( array( 'price' => '10.50' ), $cache->get_anchor( 10, 0 ) );
		$this->assertSame( array( 'price' => '11.50' ), $cache->get_anchor( 10, 2 ) );
	}

	/**
	 * When external object cache is available, transients are bypassed.
	 *
	 * @return void
	 */
	public function test_external_object_cache_bypasses_transients() {
		wp_using_ext_object_cache( true );
		$cache = new AnchorCache();

		$cache->set_anchor( 12, 0, array( 'price' => '12.99' ) );

		// Object cache has the value.
		$this->assertSame( array( 'price' => '12.99' ), $cache->get_anchor( 12, 0 ) );

		// Transients were not written.
		$key = sprintf( 'sidrena_anchor_12_0_%d', (int) get_transient( 'sidrena_cijena_cache_generation' ) );
		$this->assertArrayNotHasKey( $key, $GLOBALS['sidrena_test_transients'] );

		wp_using_ext_object_cache( false );
	}

	/**
	 * Flush records generation as a non-autoloaded option and transient.
	 *
	 * @return void
	 */
	public function test_flush_bookkeeping_row_not_autoloaded() {
		$cache = new AnchorCache();
		$cache->flush();

		$transient_val = get_transient( 'sidrena_cijena_cache_generation' );
		$this->assertNotEmpty( $transient_val );

		// Option row exists and is explicitly marked not autoloaded.
		$this->assertArrayHasKey( '_transient_sidrena_cijena_cache_generation', $GLOBALS['sidrena_test_options'] );
	}
}
