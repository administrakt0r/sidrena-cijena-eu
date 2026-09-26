<?php
/**
 * Unit tests for the cached REST price-list builds.
 *
 * @package SidrenaCijena\Tests\Unit\Api
 */

namespace SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\PriceListController;
use SidrenaCijena\Api\RestCache;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * In-memory catalog that counts streamed builds.
 */
class BuildCacheFakeCatalog implements CatalogProvider {

	/**
	 * Items served by the fake.
	 *
	 * @var CatalogItem[]
	 */
	public $items = array();

	/**
	 * Number of iterate() calls (one per catalog build).
	 *
	 * @var int
	 */
	public $iterate_calls = 0;

	/**
	 * Provider source.
	 *
	 * @return string
	 */
	public function get_source() {
		return 'standalone';
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
		foreach ( $this->items as $item ) {
			if ( (int) $item->id === (int) $id && ! $item->is_variation() ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Variation item.
	 *
	 * @param int $parent_id    Parent ID.
	 * @param int $variation_id Variation ID.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		unset( $parent_id, $variation_id );

		return null;
	}

	/**
	 * Item query.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		unset( $args );

		return $this->items;
	}

	/**
	 * Item count.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		unset( $args );

		return count( $this->items );
	}

	/**
	 * Streamed items.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return \Generator<int,CatalogItem>
	 */
	public function iterate( array $args = array() ) {
		unset( $args );

		++$this->iterate_calls;

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
	 * Category slugs of an item.
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
 * In-memory price history.
 */
class BuildCacheFakeHistory extends PriceHistoryRepository {

	/**
	 * Anchor rows keyed by `product:variation`.
	 *
	 * @var array<string,array<mixed>>
	 */
	public $anchors = array();

	/**
	 * Latest rows per type.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,array<mixed>>
	 */
	public function latest_map( array $product_ids, $price_type ) {
		unset( $product_ids, $price_type );

		return $this->anchors;
	}

	/**
	 * Latest single row.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array<mixed>|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		unset( $product_id, $variation_id, $price_type );

		return null;
	}

	/**
	 * Record an insert.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int
	 */
	public function insert( array $data ) {
		unset( $data );

		return 0;
	}
}

/**
 * In-memory special sales.
 */
class BuildCacheFakeSales extends SpecialSaleRepository {

	/**
	 * Active sale rows keyed by `product:variation`.
	 *
	 * @var array<string,array<mixed>>
	 */
	public $map = array();

	/**
	 * Active sales.
	 *
	 * @param string $at Reference time.
	 * @return array<string,array<mixed>>
	 */
	public function active_map( $at = '' ) {
		unset( $at );

		return $this->map;
	}
}

/**
 * @covers \SidrenaCijena\Api\PriceListController::cached_build
 */
class PriceListControllerCacheTest extends TestCase {

	/**
	 * Catalog fake behind the builder.
	 *
	 * @var BuildCacheFakeCatalog
	 */
	protected $catalog;

	/**
	 * Swap the container services.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();

		$this->catalog             = new BuildCacheFakeCatalog();
		$this->catalog->items[101] = new CatalogItem(
			array(
				'id'            => 101,
				'name'          => 'Kava 250g',
				'sku'           => 'KAV-250',
				'regular_price' => '7.49',
			)
		);
		$this->catalog->items[102] = new CatalogItem(
			array(
				'id'            => 102,
				'name'          => 'Čaj 100g',
				'sku'           => 'CAJ-100',
				'regular_price' => '4.00',
			)
		);

		$history          = new BuildCacheFakeHistory();
		$history->anchors = array(
			'101:0' => array(
				'price'          => '7.49',
				'reference_date' => '2026-09-10',
				'created_at'     => '2026-09-10 08:00:00',
			),
		);

		PluginSwap::swap( 'catalog', $this->catalog );
		PluginSwap::swap( 'history', $history );
		PluginSwap::swap( 'special_sales', new BuildCacheFakeSales() );
		PluginSwap::swap( 'rest_cache', new RestCache() );
	}

	/**
	 * Restore the container.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );
		PluginSwap::swap( 'special_sales', null );
		PluginSwap::swap( 'rest_cache', null );

		parent::tearDown();
	}

	/**
	 * Call the protected cached_build().
	 *
	 * @param array<mixed> $location Location row.
	 * @param array<mixed> $args     Builder arguments.
	 * @return array{items:array<int,array<mixed>>,meta:array<mixed>}
	 */
	protected function cached_build( array $location, array $args = array() ) {
		$method = new \ReflectionMethod( PriceListController::class, 'cached_build' );
		$method->setAccessible( true );

		return $method->invoke( null, $location, $args );
	}

	/**
	 * Repeat requests reuse the cached build instead of walking the catalog again.
	 *
	 * @return void
	 */
	public function test_repeat_requests_reuse_cached_build() {
		$location = array(
			'id'   => 1,
			'name' => 'Zagreb',
		);

		$first  = $this->cached_build( $location, array( 'type' => 'all' ) );
		$second = $this->cached_build( $location, array( 'type' => 'all' ) );

		$this->assertArrayHasKey( 'items', $first );
		$this->assertArrayHasKey( 'meta', $first );
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->catalog->iterate_calls );
	}

	/**
	 * A cache flush rebuilds the catalog on the next request.
	 *
	 * @return void
	 */
	public function test_flush_rebuilds_on_next_request() {
		$location = array(
			'id'   => 1,
			'name' => 'Zagreb',
		);

		$this->cached_build( $location, array( 'type' => 'all' ) );
		$this->assertSame( 1, $this->catalog->iterate_calls );

		Plugin::instance()->rest_cache()->flush();

		$rebuilt = $this->cached_build( $location, array( 'type' => 'all' ) );

		$this->assertSame( 2, $this->catalog->iterate_calls );
		$this->assertCount( 2, $rebuilt['items'] );
	}

	/**
	 * Locations and item types get separate cache entries.
	 *
	 * @return void
	 */
	public function test_locations_and_types_are_cached_separately() {
		$this->cached_build(
			array(
				'id'   => 1,
				'name' => 'Zagreb',
			),
			array( 'type' => 'all' )
		);
		$this->cached_build(
			array(
				'id'   => 2,
				'name' => 'Split',
			),
			array( 'type' => 'all' )
		);
		$this->cached_build(
			array(
				'id'   => 1,
				'name' => 'Zagreb',
			),
			array( 'type' => 'products' )
		);

		$this->assertSame( 3, $this->catalog->iterate_calls );

		$this->cached_build(
			array(
				'id'   => 1,
				'name' => 'Zagreb',
			),
			array( 'type' => 'all' )
		);

		$this->assertSame( 3, $this->catalog->iterate_calls );
	}
}
