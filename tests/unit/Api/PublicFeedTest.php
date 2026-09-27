<?php
/**
 * Unit tests for the public price-list feed.
 *
 * @package SidrenaCijena\Tests\Unit\Api
 */

namespace SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\PublicFeed;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * In-memory location rows.
 */
class FeedLocationFake {

	/**
	 * Rows returned by all().
	 *
	 * @var array<int,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Location rows.
	 *
	 * @param bool $active_only Whether to filter active rows.
	 * @return array<int,array<mixed>>
	 */
	public function all( $active_only = false ) {
		unset( $active_only );

		return $this->rows;
	}
}

/**
 * In-memory publication rows.
 */
class FeedPublicationFake {

	/**
	 * Row returned by latest().
	 *
	 * @var array<mixed>|null
	 */
	public $latest_row = null;

	/**
	 * Latest published row.
	 *
	 * @param int      $location_id Location ID.
	 * @param string   $format      Format.
	 * @param string[] $statuses    Statuses.
	 * @return array<mixed>|null
	 */
	public function latest( $location_id, $format, array $statuses = array() ) {
		unset( $location_id, $format, $statuses );

		return $this->latest_row;
	}
}

/**
 * Log recorder.
 */
class FeedLogRecorder {

	/**
	 * Recorded entries.
	 *
	 * @var array<int,array<mixed>>
	 */
	public $entries = array();

	/**
	 * Record a log entry.
	 *
	 * @param string $level   Level.
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @param int    $user_id User ID.
	 * @return int
	 */
	public function insert( $level, $event, $message, array $context = array(), $user_id = null ) {
		$this->entries[] = array(
			'level'   => $level,
			'event'   => $event,
			'message' => $message,
			'context' => $context,
			'user_id' => $user_id,
		);

		return count( $this->entries );
	}
}

/**
 * Probe capturing serve() side effects instead of streaming or exiting.
 */
class PublicFeedProbe extends PublicFeed {

	/**
	 * Captured send_file() calls.
	 *
	 * @var array<mixed>
	 */
	public $sent = array();

	/**
	 * Number of serve_missing() calls.
	 *
	 * @var int
	 */
	public $missing = 0;

	/**
	 * Record a streamed file.
	 *
	 * @param array<mixed> $row    Publication row.
	 * @param string       $format Format.
	 * @return void
	 */
	protected function send_file( array $row, $format ) {
		$this->sent = array(
			'row'    => $row,
			'format' => $format,
		);
	}

	/**
	 * Record a missing-file response.
	 *
	 * @return void
	 */
	protected function serve_missing() {
		++$this->missing;
	}

	/**
	 * Expose the protected lookup.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $format      Format.
	 * @return array<mixed>|null
	 */
	public function file_for( $location_id, $format ) {
		return $this->current_file( $location_id, $format );
	}

	/**
	 * Expose the protected rebuild queueing.
	 *
	 * @return void
	 */
	public function queue_now() {
		$this->queue_rebuild();
	}
}

/**
 * Covers the feed routing, published-file lookup and rebuild cooldown.
 *
 * @covers \SidrenaCijena\Api\PublicFeed
 */
class PublicFeedTest extends TestCase {

	/**
	 * Reset shared test state.
	 *
	 * @return void
	 */
	private function reset_state() {
		$locations       = new FeedLocationFake();
		$locations->rows = array(
			array(
				'id'   => 1,
				'type' => 'webshop',
			),
		);
		PluginSwap::swap( 'locations', $locations );
		PluginSwap::swap( 'publications', null );
		Logger::set_repository( null );

		$GLOBALS['sidrena_test_options']       = array();
		$GLOBALS['sidrena_test_query_vars']    = array();
		$GLOBALS['sidrena_test_rewrite_rules'] = array();
		$GLOBALS['sidrena_test_scheduled']     = array();
	}

