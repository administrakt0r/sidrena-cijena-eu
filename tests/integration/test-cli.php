<?php
/**
 * Integration tests for the WP-CLI commands and their exit codes.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Cli\CleanupCommand;
use SidrenaCijena\Cli\Cli;
use SidrenaCijena\Cli\GenerateCommand;
use SidrenaCijena\Cli\HistoryCommand;
use SidrenaCijena\Cli\ImportCommand;
use SidrenaCijena\Cli\SeedTestDataCommand;
use SidrenaCijena\Cli\StatusCommand;
use SidrenaCijena\Cli\ValidateCommand;
use SidrenaCijena\Plugin;

/**
 * Exercises every CLI command through its testable execute() method.
 */
class Test_Cli extends WP_UnitTestCase {

	/**
	 * Prepare the catalog and a default location.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		Cli::reset_buffer();
		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );
		Plugin::instance()->locations()->ensure_default_webshop();
	}

	/**
	 * Create a standalone item.
	 *
	 * @param string $name  Name.
	 * @param string $sku   SKU.
	 * @param string $price Regular price.
	 * @param string $extra Extra meta: barcode.
	 * @return int Post ID.
	 */
	protected function create_item( $name, $sku, $price, $extra = '' ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );

		if ( '' !== $extra ) {
			update_post_meta( $post_id, '_sidrena_barcode', $extra );
		}

		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		return $post_id;
	}

	/**
	 * Record an anchor for an item.
	 *
	 * @param int   $post_id Post ID.
	 * @param float $price   Anchor price.
	 * @return void
	 */
	protected function record_anchor( $post_id, $price ) {
		$item = Plugin::instance()->catalog()->get_item( $post_id );

		Plugin::instance()->anchors()->record( $item, $price, array( 'source' => 'cli' ) );
	}

	/**
	 * Buffered output as plain text.
	 *
	 * @return string
	 */
	protected function output() {
		$lines = array();

		foreach ( Cli::buffer() as $entry ) {
			$lines[] = $entry[1];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Status always exits 0 and reports schema, rules and scheduler.
	 *
	 * @return void
	 */
	public function test_status_returns_zero_and_reports() {
		$this->assertSame( 0, StatusCommand::execute() );
		$this->assertStringContainsString( 'Schema:', $this->output() );
		$this->assertStringContainsString( 'Scheduler:', $this->output() );

		Cli::reset_buffer();
		$this->assertSame( 0, StatusCommand::execute( array(), array( 'format' => 'json' ) ) );

		$data = json_decode( $this->output(), true );

		$this->assertSame( '1.0.0', $data['schema_version'] );
		$this->assertNotEmpty( $data['locations'] );
	}

	/**
	 * A catalog with a missing anchor exits 1.
	 *
	 * @return void
	 */
	public function test_validate_missing_anchor_returns_one() {
		$this->create_item( 'CLI orphan', 'CLI-ORPHAN', '9.99', '3850000000011' );

		$this->assertSame( 1, ValidateCommand::execute() );
		$this->assertStringContainsString( 'error(s)', $this->output() );
	}

	/**
	 * A compliant catalog with fresh publications validates cleanly.
	 *
	 * @return void
	 */
	public function test_validate_clean_returns_zero_after_generation() {
		$post_id = $this->create_item( 'CLI clean', 'CLI-CLEAN', '19.99', '3850000000022' );
		$this->record_anchor( $post_id, 19.99 );

		$this->assertSame( 0, GenerateCommand::execute() );
		$this->assertSame( 0, ValidateCommand::execute() );
	}

	/**
	 * An unknown --format exits 3.
	 *
	 * @return void
	 */
	public function test_validate_invalid_format_returns_three() {
		$this->assertSame( 3, ValidateCommand::execute( array(), array( 'format' => 'pdf' ) ) );
	}

	/**
	 * Dry-run generation writes nothing.
	 *
	 * @return void
	 */
	public function test_generate_dry_run_writes_nothing() {
		$post_id = $this->create_item( 'CLI dry', 'CLI-DRY', '7.99', '3850000000033' );
		$this->record_anchor( $post_id, 7.99 );

		$this->assertSame( 0, GenerateCommand::execute( array(), array( 'dry-run' => true ) ) );
		$this->assertStringContainsString( 'would be published', $this->output() );
		$this->assertNull( Plugin::instance()->publications()->last_successful() );
	}

	/**
	 * Real generation publishes files and is idempotent without --force.
	 *
	 * @return void
	 */
	public function test_generate_publishes_and_skips_when_unchanged() {
		$post_id = $this->create_item( 'CLI gen', 'CLI-GEN', '6.99', '3850000000044' );
		$this->record_anchor( $post_id, 6.99 );

		$this->assertSame( 0, GenerateCommand::execute() );

		$last = Plugin::instance()->publications()->last_successful();

		$this->assertNotEmpty( $last );
		$this->assertFileExists( $last['path'] );

		Cli::reset_buffer();
		$this->assertSame( 0, GenerateCommand::execute() );
		$this->assertStringContainsString( 'skipped', $this->output() );
	}

	/**
	 * History prints rows for a known product and exits 1 for unknown ones.
	 *
	 * @return void
	 */
	public function test_history_reports_rows() {
		$post_id = $this->create_item( 'CLI hist', 'CLI-HIST', '4.99', '3850000000055' );
		$this->record_anchor( $post_id, 4.99 );

		$this->assertSame( 0, HistoryCommand::execute( array( $post_id ) ) );
		$this->assertStringContainsString( 'anchor', $this->output() );
		$this->assertSame( 1, HistoryCommand::execute( array( 999999999 ) ) );
		$this->assertSame( 1, HistoryCommand::execute( array() ) );
	}

	/**
	 * Import dry-run, commit, overwrite protection and missing files.
	 *
	 * @return void
	 */
	public function test_import_flow_and_exit_codes() {
		$this->create_item( 'CLI import one', 'CLI-IMP-1', '9.99', '3850000000066' );
		$this->create_item( 'CLI import two', 'CLI-IMP-2', '5.99', '3850000000077' );

		$file = sys_get_temp_dir() . '/sidrena-cli-import.csv';
		file_put_contents( $file, "sku;anchor_price;reference_date\nCLI-IMP-1;10,99;2026-09-10\nCLI-IMP-2;6,49;2026-09-10\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.

		$this->assertSame( 0, ImportCommand::execute( array( $file ), array( 'dry-run' => true ) ) );
		$this->assertStringContainsString( 'Dry run', $this->output() );

		Cli::reset_buffer();
		$this->assertSame( 0, ImportCommand::execute( array( $file ) ) );
		$this->assertStringContainsString( '2 inserted', $this->output() );

		$this->assertSame( 1, ImportCommand::execute( array( $file ) ) );
		$this->assertSame( 0, ImportCommand::execute( array( $file ), array( 'confirm-overwrite' => true ) ) );
		$this->assertSame( 3, ImportCommand::execute( array( '/no/such/file.csv' ) ) );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
	}

	/**
	 * Cleanup dry-run exits 0 without expiring anything.
	 *
	 * @return void
	 */
	public function test_cleanup_dry_run() {
		$this->assertSame( 0, CleanupCommand::execute( array(), array( 'dry-run' => true ) ) );
		$this->assertStringContainsString( 'would expire', $this->output() );
		$this->assertSame( 0, CleanupCommand::execute() );
	}

	/**
	 * Seeding refuses to run without confirmation.
	 *
	 * @return void
	 */
	public function test_seed_refuses_without_confirmation() {
		$this->assertSame( 3, SeedTestDataCommand::execute() );
		$this->assertSame(
			3,
			SeedTestDataCommand::execute(
				array(),
				array(
					'scenario' => 'nope',
					'yes'      => true,
				)
			)
		);
	}

	/**
	 * Seeding creates the requested scenario with confirmation.
	 *
	 * @return void
	 */
	public function test_seed_creates_scenario() {
		$this->assertSame(
			0,
			SeedTestDataCommand::execute(
				array(),
				array(
					'scenario' => 'missing-anchor',
					'yes'      => true,
				)
			)
		);

		$posts = get_posts(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'meta_key'    => '_sidrena_sku', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Test lookup.
				'meta_value'  => 'SEED-MISSING_ANCHOR', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Test lookup.
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		$this->assertNotEmpty( $posts );
	}
}
