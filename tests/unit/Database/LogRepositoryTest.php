<?php
/**
 * LogRepository unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Database
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Database\LogRepository;

/**
 * Test wpdb stub for LogRepository tests.
 */
class LogRepositoryTestWpdb extends \wpdb {

	/**
	 * Recorded queries.
	 *
	 * @var string[]
	 */
	public $queries = array();

	/**
	 * Results to return from get_results.
	 *
	 * @var array[]
	 */
	public $results_to_return = array();

	/**
	 * Var to return from get_var.
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

	/**
	 * {@inheritDoc}
	 *
	 * @param string            $table  Table name.
	 * @param array             $data   Data to insert.
	 * @param array|string|null $format Data format.
	 * @return int
	 */
	public function insert( $table, $data, $format = null ) {
		$this->queries[] = 'INSERT INTO ' . $table;
		$this->insert_id = 99;

		return 1;
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Database\LogRepository
 */
class LogRepositoryTest extends TestCase {

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
		delete_transient( 'twbce_log_cnt_all' );
	}

	/**
	 * Tear down test wpdb.
	 */
	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->orig_wpdb;
		delete_transient( 'twbce_log_cnt_all' );

		parent::tearDown();
	}

	/**
	 * Query() on page 1 with count < per_page derives total from rows without issuing COUNT(*).
	 *
	 * @return void
	 */
	public function test_query_bypasses_count_when_first_page_has_fewer_than_per_page_rows() {
		global $wpdb;

		$test_wpdb                    = new LogRepositoryTestWpdb();
		$test_wpdb->results_to_return = array(
			array(
				'id'         => 1,
				'level'      => 'info',
				'event'      => 'export_manual',
				'message'    => 'Price list published.',
				'context'    => '{}',
				'user_id'    => 1,
				'created_at' => '2026-11-05 12:00:00',
			),
			array(
				'id'         => 2,
				'level'      => 'warning',
				'event'      => 'export_warning',
				'message'    => 'Warning notice.',
				'context'    => '{}',
				'user_id'    => 1,
				'created_at' => '2026-11-05 12:01:00',
			),
		);
		$wpdb                         = $test_wpdb;

		$repo   = new LogRepository();
		$result = $repo->query(
			array(
				'page'     => 1,
				'per_page' => 50,
			)
		);

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
		// Exactly 1 SQL query issued: the SELECT ... LIMIT ... OFFSET. COUNT(*) was completely bypassed.
		$this->assertCount( 1, $test_wpdb->queries );
		$this->assertStringContainsString( 'LIMIT 50 OFFSET 0', $test_wpdb->queries[0] );
		$this->assertStringNotContainsString( 'COUNT(*)', $test_wpdb->queries[0] );
	}

	/**
	 * Query() on page 1 with count == per_page issues COUNT(*) to discover full total.
	 *
	 * @return void
	 */
	public function test_query_issues_count_when_first_page_is_full() {
		global $wpdb;

		$test_wpdb  = new LogRepositoryTestWpdb();
		$dummy_rows = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$dummy_rows[] = array(
				'id'         => $i,
				'level'      => 'info',
				'event'      => 'export_manual',
				'message'    => 'Msg ' . $i,
				'context'    => '{}',
				'user_id'    => 1,
				'created_at' => '2026-11-05 12:00:00',
			);
		}
		$test_wpdb->results_to_return = $dummy_rows;
		$test_wpdb->var_to_return     = 100;
		$wpdb                         = $test_wpdb;

		$repo   = new LogRepository();
		$result = $repo->query(
			array(
				'page'     => 1,
				'per_page' => 5,
			)
		);

		$this->assertSame( 100, $result['total'] );
		$this->assertCount( 5, $result['items'] );
		// Two queries: SELECT ... LIMIT and SELECT COUNT(*).
		$this->assertCount( 2, $test_wpdb->queries );
		$this->assertStringContainsString( 'LIMIT 5 OFFSET 0', $test_wpdb->queries[0] );
		$this->assertStringContainsString( 'COUNT(*)', $test_wpdb->queries[1] );
	}

	/**
	 * Count_all() caches via transient and invalidates on insert.
	 *
	 * @return void
	 */
	public function test_count_all_caches_and_invalidates_on_insert() {
		global $wpdb;

		$test_wpdb                = new LogRepositoryTestWpdb();
		$test_wpdb->var_to_return = 75;
		$wpdb                     = $test_wpdb;

		$repo = new LogRepository();

		$count1 = $repo->count_all();
		$this->assertSame( 75, $count1 );
		$this->assertCount( 1, $test_wpdb->queries );

		// Subsequent call serves from transient without querying.
		$count2 = $repo->count_all();
		$this->assertSame( 75, $count2 );
		$this->assertCount( 1, $test_wpdb->queries );

		// insert() invalidates the transient.
		$repo->insert( 'info', 'test_event', 'Test message' );

		$test_wpdb->var_to_return = 76;
		$count3                   = $repo->count_all();
		$this->assertSame( 76, $count3 );
		// 1 (first count) + 1 (insert) + 1 (second count after invalidation) = 3 queries.
		$this->assertCount( 3, $test_wpdb->queries );
	}
}
