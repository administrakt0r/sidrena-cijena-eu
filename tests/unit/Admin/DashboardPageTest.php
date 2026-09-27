<?php
/**
 * Dashboard aggregate batching and classification tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\DashboardPage;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Tests\Support\PluginSwap;

/**
 * In-memory catalog provider.
 */
class DashboardCatalogFake implements CatalogProvider {

	/**
	 * Items.
	 *
	 * @var array<int,CatalogItem>
	 */
	public $items = array();

	/**
	 * Source identifier.
	 *
	 * @return string
	 */
	public function get_source() {
		return 'fake';
	}

	/**
	 * Availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Single item.
	 *
	 * @param int $id Item ID.
	 * @return CatalogItem|null
	 */
	public function get_item( $id ) {
		return isset( $this->items[ $id ] ) ? $this->items[ $id ] : null;
	}

	/**
	 * Variation.
	 *
	 * @param int $parent_id    Parent.
	 * @param int $variation_id Variation.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		unset( $parent_id, $variation_id );

		return null;
	}

	/**
	 * Items.
	 *
	 * @param array $args Query args.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		unset( $args );

		return array_values( $this->items );
	}

	/**
	 * Count.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		unset( $args );

		return count( $this->items );
	}

	/**
	 * Iterate.
	 *
	 * @param array $args Query args.
	 * @return \Generator<CatalogItem>
	 */
	public function iterate( array $args = array() ) {
		unset( $args );

		foreach ( $this->items as $item ) {
			yield $item;
		}
	}

	/**
	 * Categories.
	 *
	 * @return array<int,array{id:int,slug:string,name:string}>
	 */
	public function get_categories() {
		return array();
	}

	/**
	 * Item categories.
	 *
	 * @param int $id Item ID.
	 * @return string[]
	 */
	public function get_item_categories( $id ) {
		unset( $id );

		return array();
	}
}

/**
 * History repository double counting batched lookups.
 */
class DashboardHistoryFake extends PriceHistoryRepository {

	/**
	 * Number of `latest_map()` calls.
	 *
	 * @var int
	 */
	public $latest_map_calls = 0;

	/**
	 * Anchor rows keyed by `product:variation`.
	 *
	 * @var array<string,array>
	 */
	public $anchors = array();

	/**
	 * Regular price rows keyed by `product:variation`.
	 *
	 * @var array<string,array>
	 */
	public $regular = array();

	/**
	 * Batched latest lookup.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,array>
	 */
	public function latest_map( array $product_ids, $price_type ) {
		++$this->latest_map_calls;

		$ids    = array_map( 'intval', $product_ids );
		$source = 'anchor' === $price_type ? $this->anchors : $this->regular;
		$map    = array();

		foreach ( $source as $key => $row ) {
			$parts = explode( ':', (string) $key );

			if ( in_array( (int) $parts[0], $ids, true ) ) {
				$map[ $key ] = $row;
			}
		}

		return $map;
	}

	/**
	 * Per-item lookup, expected to remain unused.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		unset( $product_id, $variation_id, $price_type );

		return null;
	}
}

/**
 * Sale repository double counting batched lookups.
 */
class DashboardSalesFake extends SpecialSaleRepository {

	/**
	 * Number of `active_map()` calls.
	 *
	 * @var int
	 */
	public $active_map_calls = 0;

	/**
	 * Active sale rows keyed by `product:variation`.
	 *
	 * @var array<string,array>
	 */
	public $sales = array();

	/**
	 * Batched active sale lookup.
	 *
	 * @param string $at Moment.
	 * @return array<string,array>
	 */
	public function active_map( $at = '' ) {
		unset( $at );

		++$this->active_map_calls;

		return $this->sales;
	}
}

/**
 * Validator double returning no findings.
 */
class DashboardValidatorFake extends Validator {

	/**
	 * Findings to return.
	 *
	 * @var array<mixed>
	 */
	public $findings = array();

	/**
	 * Skip the production constructor.
	 */
	public function __construct() {
	}

