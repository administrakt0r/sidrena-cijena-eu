<?php
/**
 * Unit tests for the cached REST price-list builds.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Api
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Api\PriceListController;
use Titanworks\SidrenaCijena\Api\RestCache;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Catalog\CatalogProvider;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\PublicationRepository;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

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
 * In-memory publication repository double for caching tests.
 */
class BuildCacheFakePublications extends PublicationRepository {

	/**
	 * Number of index() calls.
	 *
	 * @var int
	 */
	public $index_calls = 0;

	/**
	 * Rows returned by index.
	 *
	 * @var array<int,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Avoid hitting the database in unit tests.
	 */
	public function __construct() {
	}

	/**
	 * Publication index.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return array{items:array<int,array<mixed>>,total:int}
	 */
	public function index( array $args = array() ) {
		unset( $args );

		++$this->index_calls;

		return array(
			'items' => $this->rows,
			'total' => count( $this->rows ),
		);
	}

	/**
	 * Last successful.
	 *
	 * @param int $location_id Location ID.
	 * @return array<mixed>|null
	 */
	public function last_successful( $location_id = 0 ) {
		unset( $location_id );

		return null;
	}
}

/**
 * In-memory location repository double for caching tests.
 */
class BuildCacheFakeLocations extends LocationRepository {

	/**
	 * Number of all() calls.
	 *
	 * @var int
	 */
	public $all_calls = 0;

	/**
	 * Rows returned by all.
	 *
	 * @var array<int,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Avoid hitting the database in unit tests.
	 */
	public function __construct() {
	}

	/**
	 * Locations.
	 *
	 * @param bool $active_only Active only.
	 * @return array<int,array<mixed>>
	 */
	public function all( $active_only = false ) {
		unset( $active_only );

		++$this->all_calls;

		return $this->rows;
	}

