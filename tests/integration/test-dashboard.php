<?php
/**
 * Integration tests for the compliance dashboard and report.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Admin\DashboardPage;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Compliance\ComplianceReport;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Plugin;

/**
 * Dashboard aggregation and report generation.
 */
class Test_Dashboard extends WP_UnitTestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Price history repository.
	 *
	 * @var \SidrenaCijena\Database\PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

	/**
	 * Prepare the standalone catalog and a single location.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();

		$this->plugin = Plugin::instance();
		$this->plugin->set_catalog( new StandaloneCatalogProvider() );
		$this->history   = $this->plugin->history();
		$this->locations = $this->plugin->locations();

		$GLOBALS['wpdb']->query( 'TRUNCATE TABLE ' . $this->history->table() );
		$GLOBALS['wpdb']->query( 'TRUNCATE TABLE ' . $this->locations->table() );
		$GLOBALS['wpdb']->query( 'TRUNCATE TABLE ' . $this->plugin->special_sales()->table() );
		$GLOBALS['wpdb']->query( 'TRUNCATE TABLE ' . $this->plugin->publications()->table() );

		$this->locations->insert(
			array(
				'name'       => 'Prodavaonica Vlaška 68',
				'type'       => 'prodavaonica',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
				'active'     => 1,
			)
		);
	}

	/**
	 * Create a standalone catalog item.
	 *
	 * @param string              $name  Item name.
	 * @param string              $sku   SKU.
	 * @param float               $price Regular price.
	 * @param array<string,mixed> $meta  Extra meta.
	 * @return int
	 */
	protected function create_item( $name, $sku, $price, array $meta = array() ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		return $post_id;
	}

	/**
	 * Aggregates anchor states, comparisons and findings.
	 *
	 * @return void
	 */
	public function test_aggregate_reports_anchor_states_and_findings() {
		$valid   = $this->create_item( 'Kava mljevena 250 g', 'KAV-250', 20.0 );
		$missing = $this->create_item( 'Čaj 100 g', 'CAJ-100', 5.0 );
		$stale   = $this->create_item( 'Sok 1 l', 'SOK-100', 3.0 );

		$anchors = $this->plugin->anchors();

		$anchors->record( $this->plugin->catalog()->get_item( $valid ), 25.0, array( 'reference_date' => '2026-09-10' ) );
		$anchors->record( $this->plugin->catalog()->get_item( $stale ), 4.0, array( 'reference_date' => '2025-05-02' ) );

		$data = DashboardPage::aggregate( 100 );

		$this->assertSame( 3, $data['total'] );
		$this->assertSame( 3, $data['scanned'] );
		$this->assertSame( 1, $data['anchor_states']['valid'] );
		$this->assertSame( 1, $data['anchor_states']['missing'] );
		$this->assertSame( 1, $data['anchor_states']['needs_verification'] );
		$this->assertSame( 2, $data['comparison']['below'] );
		$this->assertSame( 1, $data['comparison']['unknown'] );
		$this->assertSame( 3, $data['missing_history'] );
		$this->assertSame( 0, $data['failed_publications'] );

		$codes = array();

		foreach ( $data['findings'] as $finding ) {
			$codes[] = $finding->code;
		}

		$this->assertContains( 'missing_anchor_price', $codes );
		$this->assertContains( 'anchor_needs_verification', $codes );
		$this->assertGreaterThanOrEqual( 1, $data['counts']['total'] );
		$this->assertSame( 1, $data['counts'][ \SidrenaCijena\Compliance\Finding::ERROR ] );
	}

	/**
	 * Unclassified promotions and 30-day warnings are surfaced.
	 *
	 * @return void
	 */
	public function test_aggregate_reports_sale_warnings() {
		$promo      = $this->create_item( 'Krema 50 ml', 'KRE-50', 10.0, array( '_sidrena_sale_price' => 8.0 ) );
		$classified = $this->create_item( 'Gel 200 ml', 'GEL-200', 6.0, array( '_sidrena_sale_price' => 4.0 ) );

		$this->plugin->special_sales()->insert(
			array(
				'product_id'  => $classified,
				'sale_type'   => 'akcija',
				'sale_name'   => 'Akcija',
				'start_date'  => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ),
				'promo_price' => 4.0,
			)
		);

		$data = DashboardPage::aggregate( 100 );

		$this->assertSame( 1, $data['unclassified_promotions'] );
		$this->assertSame( 1, $data['thirty_day_warnings'] );
		$this->assertSame( 2, $data['comparison']['unknown'] );

		$codes = array();

		foreach ( $data['findings'] as $finding ) {
			$codes[] = $finding->code;
		}

		$this->assertContains( 'missing_sale_type', $codes );
		$this->assertContains( 'missing_30_day_price', $codes );
		$this->assertContains( 'missing_anchor_price', $codes );
	}

	/**
	 * Findings convert into a complete compliance report.
	 *
	 * @return void
	 */
	public function test_report_from_aggregate_is_exportable() {
		$this->create_item( 'Bez sidrene cijene', 'NO-ANCHOR', 9.0 );

		$data   = DashboardPage::aggregate( 100 );
		$report = ComplianceReport::from_result( $data );

		$this->assertSame( $data['counts']['total'], $report->counts()['total'] );
		$this->assertNotEmpty( $report->group_by_entity() );

		$csv = $report->to_csv();

		$this->assertStringContainsString( 'severity;code;entity_type;entity_id;message;rule_reference', $csv );
		$this->assertStringContainsString( 'missing_anchor_price', $csv );

		$json = json_decode( $report->to_json(), true );

		$this->assertIsArray( $json );
		$this->assertArrayHasKey( 'counts', $json );
		$this->assertArrayHasKey( 'findings', $json );
		$this->assertSame( '2026-10-01', $json['rule_version'] );
	}

	/**
	 * Cleanup removes the legacy state used by the report.
	 *
	 * @return void
	 */
	public function test_report_meta_contains_rule_version() {
		$data   = DashboardPage::aggregate( 100 );
		$report = ComplianceReport::from_result( $data );

		$this->assertSame( '2026-10-01', $report->meta()['rule_version'] );
	}
}