	/**
	 * Item findings.
	 *
	 * @param array $items Items.
	 * @param array $args  Arguments.
	 * @return array
	 */
	public function validate_items( array $items, array $args = array() ) {
		unset( $items, $args );

		return $this->findings;
	}

	/**
	 * No global findings.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function validate_global( array $args = array() ) {
		unset( $args );

		return array();
	}
}

/**
 * Publication repository double.
 */
class DashboardPublicationsFake {

	/**
	 * Recent publications.
	 *
	 * @param array $args Arguments.
	 * @return array{items:array<int,array<mixed>>}
	 */
	public function index( array $args = array() ) {
		unset( $args );

		return array( 'items' => array() );
	}

	/**
	 * Status counts.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		return array( 'failed' => 0 );
	}

	/**
	 * Last successful publication.
	 *
	 * @return array|null
	 */
	public function last_successful() {
		return null;
	}
}

/**
 * Covers aggregate query batching and per-item classification.
 *
 * @covers \SidrenaCijena\Admin\DashboardPage::aggregate
 */
class DashboardPageTest extends TestCase {

	/**
	 * Fakes wired into the plugin container.
	 *
	 * @var array<string,mixed>
	 */
	private $services = array();

	/**
	 * Reset option state and swap plugin services.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
		Plugin::instance()->rest_cache()->flush();

		$this->services = array(
			'catalog'       => new DashboardCatalogFake(),
			'history'       => new DashboardHistoryFake(),
			'special_sales' => new DashboardSalesFake(),
			'validator'     => new DashboardValidatorFake(),
			'publications'  => new DashboardPublicationsFake(),
		);

		foreach ( $this->services as $name => $service ) {
			PluginSwap::swap( $name, $service );
		}
	}

	/**
	 * Remove the swapped services.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Plugin::instance()->rest_cache()->flush();

		foreach ( array_keys( $this->services ) as $name ) {
			PluginSwap::swap( $name, null );
		}

		$this->services = array();

		parent::tearDown();
	}

	/**
	 * Aggregates query anchors, regular prices and sales once each.
	 *
	 * @return void
	 */
	public function test_aggregate_batches_pricing_queries() {
		$catalog = $this->services['catalog'];
		$history = $this->services['history'];
		$sales   = $this->services['special_sales'];

		$catalog->items[101] = new CatalogItem(
			array(
				'id'            => 101,
				'name'          => 'Kava 250g',
				'sku'           => 'KAV-250',
				'regular_price' => '10.00',
				'current_price' => '12.00',
			)
		);
		$catalog->items[201] = new CatalogItem(
			array(
				'id'            => 201,
				'variation_id'  => 11,
				'parent_id'     => 201,
				'name'          => 'Kava 250g – mljevena',
				'sku'           => 'KAV-250-M',
				'regular_price' => '10.00',
				'sale_price'    => '8.00',
			)
		);
		$catalog->items[202] = new CatalogItem(
			array(
				'id'            => 202,
				'variation_id'  => 12,
				'parent_id'     => 202,
				'name'          => 'Kava 250g – zrno',
				'sku'           => 'KAV-250-Z',
				'regular_price' => '10.00',
				'sale_price'    => '8.00',
			)
		);
		$catalog->items[103] = new CatalogItem(
			array(
				'id'            => 103,
				'name'          => 'Čaj 100g',
				'sku'           => 'CAJ-100',
				'regular_price' => '10.00',
				'sale_price'    => '5.00',
			)
		);

		$history->anchors = array(
			'101:0' => array(
				'price'          => '10.00',
				'reference_date' => '2026-09-10',
			),
		);

		$sales->sales = array(
			'201:0' => array( 'lowest_30_day_price' => '7.00' ),
			'202:0' => array( 'lowest_30_day_price' => '' ),
		);

		$data = DashboardPage::aggregate();

		$this->assertSame( 4, $data['total'] );
		$this->assertSame( 4, $data['scanned'] );
		$this->assertSame( 2, $history->latest_map_calls, 'Anchors and regular prices must each use one batched query.' );
		$this->assertSame( 1, $sales->active_map_calls, 'Sales must be resolved with one batched query.' );

		$this->assertSame(
			array(
				'valid'              => 1,
				'missing'            => 3,
				'needs_verification' => 0,
			),
			$data['anchor_states']
		);

		$this->assertSame(
			array(
				'below'   => 0,
				'equal'   => 0,
				'above'   => 1,
				'unknown' => 3,
			),
			$data['comparison']
		);

		// Parent sales do not classify variations 201/202. Each variation, 101,
		// and 103 therefore lacks its own regular or special-sale history.
		$this->assertSame( 4, $data['missing_history'] );
		$this->assertSame( 0, $data['thirty_day_warnings'] );
		$this->assertSame( 3, $data['unclassified_promotions'] );
	}