	/**
	 * The feed query var is allowed through WordPress.
	 *
	 * @return void
	 */
	public function test_query_vars_exposes_feed_var() {
		$vars = ( new PublicFeed() )->query_vars( array( 'p' ) );

		$this->assertSame( array( 'p', PublicFeed::QUERY_VAR ), $vars );
	}

	/**
	 * The rewrite rule maps the stable URL stems to the feed query var.
	 *
	 * @return void
	 */
	public function test_add_rewrite_registers_feed_rule() {
		$this->reset_state();

		( new PublicFeed() )->add_rewrites();

		$this->assertCount( 1, $GLOBALS['sidrena_test_rewrite_rules'] );
		$this->assertSame( '^sidrena-cijena\.(xml|csv)$', $GLOBALS['sidrena_test_rewrite_rules'][0]['regex'] );
		$this->assertSame( 'index.php?sidrena_cijena_feed=$matches[1]', $GLOBALS['sidrena_test_rewrite_rules'][0]['query'] );
		$this->assertSame( 'top', $GLOBALS['sidrena_test_rewrite_rules'][0]['after'] );
	}

	/**
	 * The webshop location is preferred over physical shops.
	 *
	 * @return void
	 */
	public function test_public_location_prefers_webshop() {
		$this->reset_state();

		$locations       = new FeedLocationFake();
		$locations->rows = array(
			array(
				'id'   => 3,
				'type' => 'prodavaonica',
			),
			array(
				'id'   => 7,
				'type' => 'webshop',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$location = PublicFeed::public_location();

		$this->assertSame( 7, $location['id'] );
	}

	/**
	 * A shop without a webshop falls back to its first active location.
	 *
	 * @return void
	 */
	public function test_public_location_falls_back_to_first_active() {
		$this->reset_state();

		$locations       = new FeedLocationFake();
		$locations->rows = array(
			array(
				'id'   => 3,
				'type' => 'prodavaonica',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$location = PublicFeed::public_location();

		$this->assertSame( 3, $location['id'] );
	}

	/**
	 * Without locations there is nothing to publish.
	 *
	 * @return void
	 */
	public function test_public_location_returns_null_without_locations() {
		$this->reset_state();

		PluginSwap::swap( 'locations', new FeedLocationFake() );

		$this->assertNull( PublicFeed::public_location() );
	}

	/**
	 * Requests without a feed format never reach the feed handler.
	 *
	 * @return void
	 */
	public function test_serve_ignores_non_feed_requests() {
		$this->reset_state();

		$GLOBALS['sidrena_test_query_vars'] = array();

		$probe = new PublicFeedProbe();
		$probe->serve();

		$this->assertSame( array(), $probe->sent );
		$this->assertSame( 0, $probe->missing );
	}

	/**
	 * A readable published file is streamed.
	 *
	 * @return void
	 */
	public function test_serve_streams_published_file() {
		$this->reset_state();

		$file = tempnam( sys_get_temp_dir(), 'sidrena-feed-' );
		file_put_contents( $file, '<price-list/>' );

		$publications             = new FeedPublicationFake();
		$publications->latest_row = array(
			'path'     => $file,
			'filename' => 'list.xml',
			'checksum' => hash( 'sha256', '<price-list/>' ),
		);
		PluginSwap::swap( 'publications', $publications );

		$GLOBALS['sidrena_test_query_vars'] = array( PublicFeed::QUERY_VAR => 'xml' );

		$probe = new PublicFeedProbe();
		$probe->serve();

		unlink( $file );

		$this->assertSame( 'xml', $probe->sent['format'] );
		$this->assertSame( $file, $probe->sent['row']['path'] );
		$this->assertSame( 0, $probe->missing );
	}

	/**
	 * Without a published row the request reports the rebuild instead.
	 *
	 * @return void
	 */
	public function test_serve_reports_missing_without_publication() {
		$this->reset_state();

		PluginSwap::swap( 'publications', new FeedPublicationFake() );

		$GLOBALS['sidrena_test_query_vars'] = array( PublicFeed::QUERY_VAR => 'csv' );

		$probe = new PublicFeedProbe();
		$probe->serve();

		$this->assertSame( array(), $probe->sent );
		$this->assertSame( 1, $probe->missing );
	}

	/**
	 * An unreadable path behaves like a missing publication.
	 *
	 * @return void
	 */
	public function test_serve_reports_missing_when_file_unreadable() {
		$this->reset_state();

		$publications             = new FeedPublicationFake();
		$publications->latest_row = array(
			'path'     => '/nonexistent/sidrena-cijena.xml',
			'filename' => 'list.xml',
			'checksum' => 'abc123',
		);
		PluginSwap::swap( 'publications', $publications );

		$GLOBALS['sidrena_test_query_vars'] = array( PublicFeed::QUERY_VAR => 'xml' );

		$probe = new PublicFeedProbe();
		$probe->serve();

		$this->assertSame( array(), $probe->sent );
		$this->assertSame( 1, $probe->missing );
	}

	/**
	 * Verify is_valid_published_file rejects empty or corrupt files.
	 *
	 * @return void
	 */
	public function test_is_valid_published_file_rejects_empty_or_corrupt_files() {
		$feed = new PublicFeed();

		// Non-existent path.
		$this->assertFalse(
			$feed->is_valid_published_file(
				array(
					'path' => '/nonexistent/file.csv',
				)
			)
		);

		// Empty (0-byte) file.
		$tmp_empty = tempnam( sys_get_temp_dir(), 'feed_empty_' );
		$this->assertFalse(
			$feed->is_valid_published_file(
				array(
					'path'     => $tmp_empty,
					'checksum' => 'fake',
				)
			)
		);
		unlink( $tmp_empty );

		// Checksum mismatch.
		$tmp_corrupt = tempnam( sys_get_temp_dir(), 'feed_corrupt_' );
		file_put_contents( $tmp_corrupt, 'corrupt content' );
		$this->assertFalse(
			$feed->is_valid_published_file(
				array(
					'path'     => $tmp_corrupt,
					'checksum' => 'different_checksum',
				)
			)
		);

		// Valid file with correct checksum.
		$valid_checksum = hash( 'sha256', 'corrupt content' );
		$this->assertTrue(
			$feed->is_valid_published_file(
				array(
					'path'     => $tmp_corrupt,
					'checksum' => $valid_checksum,
				)
			)
		);
		unlink( $tmp_corrupt );
	}

	/**
	 * Checksum mismatch causes serve to report missing and queue rebuild.
	 *
	 * @return void
	 */
	public function test_serve_reports_missing_on_checksum_mismatch() {
		$this->reset_state();

		$tmp = tempnam( sys_get_temp_dir(), 'feed_mismatch_' );
		file_put_contents( $tmp, 'tampered content' );

		$publications             = new FeedPublicationFake();
		$publications->latest_row = array(
			'path'     => $tmp,
			'filename' => 'list.csv',
			'checksum' => 'expected_sha256_hash_that_does_not_match',
		);
		PluginSwap::swap( 'publications', $publications );

		$GLOBALS['sidrena_test_query_vars'] = array( PublicFeed::QUERY_VAR => 'csv' );

		$probe = new PublicFeedProbe();
		$probe->serve();

		unlink( $tmp );

		$this->assertSame( array(), $probe->sent );
		$this->assertSame( 1, $probe->missing );
	}

	/**
	 * Rows without a stored path are ignored.
	 *
	 * @return void
	 */
	public function test_current_file_ignores_rows_without_path() {
		$this->reset_state();

		$publications = new FeedPublicationFake();
		PluginSwap::swap( 'publications', $publications );

		$probe = new PublicFeedProbe();

		$this->assertNull( $probe->file_for( 1, 'xml' ) );

		$publications->latest_row = array( 'path' => '' );
		$this->assertNull( $probe->file_for( 1, 'xml' ) );

		$publications->latest_row = array( 'path' => '/tmp/list.xml' );
		$this->assertSame( '/tmp/list.xml', $probe->file_for( 1, 'xml' )['path'] );
	}

	/**
	 * A missing file queues exactly one rebuild per cooldown window.
	 *
	 * @return void
	 */
	public function test_queue_rebuild_schedules_once_per_cooldown() {
		$this->reset_state();

		$recorder = new FeedLogRecorder();
		Logger::set_repository( $recorder );

		$probe = new PublicFeedProbe();
		$probe->queue_now();

		$this->assertCount( 1, $GLOBALS['sidrena_test_scheduled'] );
		$this->assertSame( 'sidrena_cijena_service_regeneration', $GLOBALS['sidrena_test_scheduled'][0]['hook'] );
		$this->assertSame( array(), $GLOBALS['sidrena_test_scheduled'][0]['args'] );
		$this->assertGreaterThanOrEqual( time() + 50, $GLOBALS['sidrena_test_scheduled'][0]['timestamp'] );
		$this->assertGreaterThan( 0, (int) get_option( PublicFeed::OPTION_LAST_QUEUE, 0 ) );

		$queued = array_filter(
			$recorder->entries,
			static function ( $entry ) {
				return 'public_feed_rebuild_queued' === $entry['event'];
			}
		);
		$this->assertCount( 1, $queued );

		$probe->queue_now();
		$this->assertCount( 1, $GLOBALS['sidrena_test_scheduled'] );

		update_option( PublicFeed::OPTION_LAST_QUEUE, time() - PublicFeed::REBUILD_COOLDOWN - 1, false );
		$probe->queue_now();
		$this->assertCount( 2, $GLOBALS['sidrena_test_scheduled'] );
	}

	/**
	 * Fallback route matching resolves XML and CSV feeds when rewrites are unflushed.
	 *
	 * @return void
	 */
	public function test_parse_request_fallback_matches_xml_and_csv() {
		$this->reset_state();
		$feed = new PublicFeed();

		// 1. Matched from wp->request for XML.
		$wp             = new \stdClass();
		$wp->request    = PublicFeed::STEM . '.xml';
		$wp->query_vars = array( 'error' => '404' );
		$feed->parse_request_fallback( $wp );
		$this->assertSame( 'xml', $wp->query_vars[ PublicFeed::QUERY_VAR ] );
		$this->assertArrayNotHasKey( 'error', $wp->query_vars );

		// 2. Matched from wp->request for CSV.
		$wp             = new \stdClass();
		$wp->request    = PublicFeed::STEM . '.csv';
		$wp->query_vars = array( 'error' => '404' );
		$feed->parse_request_fallback( $wp );
		$this->assertSame( 'csv', $wp->query_vars[ PublicFeed::QUERY_VAR ] );
		$this->assertArrayNotHasKey( 'error', $wp->query_vars );

		// 3. Matched from REQUEST_URI when wp->request is empty.
		$wp                     = new \stdClass();
		$wp->request            = '';
		$wp->query_vars         = array( 'error' => '404' );
		$_SERVER['REQUEST_URI'] = '/' . PublicFeed::STEM . '.xml';
		$feed->parse_request_fallback( $wp );
		$this->assertSame( 'xml', $wp->query_vars[ PublicFeed::QUERY_VAR ] );
		$this->assertArrayNotHasKey( 'error', $wp->query_vars );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Method add_rewrites self-heals by flushing rewrite rules when the feed XML rule is missing.
	 *
	 * @return void
	 */
	public function test_add_rewrites_self_heals_when_rule_missing() {
		$this->reset_state();
		$feed = new PublicFeed();

		$GLOBALS['sidrena_test_options']['rewrite_rules'] = array(
			'^some-other-slug/?$' => 'index.php?page=other',
		);
		$GLOBALS['sidrena_test_flushed_rules']            = 0;

		$feed->add_rewrites();

		$this->assertGreaterThan( 0, $GLOBALS['sidrena_test_flushed_rules'] );
	}
}
