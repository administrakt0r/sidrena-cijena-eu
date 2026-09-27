<?php
/**
 * Migration detector unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Import
 */

namespace SidrenaCijena\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Import\MigrationDetector;

/**
 * Test wpdb double for migration detection.
 */
class MigrationDetectorTestWpdb extends \wpdb {

	/**
	 * Tables returned by SHOW TABLES.
	 *
	 * @var array<int,string>
	 */
	public $mock_tables = array();

	/**
	 * Postmeta table name.
	 *
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';

	/**
	 * Row counts keyed by query.
	 *
	 * @var array<string,int>
	 */
	public $mock_counts = array();

	/**
	 * Query log.
	 *
	 * @var array<int,string>
	 */
	public $queries = array();

	/**
	 * Stub get_col.
	 *
	 * @param string $query Query.
	 * @param int    $x     Column offset.
	 * @return array<int,string>
	 */
	public function get_col( $query = '', $x = 0 ) {
		$this->queries[] = (string) $query;

		return $this->mock_tables;
	}

	/**
	 * Stub get_var.
	 *
	 * @param string|null $query Query.
	 * @param int         $x     Column offset.
	 * @param int         $y     Row offset.
	 * @return mixed
	 */
	public function get_var( $query = null, $x = 0, $y = 0 ) {
		$query           = (string) $query;
		$this->queries[] = $query;

		foreach ( $this->mock_counts as $pattern => $count ) {
			if ( false !== strpos( $query, $pattern ) ) {
				return $count;
			}
		}

		return 0;
	}
}

/**
 * @covers \SidrenaCijena\Import\MigrationDetector
 */
class MigrationDetectorTest extends TestCase {

	/**
	 * Original wpdb instance.
	 *
	 * @var \wpdb|null
	 */
	private $original_wpdb;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_wpdb                = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->original_wpdb ) {
			$GLOBALS['wpdb'] = $this->original_wpdb;
		}

		unset( $GLOBALS['sidrena_test_options'], $GLOBALS['sidrena_test_transients'] );

		parent::tearDown();
	}

	/**
	 * Verify known meta keys.
	 *
	 * @return void
	 */
	public function test_known_meta_keys() {
		$keys = MigrationDetector::known_meta_keys();

		$this->assertArrayHasKey( '_price_history', $keys );
		$this->assertArrayHasKey( '_wc_price_history', $keys );
		$this->assertArrayHasKey( '_wc_price_changes', $keys );
	}

	/**
	 * Verify known options.
	 *
	 * @return void
	 */
	public function test_known_options() {
		$options = MigrationDetector::known_options();

		$this->assertArrayHasKey( 'woocommerce_price_history_version', $options );
		$this->assertArrayHasKey( 'wc_price_history_version', $options );
		$this->assertArrayHasKey( 'price_history_db_version', $options );
	}

	/**
	 * Verify plain-language guidance strings.
	 *
	 * @return void
	 */
	public function test_guidance() {
		$guidance = MigrationDetector::guidance();

		$this->assertCount( 4, $guidance );
		$this->assertStringContainsString( 'CSV', $guidance[0] );
	}

	/**
	 * Detect detects plugin own history and ignores sidrena-prefixed tables.
	 *
	 * @return void
	 */
	public function test_detect_default_sources() {
		$wpdb              = new MigrationDetectorTestWpdb();
		$wpdb->prefix      = 'wp_';
		$wpdb->postmeta    = 'wp_postmeta';
		$wpdb->mock_tables = array(
			'wp_sidrena_price_history',
			'wp_sidrena_locations',
			'wp_posts',
		);
		$wpdb->mock_counts = array(
			'sidrena_price_history' => 42,
		);
		$GLOBALS['wpdb']   = $wpdb;

		$sources = MigrationDetector::detect();

		$this->assertNotEmpty( $sources );
		$this->assertSame( 'sidrena_history', $sources[0]['id'] );
		$this->assertSame( 42, $sources[0]['rows'] );
		$this->assertFalse( $sources[0]['migration'] );
		$this->assertCount( 1, $sources );
	}

	/**
	 * Detect finds third-party price history tables.
	 *
	 * @return void
	 */
	public function test_detect_finds_third_party_table() {
		$wpdb              = new MigrationDetectorTestWpdb();
		$wpdb->prefix      = 'wp_';
		$wpdb->postmeta    = 'wp_postmeta';
		$wpdb->mock_tables = array(
			'wp_legacy_price_history',
			'wp_custom_price_log_archive',
		);
		$wpdb->mock_counts = array(
			'wp_legacy_price_history'     => 150,
			'wp_custom_price_log_archive' => 20,
		);
		$GLOBALS['wpdb']   = $wpdb;

		$sources = MigrationDetector::detect();
		$ids     = array_column( $sources, 'id' );

		$this->assertContains( 'table:wp_legacy_price_history', $ids );
		$this->assertContains( 'table:wp_custom_price_log_archive', $ids );

		foreach ( $sources as $source ) {
			if ( 'table:wp_legacy_price_history' === $source['id'] ) {
				$this->assertSame( 150, $source['rows'] );
				$this->assertTrue( $source['migration'] );
			}
		}
	}

	/**
	 * Detect finds known third-party plugin options and meta keys.
	 *
	 * @return void
	 */
	public function test_detect_finds_options_and_meta_keys() {
		update_option( 'woocommerce_price_history_version', '1.2.3', false );

		$wpdb              = new MigrationDetectorTestWpdb();
		$wpdb->prefix      = 'wp_';
		$wpdb->postmeta    = 'wp_postmeta';
		$wpdb->mock_tables = array();
		$wpdb->mock_counts = array(
			'_wc_price_history' => 88,
		);
		$GLOBALS['wpdb']   = $wpdb;

		$sources = MigrationDetector::detect();
		$ids     = array_column( $sources, 'id' );

		$this->assertContains( 'option:woocommerce_price_history_version', $ids );
		$this->assertContains( 'meta:_wc_price_history', $ids );

		foreach ( $sources as $source ) {
			if ( 'meta:_wc_price_history' === $source['id'] ) {
				$this->assertSame( 88, $source['rows'] );
				$this->assertTrue( $source['migration'] );
			}
		}
	}
}