	/**
	 * An empty catalog does not hit the pricing tables.
	 *
	 * @return void
	 */
	public function test_aggregate_skips_pricing_queries_for_empty_catalog() {
		$history = $this->services['history'];
		$sales   = $this->services['special_sales'];

		$data = DashboardPage::aggregate();

		$this->assertSame( 0, $data['total'] );
		$this->assertSame( 0, $data['scanned'] );
		$this->assertSame( 0, $history->latest_map_calls );
		$this->assertSame( 0, $sales->active_map_calls );
	}

	/**
	 * Aggregate caches results on clean catalogs and reuses them on repeat calls.
	 *
	 * @return void
	 */
	public function test_aggregate_caches_clean_runs() {
		$catalog = $this->services['catalog'];
		$history = $this->services['history'];

		$catalog->items[10] = new CatalogItem(
			array(
				'id'            => 10,
				'name'          => 'Kruh',
				'sku'           => 'KRU-1',
				'regular_price' => '2.00',
			)
		);

		$history->anchors = array(
			'10:0' => array(
				'price'          => '2.00',
				'reference_date' => '2026-09-10',
				'created_at'     => '2026-09-10 08:00:00',
			),
		);

		$history->regular = array(
			'10:0' => array(
				'price' => '2.00',
				'date'  => '2026-09-10',
			),
		);

		$first = DashboardPage::aggregate();
		$this->assertSame( 1, $first['total'] );
		$this->assertSame( 2, $history->latest_map_calls );

		$second = DashboardPage::aggregate();
		$this->assertSame( 1, $second['total'] );
		$this->assertSame( 2, $history->latest_map_calls, 'Cached aggregate must not repeat latest_map pricing queries.' );

		// Force bypass should re-run.
		$third = DashboardPage::aggregate( DashboardPage::SCAN_LIMIT, true );
		$this->assertSame( 1, $third['total'] );
		$this->assertSame( 4, $history->latest_map_calls, 'Force bypass must re-run pricing queries.' );
	}

	/**
	 * Compliance errors are never cached so that legal issues are never masked.
	 *
	 * @return void
	 */
	public function test_aggregate_never_caches_when_compliance_errors_exist() {
		$catalog   = $this->services['catalog'];
		$history   = $this->services['history'];
		$validator = $this->services['validator'];

		// Item without anchor price generates compliance ERROR.
		$catalog->items[20] = new CatalogItem(
			array(
				'id'            => 20,
				'name'          => 'Mlijeko',
				'sku'           => 'MLI-1',
				'regular_price' => '1.50',
			)
		);

		$validator->findings = array(
			new \SidrenaCijena\Compliance\Finding(
				\SidrenaCijena\Compliance\Finding::ERROR,
				'missing_anchor',
				'Anchor is missing.'
			),
		);

		$first = DashboardPage::aggregate();
		$this->assertNotEmpty( $first['findings'] );
		$this->assertGreaterThan( 0, $first['counts'][ \SidrenaCijena\Compliance\Finding::ERROR ] );
		$this->assertSame( 2, $history->latest_map_calls );

		$second = DashboardPage::aggregate();
		$this->assertSame( 4, $history->latest_map_calls, 'Compliance errors must prevent caching and force recalculation.' );
	}
}
