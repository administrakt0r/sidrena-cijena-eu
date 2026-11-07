<?php
/**
 * PriceHistoryRepository unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Database
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;

/**
 * Test wpdb stub for PriceHistoryRepository tests.
 */
class PriceHistoryRepositoryTestWpdb extends \wpdb {

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
	 * Stub var to return from get_var.
	 *
	 * @var mixed
	 */
	public $var_to_return = 0;

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

	/**
	 * {@inheritDoc}
	 *
	 * @param string|null $query Query string.
	 * @param int         $x     Column offset.
	 * @param int         $y     Row offset.
	 * @return mixed
	 */
	public function get_var( $query = null, $x = 0, $y = 0 ) {
		$this->queries[] = $query;

		return $this->var_to_return;
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Database\PriceHistoryRepository
 */
class PriceHistoryRepositoryTest extends TestCase {

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
	 * Active_sale_period_map memoizes queried products within the request.
	 *
	 * @return void
	 */
	public function test_active_sale_period_map_memoizes_queried_products() {
		global $wpdb;

		$test_wpdb                    = new PriceHistoryRepositoryTestWpdb();
		$test_wpdb->results_to_return = array(
			array(
				'product_id'       => 10,
				'variation_id'     => 0,
				'price'            => 12.50,
				'valid_from'       => '2026-09-01 00:00:00',
				'prior_closure_at' => null,
			),
		);
		$wpdb                         = $test_wpdb;

		$repo = new PriceHistoryRepository();

		// Query for product 10.
		$map1 = $repo->active_sale_period_map( array( 10 ) );
		$this->assertArrayHasKey( '10:0', $map1 );
		$this->assertCount( 1, $test_wpdb->queries );

		// Subsequent call for product 10 serves from request memoization without querying again.
		$map2 = $repo->active_sale_period_map( array( 10 ) );
		$this->assertArrayHasKey( '10:0', $map2 );
		$this->assertCount( 1, $test_wpdb->queries, 'Repeated call for same product ID must not issue another SQL query' );

		// Query including product 10 and a new product 20 only requests 20.
		$test_wpdb->results_to_return = array();
		$map3                         = $repo->active_sale_period_map( array( 10, 20 ) );
		$this->assertCount( 2, $test_wpdb->queries );
		$this->assertStringContainsString( 'IN (20)', $test_wpdb->queries[1], 'Must only query unmemoized product IDs' );
		$this->assertArrayHasKey( '10:0', $map3 );

		// Calling reset_cache clears memoization.
		$repo->reset_cache();
		$repo->active_sale_period_map( array( 10 ) );
		$this->assertCount( 3, $test_wpdb->queries, 'Must re-query after reset_cache' );
	}

	/**
	 * Count memoizes result for identical query arguments within request.
	 *
	 * @return void
	 */
	public function test_count_memoizes_identical_queries() {
		global $wpdb;

		$test_wpdb                = new PriceHistoryRepositoryTestWpdb();
		$test_wpdb->var_to_return = 42;
		$wpdb                     = $test_wpdb;

		$repo = new PriceHistoryRepository();

		$count1 = $repo->count( array( 'price_type' => 'anchor' ) );
		$this->assertSame( 42, $count1 );
		$this->assertCount( 1, $test_wpdb->queries );

		// Repeated count call with same arguments serves from in-memory cache.
		$count2 = $repo->count( array( 'price_type' => 'anchor' ) );
		$this->assertSame( 42, $count2 );
		$this->assertCount( 1, $test_wpdb->queries, 'Repeated count call with identical args must not query DB again' );

		// reset_cache clears the cache.
		$repo->reset_cache();

		$count3 = $repo->count( array( 'price_type' => 'anchor' ) );
		$this->assertSame( 42, $count3 );
		$this->assertCount( 2, $test_wpdb->queries, 'Must re-query after cache is reset' );
	}
}