	/**
	 * Get one location by ID.
	 *
	 * @param int $id Location ID.
	 * @return array<string,mixed>|null
	 */
	public function get( $id ) {
		foreach ( $this->rows as $row ) {
			if ( (int) $row['id'] === (int) $id ) {
				return $row;
			}
		}

		return null;
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Api\PriceListController::cached_build
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

		$GLOBALS['twbce_test_options']    = array();
		$GLOBALS['twbce_test_transients'] = array();

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

	/**
	 * Endpoint get_publications reuses cached responses and refreshes on rest_cache flush.
	 *
	 * @return void
	 */
	public function test_get_publications_reuses_cached_response_and_refreshes_on_flush() {
		$pubs       = new BuildCacheFakePublications();
		$pubs->rows = array(
			array(
				'id'           => 1,
				'location_id'  => 1,
				'format'       => 'csv',
				'filename'     => 'test.csv',
				'url'          => 'https://example.com/test.csv',
				'checksum'     => 'abc',
				'rule_version' => '1.0',
				'status'       => 'published',
				'item_count'   => 5,
				'published_at' => '2026-09-27 12:00:00',
				'expires_at'   => '2026-10-27 12:00:00',
			),
		);
		PluginSwap::swap( 'publications', $pubs );

		$request = new \WP_REST_Request( 'GET', '/sidrena-cijena/v1/publications' );

		$res1 = PriceListController::get_publications( $request );
		$this->assertSame( 1, $pubs->index_calls );
		$this->assertInstanceOf( \WP_REST_Response::class, $res1 );

		$res2 = PriceListController::get_publications( $request );
		$this->assertSame( 1, $pubs->index_calls, 'Repeat get_publications calls must be served from cache.' );
		$this->assertSame( $res1->get_data(), $res2->get_data() );

		Plugin::instance()->rest_cache()->flush();

		$res3 = PriceListController::get_publications( $request );
		$this->assertSame( 2, $pubs->index_calls, 'Cache flush must trigger a re-query for get_publications.' );

		PluginSwap::swap( 'publications', null );
	}

	/**
	 * Endpoint get_status reuses cached responses and refreshes on rest_cache flush.
	 *
	 * @return void
	 */
	public function test_get_status_reuses_cached_response_and_refreshes_on_flush() {
		$locs       = new BuildCacheFakeLocations();
		$locs->rows = array(
			array(
				'id'         => 1,
				'name'       => 'Zagreb',
				'identifier' => 'ZG-01',
				'active'     => true,
			),
		);
		PluginSwap::swap( 'locations', $locs );
		PluginSwap::swap( 'publications', new BuildCacheFakePublications() );

		$request = new \WP_REST_Request( 'GET', '/sidrena-cijena/v1/status' );

		$res1 = PriceListController::get_status( $request );
		$this->assertSame( 1, $locs->all_calls );
		$this->assertInstanceOf( \WP_REST_Response::class, $res1 );

		$res2 = PriceListController::get_status( $request );
		$this->assertSame( 1, $locs->all_calls, 'Repeat get_status calls must be served from cache.' );
		$this->assertSame( $res1->get_data(), $res2->get_data() );

		Plugin::instance()->rest_cache()->flush();

		$res3 = PriceListController::get_status( $request );
		$this->assertSame( 2, $locs->all_calls, 'Cache flush must trigger a re-query for get_status.' );

		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
	}

	/**
	 * Verify download_csv caches the serialized document across requests and invalidates on flush.
	 *
	 * @return void
	 */
	public function test_download_caches_serialized_document_and_refreshes_on_flush() {
		$locs       = new BuildCacheFakeLocations();
		$locs->rows = array(
			array(
				'id'         => 1,
				'name'       => 'Zagreb',
				'identifier' => 'ZG-01',
				'active'     => true,
			),
		);
		PluginSwap::swap( 'locations', $locs );
		PluginSwap::swap( 'publications', new BuildCacheFakePublications() );

		$request = new \WP_REST_Request( 'GET', '/sidrena-cijena/v1/download/csv' );
		$request->set_param( 'location', 1 );
		$request->set_param( 'type', 'product' );

		$res1 = PriceListController::download_csv( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $res1 );
		$this->assertSame( 1, $this->catalog->iterate_calls );

		$res2 = PriceListController::download_csv( $request );
		$this->assertSame( 1, $this->catalog->iterate_calls, 'Repeat download must be served from cache without rebuild.' );
		$this->assertSame( $res1->get_data(), $res2->get_data() );

		Plugin::instance()->rest_cache()->flush();

		$res3 = PriceListController::download_csv( $request );
		$this->assertSame( 2, $this->catalog->iterate_calls, 'Flush must invalidate cached download document.' );

		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
	}

	/**
	 * Verify download_xml caches the serialized document across requests and invalidates on flush.
	 *
	 * @return void
	 */
	public function test_download_xml_caches_serialized_document_and_refreshes_on_flush() {
		$locs       = new BuildCacheFakeLocations();
		$locs->rows = array(
			array(
				'id'         => 1,
				'name'       => 'Zagreb',
				'identifier' => 'ZG-01',
				'active'     => true,
			),
		);
		PluginSwap::swap( 'locations', $locs );
		PluginSwap::swap( 'publications', new BuildCacheFakePublications() );

		$request = new \WP_REST_Request( 'GET', '/sidrena-cijena/v1/download/xml' );
		$request->set_param( 'location', 1 );
		$request->set_param( 'type', 'all' );

		$res1 = PriceListController::download_xml( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $res1 );
		$this->assertSame( 1, $this->catalog->iterate_calls );

		$res2 = PriceListController::download_xml( $request );
		$this->assertSame( 1, $this->catalog->iterate_calls, 'Repeat download must be served from cache without rebuild.' );
		$this->assertSame( $res1->get_data(), $res2->get_data() );

		Plugin::instance()->rest_cache()->flush();

		$res3 = PriceListController::download_xml( $request );
		$this->assertSame( 2, $this->catalog->iterate_calls, 'Flush must invalidate cached download document.' );

		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
	}
}
