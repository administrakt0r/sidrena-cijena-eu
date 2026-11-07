<?php
/**
 * CleanupJob test.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cron
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cron\CleanupJob;
use Titanworks\SidrenaCijena\Database\LogRepository;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

/**
 * Fake publications for cleanup.
 */
class CleanupJobFakePublication {

	/**
	 * Candidates to return.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $candidates = array();

	/**
	 * Marked IDs.
	 *
	 * @var array<int>
	 */
	public $marked = array();

	/**
	 * Stub.
	 *
	 * @param string $cutoff Cutoff date.
	 * @return array<int,array<string,mixed>>
	 */
	public function expired_candidates( $cutoff ) {
		return $this->candidates;
	}

	/**
	 * Stub.
	 *
	 * @param int|array<int> $id ID or list of IDs.
	 * @return void
	 */
	public function mark_expired( $id ) {
		if ( is_array( $id ) ) {
			$this->marked = array_merge( $this->marked, $id );
		} else {
			$this->marked[] = $id;
		}
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Cron\CleanupJob
 * @covers \Titanworks\SidrenaCijena\Database\LogRepository::prune
 */
class CleanupJobTest extends TestCase {

	/**
	 * Reset.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['twbce_test_db_queries']      = array();
		$GLOBALS['twbce_test_db_query_result'] = 0;
		$GLOBALS['twbce_test_options']         = array();
	}

	/**
	 * Reset.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'publications', null );
		unset( $GLOBALS['twbce_test_db_queries'] );
		unset( $GLOBALS['twbce_test_db_query_result'] );
		unset( $GLOBALS['twbce_test_options'] );
		parent::tearDown();
	}

	/**
	 * Test run logic.
	 *
	 * @return void
	 */
	public function test_run_deletes_files_and_marks_expired() {
		$pub = new CleanupJobFakePublication();
		PluginSwap::swap( 'publications', $pub );

		$temp_dir = sys_get_temp_dir() . '/twbce_test_' . uniqid();
		mkdir( $temp_dir );
		$file1 = $temp_dir . '/file1.csv';
		$file2 = $temp_dir . '/file2.csv';
		file_put_contents( $file1, 'test' );
		// file2 won't exist.

		$pub->candidates = array(
			array(
				'id'   => 10,
				'path' => $file1,
			),
			array(
				'id'   => 20,
				'path' => $file2,
			), // does not exist, but will still be marked expired.
		);

		// wp_delete_file uses unlink, so the file should be deleted.
		$GLOBALS['twbce_test_db_query_result'] = 5; // Return 5 rows pruned.

		CleanupJob::run();

		$this->assertFalse( file_exists( $file1 ) );
		$this->assertSame( array( 10, 20 ), $pub->marked );

		// Check prune query.
		$queries = $GLOBALS['twbce_test_db_queries'];
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'DELETE FROM `wp_sidrena_logs` WHERE created_at <', $queries[0] );

		rmdir( $temp_dir );
	}

	/**
	 * Test LogRepository::prune query correctness.
	 *
	 * @return void
	 */
	public function test_prune_query() {
		$repo = new LogRepository();

		$GLOBALS['twbce_test_db_query_result'] = 10;

		$deleted = $repo->prune( 30 );

		$this->assertSame( 10, $deleted );
		$queries = $GLOBALS['twbce_test_db_queries'];
		$this->assertCount( 1, $queries );

		// The query should use 30 days cutoff.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		$this->assertStringContainsString( $cutoff, $queries[0] );
	}

	/**
	 * Verify PublicationRepository::mark_expired with an array of IDs generates a single IN query.
	 *
	 * @return void
	 */
	public function test_mark_expired_batch_query() {
		$repo                             = new \Titanworks\SidrenaCijena\Database\PublicationRepository();
		$GLOBALS['twbce_test_db_queries'] = array();

		$result = $repo->mark_expired( array( 101, 102, 103 ) );

		$this->assertTrue( $result );
		$queries = $GLOBALS['twbce_test_db_queries'];
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "UPDATE `wp_sidrena_publications` SET status = 'expired'", $queries[0] );
		$this->assertStringContainsString( 'WHERE id IN (101,102,103)', $queries[0] );
	}
}
