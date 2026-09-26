<?php
/**
 * Price-list builder query batching tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Export\PriceListBuilder;

/**
 * Counting catalog provider.
 */
class BuilderFakeCatalog implements CatalogProvider {

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
		return null;
	}

	/**
	 * Variation.
	 *
	 * @param int $parent_id     Parent.
	 * @param int $variation_id  Variation.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		return null;
	}

	/**
	 * Get items.
	 *
	 * @param array $args Query args.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		return array_values( $this->items );
	}

	/**
	 * Count.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		return count( $this->items );
	}

	/**
	 * Iterate.
	 *
	 * @param array $args Query args.
	 * @return \Generator<CatalogItem>
	 */
	public function iterate( array $args = array() ) {
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
		return array();
	}
}

/**
 * History repository double that counts batched lookups.
 */
class BuilderFakeHistory extends PriceHistoryRepository {

	/**
	 * Number of `latest_map()` calls.
	 *
	 * @var int
	 */
	public $latest_map_calls = 0;

	/**
	 * Number of per-item `latest()` calls.
	 *
	 * @var int
	 */
	public $latest_calls = 0;

	/**
	 * Anchor rows keyed by `product:variation`.
	 *
	 * @var array<string,array>
	 */
	public $anchors = array();

	/**
	 * Batched latest lookup.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,array>
	 */
	public function latest_map( array $product_ids, $price_type ) {
		++$this->latest_map_calls;

		$ids = array_map( 'intval', $product_ids );
		$map = array();

		foreach ( $this->anchors as $key => $row ) {
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
		++$this->latest_calls;

		return null;
	}
}

/**
 * Sale repository double that counts batched lookups.
 */
class BuilderFakeSales extends SpecialSaleRepository {

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
		++$this->active_map_calls;

		return $this->sales;
	}
}

/**
 * @covers \SidrenaCijena\Export\PriceListBuilder
 */
class PriceListBuilderTest extends TestCase {

	/**
	 * Reset option state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options'] = array();
	}

	/**
	 * Build with three items.
	 *
	 * @return PriceListBuilder
	 */
	protected function builder() {
		$catalog             = new BuilderFakeCatalog();
		$catalog->items[101] = new CatalogItem(
			array(
				'id'            => 101,
				'name'          => 'Kava 250g',
				'sku'           => 'KAV-250',
				'regular_price' => '7.49',
				'categories'    => array( 'kava' ),
			)
		);
		$catalog->items[102] = new CatalogItem(
			array(
				'id'            => 102,
				'name'          => 'Čaj 100g',
				'sku'           => 'CAJ-100',
				'regular_price' => '4.00',
				'categories'    => array( 'caj' ),
			)
		);
		$catalog->items[103] = new CatalogItem(
			array(
				'id'            => 103,
				'name'          => 'Šišanje',
				'sku'           => 'USL-1',
				'type'          => 'service',
				'regular_price' => '20.00',
			)
		);

		$history          = new BuilderFakeHistory();
		$history->anchors = array(
			'101:0' => array(
				'price'          => '7.49',
				'reference_date' => '2026-09-10',
				'created_at'     => '2026-09-10 08:00:00',
			),
		);

		$sales        = new BuilderFakeSales();
		$sales->sales = array(
			'102:0' => array(
				'sale_type'   => 'akcija',
				'promo_price' => '3.20',
			),
		);

		return array( new PriceListBuilder( $catalog, $history, $sales ), $history, $sales );
	}

	/**
	 * The builder resolves anchors and sales in one query each, never per item.
	 *
	 * @return void
	 */
	public function test_build_batches_history_and_sale_queries() {
		list( $builder, $history, $sales ) = $this->builder();

		$result = $builder->build(
			array(
				'id'         => 1,
				'name'       => 'Zagreb',
				'applies_to' => array( 'mode' => 'all' ),
			)
		);

		$this->assertCount( 3, $result['items'] );
		$this->assertSame( 1, $history->latest_map_calls, 'Anchors must be resolved with a single batched query.' );
		$this->assertSame( 1, $sales->active_map_calls, 'Sales must be resolved with a single batched query.' );
		$this->assertSame( 0, $history->latest_calls, 'No per-item history lookup may run.' );

		$rows = array();
		foreach ( $result['items'] as $row ) {
			$rows[ $row['sifra'] ] = $row;
		}

		$this->assertSame( 7.49, $rows['KAV-250']['sidrena_cijena'] );
		$this->assertSame( '2026-09-10', $rows['KAV-250']['anchor_reference_date'] );
		$this->assertTrue( $rows['CAJ-100']['poseban_oblik_prodaje'] );
		$this->assertFalse( $rows['USL-1']['poseban_oblik_prodaje'] );
		$this->assertNull( $rows['USL-1']['sidrena_cijena'] );
		$this->assertSame(
			array(
				'product' => 2,
				'service' => 1,
			),
			$result['meta']['counts']
		);
	}

	/**
	 * An empty catalog does not hit the history or sale tables.
	 *
	 * @return void
	 */
	public function test_build_skips_pricing_queries_for_empty_catalog() {
		$catalog = new BuilderFakeCatalog();
		$history = new BuilderFakeHistory();
		$sales   = new BuilderFakeSales();
		$builder = new PriceListBuilder( $catalog, $history, $sales );
		$result  = $builder->build( array( 'id' => 1 ) );

		$this->assertSame( array(), $result['items'] );
		$this->assertSame( 0, $result['meta']['count'] );
		$this->assertSame( 0, $history->latest_map_calls );
		$this->assertSame( 0, $sales->active_map_calls );
	}
}
