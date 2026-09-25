<?php
/**
 * Integration tests for the anchor import pipeline.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Import\CsvParser;
use SidrenaCijena\Import\ImportService;
use SidrenaCijena\Import\RowValidator;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;

/**
 * Anchor service that fails for one SKU to exercise rollback.
 */
class FailingAnchorService extends AnchorPriceService {

	/**
	 * Record an anchor unless the SKU is marked as failing.
	 *
	 * @param \SidrenaCijena\Catalog\CatalogItem $item Item.
	 * @param mixed                              $price Price.
	 * @param array<mixed>                       $args Arguments.
	 * @return int|\WP_Error
	 */
	public function record( $item, $price, array $args = array() ) {
		if ( 'FAIL-1' === $item->sku ) {
			return new \WP_Error( 'anchor_record_failed', 'Simulated storage failure.' );
		}

		return parent::record( $item, $price, $args );
	}
}

/**
 * Covers preview, dry run, atomic commit, rollback and error reporting.
 */
class Test_Import extends WP_UnitTestCase {

	/**
	 * History repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Import service.
	 *
	 * @var ImportService
	 */
	protected $service;

	/**
	 * Temporary CSV files created by a test.
	 *
	 * @var string[]
	 */
	protected $files = array();

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$wpdb = $GLOBALS['wpdb'];

		foreach ( array( 'sidrena_price_history', 'sidrena_locations', 'sidrena_special_sales', 'sidrena_publications' ) as $suffix ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . $suffix ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$locations = new LocationRepository();
		$locations->insert(
			array(
				'name'       => 'Prodavaonica Vlaška 68',
				'type'       => 'prodavaonica',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
				'active'     => 1,
			)
		);

		$this->history = new PriceHistoryRepository();
		$this->service = new ImportService( new CsvParser(), new RowValidator( Plugin::instance()->catalog(), $this->history ), Plugin::instance()->anchors() );

		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );
		$this->create_item( 'Čaj 100 g', 'CAJ-100', 4.99 );
	}

	/**
	 * Remove temporary files.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		$this->files = array();

		parent::tear_down();
	}

	/**
	 * Create a catalog item.
	 *
	 * @param string $name  Name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price.
	 * @return int
	 */
	protected function create_item( $name, $sku, $price ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );

		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		return $post_id;
	}

	/**
	 * Write a temporary CSV file.
	 *
	 * @param string $contents CSV contents.
	 * @return string Path.
	 */
	protected function csv_file( $contents ) {
		$path = tempnam( sys_get_temp_dir(), 'sidrena-import-' ) . '.csv';

		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->files[] = $path;

		return $path;
	}

	/**
	 * Preview validates rows without writing anything.
	 *
	 * @return void
	 */
	public function test_preview_validates_without_writing() {
		$path    = $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\nNEPOZNAT;2026-09-10;3,00\n" );
		$preview = $this->service->preview( $path );

		$this->assertIsArray( $preview );
		$this->assertSame( 1, $preview['summary']['valid'] );
		$this->assertSame( 1, $preview['summary']['invalid'] );
		$this->assertSame( 0, $this->history->count() );
	}

	/**
	 * Dry run reports what would change without writing.
	 *
	 * @return void
	 */
	public function test_dry_run_changes_nothing() {
		$path   = $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\n" );
		$report = $this->service->commit( $path, array( 'dry_run' => true ) );

		$this->assertIsArray( $report );
		$this->assertTrue( $report['dry_run'] );
		$this->assertSame( 1, $report['inserted'] );
		$this->assertSame( 0, $report['failed'] );
		$this->assertSame( 0, $this->history->count() );
	}

	/**
	 * Commit writes anchor rows sourced from the import.
	 *
	 * @return void
	 */
	public function test_commit_inserts_anchor_records() {
		$path   = $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\nCAJ-100;2025-05-02;4,49\n" );
		$report = $this->service->commit( $path );

		$this->assertIsArray( $report );
		$this->assertSame( 2, $report['inserted'] );
		$this->assertSame( 0, $report['failed'] );
		$this->assertSame( 2, $this->history->count() );

		$rows = $this->history->query(
			array(
				'price_type' => 'anchor',
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);

		$this->assertSame( '7.490000', $rows[0]['price'] );
		$this->assertSame( '2026-09-10', $rows[0]['reference_date'] );
		$this->assertSame( 'import', $rows[0]['source'] );
		$this->assertSame( '2025-05-02', $rows[1]['reference_date'] );
	}

	/**
	 * Overwriting existing anchors requires explicit confirmation.
	 *
	 * @return void
	 */
	public function test_overwrite_requires_confirmation() {
		$this->service->commit( $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;5,00\n" ) );
		$this->assertSame( 1, $this->history->count() );

		$path   = $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\n" );
		$result = $this->service->commit( $path );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'overwrite_confirmation_required', $result->get_error_code() );
		$this->assertSame( 1, $this->history->count() );

		$report = $this->service->commit( $path, array( 'confirm_overwrite' => true ) );

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['overwritten'] );
		$this->assertSame( 2, $this->history->count() );

		$rows = $this->history->query(
			array(
				'price_type' => 'anchor',
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);

		$this->assertSame( '5.000000', $rows[0]['price'] );
		$this->assertSame( '7.490000', $rows[1]['price'] );
		$this->assertSame( $rows[0]['id'], $rows[1]['correction_of'] );
	}

	/**
	 * A storage failure rolls the whole batch back.
	 *
	 * @return void
	 */
	public function test_failed_import_rolls_back() {
		$this->create_item( 'Neispravan proizvod', 'FAIL-1', 1.00 );

		$service = new ImportService( new CsvParser(), new RowValidator( Plugin::instance()->catalog(), $this->history ), new FailingAnchorService( $this->history ) );

		$path   = $this->csv_file( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\nFAIL-1;2026-09-10;1,00\n" );
		$result = $service->commit( $path );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'import_rolled_back', $result->get_error_code() );
		$this->assertSame( 0, $this->history->count() );
	}

	/**
	 * The downloadable error report contains the row-level findings.
	 *
	 * @return void
	 */
	public function test_error_report_lists_findings() {
		$path   = $this->csv_file( "sku;reference_date;anchor_price\nNEPOZNAT;2026-09-10;3,00\nKAV-250;2026-09-10;7,49\n" );
		$report = $this->service->commit( $path );

		$this->assertIsArray( $report );
		$this->assertNotEmpty( $report['errors'] );

		$csv = $this->service->report_csv( $report );

		$this->assertStringContainsString( 'row;code;message;sku;product_id;reference_date;price', $csv );
		$this->assertStringContainsString( 'unknown_product', $csv );
		$this->assertSame( 1, $this->history->count() );
	}

	/**
	 * The parser rejects paths outside the allowed directories.
	 *
	 * @return void
	 */
	public function test_parser_rejects_path_traversal() {
		$parser = new CsvParser();
		$result = $parser->parse_file( '/etc/passwd' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}
}
