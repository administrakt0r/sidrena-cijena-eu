<?php
/**
 * SpecialSaleRepository unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Database
 */

namespace SidrenaCijena\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Database\SpecialSaleRepository;

/**
 * Test wpdb stub for SpecialSaleRepository tests.
 */
class SpecialSaleRepositoryTestWpdb extends \wpdb {

	/**
	 * Recorded queries.
	 *
	 * @var string[]
	 */
	public $queries = array();

	/**
	 * Stub rows to return from get_results.
	 *
	 * @var array[]
	 */
	public $results_to_return = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string|null $query  Query string.
	 * @param string      $output Output format.
	 * @return array[]
	 */
	public function get_results( $query = null, $output = OBJECT ) {
		$this->queries[] = $query;

		return $this->results_to_return;
	}
}

/**
 * @covers \SidrenaCijena\Database\SpecialSaleRepository
 */
class SpecialSaleRepositoryTest extends TestCase {

	/**
	 * Original wpdb.
	 *
	 * @var \wpdb
	 */
	protected $orig_wpdb;

	/**
	 * Set up test wpdb.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		$this->orig_wpdb = $wpdb;
	}

	/**
	 * Tear down test wpdb.
	 */
	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->orig_wpdb;

		parent::tearDown();
	}

	/**
	 * Active_for_item leverages memoized active_map cache to prevent N+1 queries.
	 *
	 * @return void
	 */
	public function test_active_for_item_serves_from_active_map_cache() {
		global $wpdb;

		$test_wpdb                    = new SpecialSaleRepositoryTestWpdb();
		$test_wpdb->results_to_return = array(
			array(
				'id'                  => 1,
				'product_id'          => 10,
				'variation_id'        => 0,
				'sale_type'           => 'akcija',
				'status'              => 'active',
				'start_date'          => '2026-09-01 00:00:00',
				'end_date'            => '2026-10-31 23:59:59',
				'promo_price'         => 7.50,
				'lowest_30_day_price' => 8.50,
				'currency'            => 'EUR',
				'created_at'          => '2026-09-01 00:00:00',
			),
		);
		$wpdb                         = $test_wpdb;

		$repo = new SpecialSaleRepository();

		// First call queries active_map.
		$sale1 = $repo->active_for_item( 10, 0 );
		$this->assertNotNull( $sale1 );
		$this->assertSame( 1, $sale1['id'] );
		$this->assertCount( 1, $test_wpdb->queries );

		// Second call for same or another product serves from the same active map without extra SQL query.
		$sale2 = $repo->active_for_item( 10, 0 );
		$this->assertNotNull( $sale2 );
		$this->assertCount( 1, $test_wpdb->queries, 'Must not issue another query for subsequent active_for_item calls' );

		// Product without active sale returns null from cache.
		$sale3 = $repo->active_for_item( 999, 0 );
		$this->assertNull( $sale3 );
		$this->assertCount( 1, $test_wpdb->queries, 'Must not query DB for missing items when active_map is memoized' );
	}
}
