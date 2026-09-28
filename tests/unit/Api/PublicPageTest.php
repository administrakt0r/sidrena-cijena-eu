<?php
/**
 * Unit tests for the public HTML price list and archive pages.
 *
 * @package SidrenaCijena\Tests\Unit\Api
 */

namespace SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\PublicPage;
use SidrenaCijena\Api\TemplateFunctions;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * In-memory catalog rows.
 */
class PageCatalogFake implements CatalogProvider {

	/**
	 * Items served by the fake.
	 *
	 * @var CatalogItem[]
	 */
	public $items = array();

	/**
	 * Number of iterate() calls (one per row build).
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
		foreach ( $this->items as $item ) {
			if ( (int) $item->id === (int) $parent_id && (int) $item->variation_id === (int) $variation_id ) {
				return $item;
			}
		}

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
class PageHistoryFake extends PriceHistoryRepository {

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
		$key = (int) $product_id . ':' . (int) $variation_id;

		return 'anchor' === $price_type && isset( $this->anchors[ $key ] ) ? $this->anchors[ $key ] : null;
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
class PageSaleFake extends SpecialSaleRepository {

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
 * In-memory location rows.
 */
class PageLocationFake {

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
 * In-memory publication index.
 */
class PagePublicationFake {

	/**
	 * Rows returned by index().
	 *
	 * @var array<int,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Number of index() calls.
	 *
	 * @var int
	 */
	public $index_calls = 0;

	/**
	 * Publication index.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return array<mixed>
	 */
	public function index( array $args = array() ) {
		++$this->index_calls;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$offset   = ( $page - 1 ) * $per_page;
		$total    = count( $this->rows );
		$items    = array_slice( $this->rows, $offset, $per_page );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}

/**
 * Probe exposing the protected row helpers.
 */
class PublicPageProbe extends PublicPage {

	/**
	 * Expose the search matcher.
	 *
	 * @param array<mixed> $row Row.
	 * @param string       $q   Search term.
	 * @return bool
	 */
	public function match( array $row, $q ) {
		return $this->matches( $row, $q );
	}

	/**
	 * Expose the row display values.
	 *
	 * @param array<mixed> $row Row.
	 * @return array<mixed>
	 */
	public function decorate( array $row ) {
		return $this->display_row( $row );
	}

	/**
	 * Expose the publication display values.
	 *
	 * @param array<mixed> $item Publication row.
	 * @return array<mixed>
	 */
	public function decorate_publication( array $item ) {
		return $this->display_publication( $item );
	}

	/**
	 * Expose the sanitized request arguments.
	 *
	 * @return array<mixed>
	 */
	public function request_arguments() {
		return $this->request_args();
	}

	/**
	 * Expose the once-per-request styles.
	 *
	 * @return string
	 */
	public function styles() {
		return $this->style();
	}

	/**
	 * Expose a template partial for serialized-output assertions.
	 *
	 * @param string       $name Partial name.
	 * @param array<mixed> $args Template arguments.
	 * @return string
	 */
	public function render_partial( $name, array $args ) {
		return $this->partial( $name, $args );
	}

	/**
	 * Reset the static style flag.
	 *
	 * @return void
	 */
	public static function reset_styles() {
		$property = new \ReflectionProperty( PublicPage::class, 'styled' );
		$property->setAccessible( true );
		$property->setValue( null, false );
	}
}

/**
 * Covers search matching, display formatting, request sanitizing and list filtering.
 *
 * @covers \SidrenaCijena\Api\PublicPage
 */
class PublicPageTest extends TestCase {

	/**
	 * Reset shared test state before and after each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->reset_state();
	}

	protected function tearDown(): void {
		$this->reset_state();
		parent::tearDown();
	}

	private function reset_state() {
		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );
		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
		PluginSwap::swap( 'special_sales', null );
		PluginSwap::swap( 'anchors', null );
		PluginSwap::swap( 'anchor_cache', null );
		PublicPageProbe::reset_styles();

		unset( $GLOBALS['sidrena_test_timezone'] );

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
		$_GET                               = array();
	}

	/**
	 * Build the canonical row shape the templates expect.
	 *
	 * @param array<mixed> $overrides Field overrides.
	 * @return array<mixed>
	 */
	private function row( array $overrides = array() ) {
		return array_merge(
			array(
				'vrsta'                         => 'proizvod',
				'naziv'                         => 'Mlijeko 1L',
				'sifra'                         => 'MLJ-1',
				'marka'                         => 'Dukat',
				'jedinica_mjere'                => 'kom',
				'cijena_za_jedinicu_mjere'      => 1.33,
				'maloprodajna_cijena'           => 1.99,
				'poseban_oblik_prodaje'         => true,
				'naziv_posebnog_oblika_prodaje' => 'Akcija',
				'sidrena_cijena'                => 1.75,
				'barkod'                        => '3850001',
				'dostupnost'                    => 'dostupno',
				'product_id'                    => 11,
				'variation_id'                  => 0,
				'anchor_reference_date'         => '2025-05-02',
				'current_price'                 => 1.99,
				'updated_at'                    => '2026-09-25 08:00:00',
				'special_sale'                  => null,
			),
			$overrides
		);
	}

	/**
	 * The matcher scans name, code, brand and barcode.
	 *
	 * @return void
	 */
	public function test_matches_scans_searchable_fields() {
		$this->reset_state();

		$probe = new PublicPageProbe();
		$row   = $this->row();

		$this->assertTrue( $probe->match( $row, 'mlijeko' ) );
		$this->assertTrue( $probe->match( $row, 'MLJ' ) );
		$this->assertTrue( $probe->match( $row, 'dukAT' ) );
		$this->assertTrue( $probe->match( $row, '385000' ) );
		$this->assertFalse( $probe->match( $row, 'kruh' ) );
		$this->assertFalse( $probe->match( array( 'naziv' => '' ), 'x' ) );
	}

	/**
	 * Display values cover prices, reference date, type, sale and availability.
	 *
	 * @return void
	 */
	public function test_display_row_formats_values() {
		$this->reset_state();

		$probe = new PublicPageProbe();
		$out   = $probe->decorate(
			$this->row(
				array(
					'special_sale' => array( 'lowest_30_day_price' => 1.49 ),
				)
			)
		);

		$this->assertSame( '1,33 €', $out['unit_price_display'] );
		$this->assertSame( '1,99 €', $out['retail_display'] );
		$this->assertSame( '1,49 €', $out['lowest_30_day_display'] );
		$this->assertSame( '1,75 €', $out['anchor_display'] );
		$this->assertSame( '02.05.2025.', $out['reference_display'] );
		$this->assertSame( 'Product', $out['type_display'] );
		$this->assertSame( 'Akcija', $out['sale_display'] );
		$this->assertSame( 'Available', $out['availability_display'] );
	}

	/**
	 * The public table serializes the 30-day low between retail price and sale name.
	 *
	 * @return void
	 */
	public function test_public_price_list_serializes_the_30_day_reference_with_a_sale() {
		$this->reset_state();

		$probe      = new PublicPageProbe();
		$row        = $probe->decorate(
			$this->row(
				array(
					'special_sale' => array( 'lowest_30_day_price' => 1.49 ),
				)
			)
		);
		$html       = $probe->render_partial(
			'public-price-list.php',
			array(
				'rows'        => array( $row ),
				'meta'        => array(),
				'location'    => null,
				'q'           => '',
				'type'        => '',
				'feed_xml'    => '',
				'feed_csv'    => '',
				'archive_url' => '',
			)
		);
		$serialized = preg_replace( '/\s+/', ' ', $html );

		$this->assertStringContainsString( '<th scope="col">Lowest price in the last 30 days</th>', $serialized );
		$this->assertStringContainsString( '<td>1,99 €</td> <td>1,49 €</td> <td>Akcija</td>', $serialized );
	}

	/**
	 * Reference dates are legal date-only values and never shift across time zones.
	 *
	 * @return void
	 */
	public function test_display_row_reference_date_ignores_site_timezone() {
		$this->reset_state();

		$GLOBALS['sidrena_test_timezone'] = new \DateTimeZone( 'America/New_York' );

		try {
			$out = ( new PublicPageProbe() )->decorate( $this->row() );

			$this->assertSame( '02.05.2025.', $out['reference_display'] );
		} finally {
			unset( $GLOBALS['sidrena_test_timezone'] );
		}
	}

	/**
	 * Missing optional values render as empty strings.
	 *
	 * @return void
	 */
	public function test_display_row_handles_missing_values() {
		$this->reset_state();

		$probe = new PublicPageProbe();
		$out   = $probe->decorate(
			$this->row(
				array(
					'vrsta'                    => 'usluga',
					'cijena_za_jedinicu_mjere' => null,
					'sidrena_cijena'           => null,
					'anchor_reference_date'    => null,
					'poseban_oblik_prodaje'    => false,
					'dostupnost'               => 'nedostupno',
				)
			)
		);

		$this->assertSame( '', $out['unit_price_display'] );
		$this->assertSame( '', $out['anchor_display'] );
		$this->assertSame( '', $out['lowest_30_day_display'] );
		$this->assertSame( '', $out['reference_display'] );
		$this->assertSame( '', $out['sale_display'] );
		$this->assertSame( 'Service', $out['type_display'] );
		$this->assertSame( 'Unavailable', $out['availability_display'] );
	}

	/**
	 * Publication rows show the publish time, format, size and checksum.
	 *
	 * @return void
	 */
	public function test_display_publication_formats_archive_row() {
		$this->reset_state();

		$probe = new PublicPageProbe();
		$out   = $probe->decorate_publication(
			array(
				'published_at' => '2026-09-25 10:00:00',
				'created_at'   => '2026-09-24 08:00:00',
				'format'       => 'csv',
				'file_size'    => 2048,
				'checksum'     => 'abcdef1234567890',
				'url'          => 'https://shop.example/list.csv',
			)
		);

		$this->assertSame( wp_date( 'd.m.Y. H:i', (int) strtotime( '2026-09-25 10:00:00' ) ), $out['published_display'] );
		$this->assertSame( 'CSV', $out['format_display'] );
		$this->assertSame( '2 KB', $out['size_display'] );
		$this->assertSame( 'abcdef123456', $out['checksum_short'] );
		$this->assertSame( 'https://shop.example/list.csv', $out['download_url'] );
	}

	/**
	 * Unpublished rows fall back to the creation time.
	 *
	 * @return void
	 */
	public function test_display_publication_falls_back_to_created_at() {
		$this->reset_state();

		$probe = new PublicPageProbe();
		$out   = $probe->decorate_publication(
			array(
				'published_at' => '',
				'created_at'   => '2026-09-24 08:00:00',
				'format'       => 'xml',
				'file_size'    => 2048,
				'checksum'     => 'abcdef1234567890',
				'url'          => 'https://shop.example/list.xml',
			)
		);

		$this->assertSame( wp_date( 'd.m.Y. H:i', (int) strtotime( '2026-09-24 08:00:00' ) ), $out['published_display'] );
		$this->assertSame( 'XML', $out['format_display'] );
	}

	/**
	 * Request arguments are sanitized before filtering.
	 *
	 * @return void
	 */
	public function test_request_arguments_are_sanitized() {
		$this->reset_state();

		$_GET['q']    = 'mlijeko';
		$_GET['type'] = 'proizvod';

		$probe = new PublicPageProbe();

		$this->assertSame(
			array(
				'q'     => 'mlijeko',
				'type'  => 'proizvod',
				'paged' => 1,
			),
			$probe->request_arguments()
		);

		$_GET = array();
		$this->assertSame(
			array(
				'q'     => '',
				'type'  => '',
				'paged' => 1,
			),
			$probe->request_arguments()
		);

		$_GET['paged'] = '3';
		$this->assertSame(
			array(
				'q'     => '',
				'type'  => '',
				'paged' => 3,
			),
			$probe->request_arguments()
		);
	}

	/**
	 * Styles are emitted once per request.
	 *
	 * @return void
	 */
	public function test_styles_are_emitted_once() {
		$this->reset_state();

		$probe = new PublicPageProbe();

		$this->assertStringContainsString( '<style>', $probe->styles() );
		$this->assertSame( '', $probe->styles() );
	}

	/**
	 * Wire catalog fakes producing one product and one service.
	 *
	 * @return PageCatalogFake The catalog fake in use.
	 */
	private function wire_catalog() {
		$catalog        = new PageCatalogFake();
		$catalog->items = array(
			new CatalogItem(
				array(
					'id'            => 11,
					'type'          => 'product',
					'name'          => 'Mlijeko 1L',
					'sku'           => 'MLJ-1',
					'brand'         => 'Dukat',
					'barcode'       => '3850001',
					'regular_price' => 1.99,
					'current_price' => 1.99,
				)
			),
			new CatalogItem(
				array(
					'id'            => 12,
					'type'          => 'service',
					'name'          => 'Servis klime',
					'sku'           => 'SRV-1',
					'regular_price' => 50.0,
					'current_price' => 50.0,
				)
			),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history          = new PageHistoryFake();
		$history->anchors = array(
			$catalog->items[0]->key() => array(
				'price'          => 1.75,
				'reference_date' => '2025-05-02',
				'created_at'     => '2025-05-02 08:00:00',
			),
		);
		PluginSwap::swap( 'history', $history );
		PluginSwap::swap( 'special_sales', new PageSaleFake() );

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 7,
				'type' => 'webshop',
				'name' => 'Webshop Zagreb',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		return $catalog;
	}

	/**
	 * The list renders catalog rows with anchor data.
	 *
	 * @return void
	 */
	public function test_renders_catalog_rows() {
		$this->reset_state();
		$this->wire_catalog();

		$probe = new PublicPageProbe();
		$out   = $probe->render_list( array() );

		$this->assertStringContainsString( 'sidrena-cijena-cjenik', $out );
		$this->assertStringContainsString( 'Mlijeko 1L', $out );
		$this->assertStringContainsString( 'Servis klime', $out );
	}

	/**
	 * The price shortcode defaults to one 30-day block and honors its override.
	 *
	 * @covers \SidrenaCijena\Api\TemplateFunctions::shortcode
	 * @return void
	 */
	public function test_price_shortcode_honors_30_day_visibility_without_duplicate_blocks() {
		$this->reset_state();
		$catalog = $this->wire_catalog();
		$item    = $catalog->items[0];
		PluginSwap::swap( 'anchors', new AnchorPriceService( Plugin::instance()->history() ) );
		$sales = new PageSaleFake();

		$sales->map[ $item->key() ] = array( 'lowest_30_day_price' => 1.50 );
		PluginSwap::swap( 'special_sales', $sales );

		$default = TemplateFunctions::shortcode( array( 'product_id' => $item->id ) );
		$this->assertSame( 1, substr_count( $default, 'sidrena-cijena--30-day' ) );
		$this->assertStringContainsString( '1,50', $default );

		$hidden = TemplateFunctions::shortcode(
			array(
				'product_id'  => $item->id,
				'show_30_day' => 'no',
			)
		);
		$this->assertSame( 0, substr_count( $hidden, 'sidrena-cijena--30-day' ) );
		$this->assertStringNotContainsString( '1,50', $hidden );

		$shown = TemplateFunctions::shortcode(
			array(
				'product_id'  => $item->id,
				'show_30_day' => 'yes',
			)
		);
		$this->assertSame( 1, substr_count( $shown, 'sidrena-cijena--30-day' ) );
	}

	/**
	 * The type filter keeps only the requested item type.
	 *
	 * @return void
	 */
	public function test_type_filter_narrows_rows() {
		$this->reset_state();
		$this->wire_catalog();

		$probe = new PublicPageProbe();
		$out   = $probe->render_list( array( 'type' => 'usluga' ) );

		$this->assertStringContainsString( 'Servis klime', $out );
		$this->assertStringNotContainsString( 'Mlijeko 1L', $out );
	}

	/**
	 * The search filter keeps only matching rows.
	 *
	 * @return void
	 */
	public function test_search_filter_narrows_rows() {
		$this->reset_state();
		$this->wire_catalog();

		$probe = new PublicPageProbe();
		$out   = $probe->render_list( array( 'q' => 'klime' ) );

		$this->assertStringContainsString( 'Servis klime', $out );
		$this->assertStringNotContainsString( 'Mlijeko 1L', $out );
	}

	/**
	 * Without a location the page renders empty instead of failing.
	 *
	 * @return void
	 */
	public function test_renders_empty_state_without_location() {
		$this->reset_state();

		PluginSwap::swap( 'catalog', new PageCatalogFake() );
		PluginSwap::swap( 'history', new PageHistoryFake() );
		PluginSwap::swap( 'special_sales', new PageSaleFake() );
		PluginSwap::swap( 'locations', new PageLocationFake() );

		$probe = new PublicPageProbe();
		$out   = $probe->render_list( array() );

		$this->assertStringContainsString( 'sidrena-cijena-cjenik', $out );
		$this->assertStringNotContainsString( 'Mlijeko 1L', $out );
		$this->assertStringContainsString( '<td colspan="13">', $out );
	}

	/**
	 * Repeat requests reuse the cached table instead of rebuilding it.
	 *
	 * @return void
	 */
	public function test_render_list_serves_cached_rows_on_repeat() {
		$this->reset_state();
		$catalog = $this->wire_catalog();

		$probe  = new PublicPageProbe();
		$first  = $probe->render_list( array() );
		$second = $probe->render_list( array() );

		$this->assertStringContainsString( 'Mlijeko 1L', $first );
		$this->assertStringContainsString( 'Mlijeko 1L', $second );
		$this->assertSame( 1, $catalog->iterate_calls );
	}

	/**
	 * Flushing the shared cache rebuilds the table.
	 *
	 * @return void
	 */
	public function test_cache_flush_rebuilds_rows() {
		$this->reset_state();
		$catalog = $this->wire_catalog();

		$probe = new PublicPageProbe();
		$probe->render_list( array() );

		Plugin::instance()->rest_cache()->flush();

		$probe->render_list( array() );

		$this->assertSame( 2, $catalog->iterate_calls );
	}

	/**
	 * Distinct search, type, and pagination arguments reuse the canonical build without rebuilding.
	 *
	 * @return void
	 */
	public function test_distinct_arguments_reuse_canonical_build() {
		$this->reset_state();
		$catalog = $this->wire_catalog();

		$probe = new PublicPageProbe();
		$base  = $probe->render_list( array() );
		$query = $probe->render_list( array( 'q' => 'klime' ) );
		$type  = $probe->render_list( array( 'type' => 'usluga' ) );
		$page  = $probe->render_list( array( 'paged' => 2 ) );

		$this->assertSame( 1, $catalog->iterate_calls );
		$this->assertStringContainsString( 'Mlijeko 1L', $base );
		$this->assertStringContainsString( 'Servis klime', $query );
		$this->assertStringNotContainsString( 'Mlijeko 1L', $query );
		$this->assertStringContainsString( 'Servis klime', $type );
	}

	/**
	 * Large catalog is paginated and displays result count and pagination links.
	 *
	 * @return void
	 */
	public function test_render_list_paginates_large_catalog() {
		$this->reset_state();

		$catalog = new PageCatalogFake();
		for ( $i = 1; $i <= 55; ++$i ) {
			$catalog->items[] = new CatalogItem(
				array(
					'id'            => $i,
					'type'          => 'product',
					'name'          => sprintf( 'Proizvod %02d', $i ),
					'sku'           => sprintf( 'SKU-%02d', $i ),
					'brand'         => 'Brand',
					'barcode'       => '1000' . $i,
					'regular_price' => 10.0 + $i,
					'current_price' => 10.0 + $i,
				)
			);
		}
		PluginSwap::swap( 'catalog', $catalog );
		PluginSwap::swap( 'history', new PageHistoryFake() );
		PluginSwap::swap( 'special_sales', new PageSaleFake() );

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 1,
				'type' => 'webshop',
				'name' => 'Webshop',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$probe = new PublicPageProbe();

		$page_1 = $probe->render_list( array( 'paged' => 1 ) );
		$this->assertStringContainsString( 'Proizvod 01<', $page_1 );
		$this->assertStringContainsString( 'Proizvod 50<', $page_1 );
		$this->assertStringNotContainsString( 'Proizvod 51<', $page_1 );
		$this->assertStringContainsString( 'Showing 1–50 of 55 items.', $page_1 );
		$this->assertStringContainsString( 'sidrena-cijena-pagination', $page_1 );
		$this->assertStringContainsString( 'sidrena-cijena-table-container', $page_1 );
		$this->assertStringContainsString( 'XML Feed', $page_1 );
		$this->assertStringContainsString( 'CSV Feed', $page_1 );

		PublicPageProbe::reset_styles();
		$page_2 = $probe->render_list( array( 'paged' => 2 ) );
		$this->assertStringNotContainsString( 'Proizvod 01<', $page_2 );
		$this->assertStringContainsString( 'Proizvod 51<', $page_2 );
		$this->assertStringContainsString( 'Proizvod 55<', $page_2 );
		$this->assertStringContainsString( 'Showing 51–55 of 55 items.', $page_2 );
	}

	/**
	 * Active search or type filters display a clear-filters link.
	 *
	 * @return void
	 */
	public function test_render_list_shows_clear_filters_link() {
		$this->reset_state();
		$this->wire_catalog();

		$probe = new PublicPageProbe();

		$clean = $probe->render_list( array() );
		$this->assertStringNotContainsString( 'Clear filters', $clean );

		PublicPageProbe::reset_styles();
		$filtered = $probe->render_list( array( 'q' => 'Mlijeko' ) );
		$this->assertStringContainsString( 'Clear filters', $filtered );
	}

	/**
	 * Archive render wraps in container, gives download links accessible names, and translates feed links.
	 *
	 * @return void
	 */
	public function test_render_archive_accessible_download_and_translated_feeds() {
		$this->reset_state();

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 7,
				'type' => 'webshop',
				'name' => 'Webshop Zagreb',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$pub       = new PagePublicationFake();
		$pub->rows = array(
			array(
				'id'           => 1,
				'location_id'  => 7,
				'format'       => 'csv',
				'filename'     => 'cjenik-2026-09-25.csv',
				'file_size'    => 1024,
				'checksum'     => '1234567890abcdef',
				'item_count'   => 10,
				'published_at' => '2026-09-25 12:00:00',
				'created_at'   => '2026-09-25 12:00:00',
				'url'          => 'https://example.com/cjenik-2026-09-25.csv',
			),
		);
		PluginSwap::swap( 'publications', $pub );

		$probe = new PublicPageProbe();
		$out   = $probe->render_archive();

		$this->assertStringContainsString( 'sidrena-cijena-table-container', $out );
		$this->assertStringContainsString( 'aria-label="Download cjenik-2026-09-25.csv"', $out );
		$this->assertStringContainsString( 'XML Feed', $out );
		$this->assertStringContainsString( 'CSV Feed', $out );
		$this->assertStringContainsString( 'Showing 1–1 of 1 published price lists.', $out );
	}

	/**
	 * Archive renders pagination links and count when results exceed per-page limit.
	 *
	 * @return void
	 */
	public function test_render_archive_pagination_and_count() {
		$this->reset_state();

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 7,
				'type' => 'webshop',
				'name' => 'Webshop Zagreb',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$pub = new PagePublicationFake();
		for ( $i = 1; $i <= 55; ++$i ) {
			$pub->rows[] = array(
				'id'           => $i,
				'location_id'  => 7,
				'format'       => 'csv',
				'filename'     => sprintf( 'cjenik-2026-09-%02d.csv', ( $i % 30 ) + 1 ),
				'file_size'    => 1024,
				'checksum'     => '1234567890abcdef',
				'item_count'   => 10,
				'published_at' => '2026-09-25 12:00:00',
				'created_at'   => '2026-09-25 12:00:00',
				'url'          => 'https://example.com/cjenik.csv',
			);
		}
		PluginSwap::swap( 'publications', $pub );

		$probe = new PublicPageProbe();

		// Page 1: 50 items of 55.
		$out1 = $probe->render_archive();
		$this->assertStringContainsString( 'Showing 1–50 of 55 published price lists.', $out1 );
		$this->assertStringContainsString( 'sidrena-cijena-pagination', $out1 );
		$this->assertStringContainsString( 'aria-label="Archive pagination"', $out1 );

		// Page 2: remaining 5 items.
		$out2 = $probe->render_archive( array( 'paged' => 2 ) );
		$this->assertStringContainsString( 'Showing 51–55 of 55 published price lists.', $out2 );
	}

	/**
	 * Shortcode sidrena-arhiva accepts paged attribute.
	 *
	 * @return void
	 */
	public function test_shortcode_archive_supports_paged() {
		$this->reset_state();

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 7,
				'type' => 'webshop',
				'name' => 'Webshop Zagreb',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$pub = new PagePublicationFake();
		for ( $i = 1; $i <= 55; ++$i ) {
			$pub->rows[] = array(
				'id'           => $i,
				'location_id'  => 7,
				'format'       => 'csv',
				'filename'     => 'cjenik.csv',
				'file_size'    => 1024,
				'checksum'     => '1234567890abcdef',
				'item_count'   => 10,
				'published_at' => '2026-09-25 12:00:00',
				'created_at'   => '2026-09-25 12:00:00',
				'url'          => 'https://example.com/cjenik.csv',
			);
		}
		PluginSwap::swap( 'publications', $pub );

		$page = new PublicPage();
		$out  = $page->shortcode_archive( array( 'paged' => 2 ) );
		$this->assertStringContainsString( 'Showing 51–55 of 55 published price lists.', $out );
	}

	/**
	 * Shortcode accepts paged attribute and $_GET['paged'].
	 *
	 * @return void
	 */
	public function test_shortcode_list_supports_paged_parameter() {
		$this->reset_state();

		$catalog = new PageCatalogFake();
		for ( $i = 1; $i <= 55; ++$i ) {
			$catalog->items[] = new CatalogItem(
				array(
					'id'            => $i,
					'type'          => 'product',
					'name'          => 'Stavka ' . $i,
					'sku'           => 'STK-' . $i,
					'regular_price' => 5.0,
					'current_price' => 5.0,
				)
			);
		}
		PluginSwap::swap( 'catalog', $catalog );
		PluginSwap::swap( 'history', new PageHistoryFake() );
		PluginSwap::swap( 'special_sales', new PageSaleFake() );

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 1,
				'type' => 'webshop',
				'name' => 'Webshop',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$probe = new PublicPageProbe();

		// Shortcode attribute paged 2.
		$out1 = $probe->shortcode_list( array( 'paged' => '2' ) );
		$this->assertStringContainsString( 'Stavka 55', $out1 );
		$this->assertStringNotContainsString( 'Stavka 1<', $out1 );

		// Via global query argument paged.
		PublicPageProbe::reset_styles();
		Plugin::instance()->rest_cache()->flush();
		$_GET['paged'] = '2';
		$out2          = $probe->shortcode_list( array() );
		$this->assertStringContainsString( 'Stavka 55', $out2 );
	}

	/**
	 * Fallback route matching resolves archive and list pages when rewrites are unflushed.
	 *
	 * @return void
	 */
	public function test_parse_request_fallback_matches_archive_and_list() {
		$this->reset_state();
		$page = new PublicPage();

		// 1. Matched from wp->request for archive.
		$wp             = new \stdClass();
		$wp->request    = PublicPage::STEM_ARCHIVE;
		$wp->query_vars = array( 'error' => '404' );
		$page->parse_request_fallback( $wp );
		$this->assertSame( PublicPage::PAGE_ARCHIVE, $wp->query_vars[ PublicPage::QUERY_VAR ] );
		$this->assertArrayNotHasKey( 'error', $wp->query_vars );

		// 2. Matched from wp->request for list.
		$wp             = new \stdClass();
		$wp->request    = PublicPage::STEM_LIST;
		$wp->query_vars = array();
		$page->parse_request_fallback( $wp );
		$this->assertSame( PublicPage::PAGE_LIST, $wp->query_vars[ PublicPage::QUERY_VAR ] );

		// 3. Matched from REQUEST_URI when wp->request is empty.
		$wp                     = new \stdClass();
		$wp->request            = '';
		$wp->query_vars         = array( 'error' => '404' );
		$_SERVER['REQUEST_URI'] = '/' . PublicPage::STEM_ARCHIVE . '/';
		$page->parse_request_fallback( $wp );
		$this->assertSame( PublicPage::PAGE_ARCHIVE, $wp->query_vars[ PublicPage::QUERY_VAR ] );
		$this->assertArrayNotHasKey( 'error', $wp->query_vars );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Footer link renders unobtrusive "Cjenici" link and supports filtering.
	 *
	 * @return void
	 */
	public function test_footer_link_renders_unobtrusive_cjenici_link() {
		$this->reset_state();
		$page = new PublicPage();

		// When footer link setting is disabled, nothing outputs.
		\SidrenaCijena\Support\Settings::update( array( 'archive_footer_link' => false ) );
		ob_start();
		$page->footer_link();
		$disabled_out = ob_get_clean();
		$this->assertSame( '', $disabled_out );

		// When enabled, outputs small link with default text "Cjenici".
		\SidrenaCijena\Support\Settings::update( array( 'archive_footer_link' => true ) );
		ob_start();
		$page->footer_link();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'sidrena-cijena-archive-link', $out );
		$this->assertStringContainsString( 'Cjenici', $out );
		$this->assertStringContainsString( PublicPage::STEM_ARCHIVE, $out );

		// Filter hook sidrena_cijena_footer_link_text.
		add_filter(
			'sidrena_cijena_footer_link_text',
			static function () {
				return 'Službeni cjenici';
			}
		);
		ob_start();
		$page->footer_link();
		$filtered_out = ob_get_clean();
		$this->assertStringContainsString( 'Službeni cjenici', $filtered_out );
	}

	/**
	 * Method add_rewrites self-heals by flushing rewrite rules when the archive rule is missing.
	 *
	 * @return void
	 */
	public function test_add_rewrites_self_heals_when_rule_missing() {
		$this->reset_state();
		$page = new PublicPage();

		$GLOBALS['sidrena_test_options']['rewrite_rules'] = array(
			'^some-other-slug/?$' => 'index.php?page=other',
		);
		$GLOBALS['sidrena_test_flushed_rules']            = 0;

		$page->add_rewrites();

		$this->assertGreaterThan( 0, $GLOBALS['sidrena_test_flushed_rules'] );
	}

	/**
	 * Method render_archive reuses cached HTML and refreshes on rest_cache flush.
	 *
	 * @return void
	 */
	public function test_render_archive_reuses_cache_and_invalidates_on_flush() {
		$this->reset_state();

		$locations       = new PageLocationFake();
		$locations->rows = array(
			array(
				'id'   => 7,
				'type' => 'webshop',
				'name' => 'Webshop Zagreb',
			),
		);
		PluginSwap::swap( 'locations', $locations );

		$pub       = new PagePublicationFake();
		$pub->rows = array(
			array(
				'id'           => 1,
				'location_id'  => 7,
				'format'       => 'csv',
				'filename'     => 'cjenik-2026-09-25.csv',
				'file_size'    => 1024,
				'checksum'     => '1234567890abcdef',
				'item_count'   => 10,
				'published_at' => '2026-09-25 12:00:00',
				'created_at'   => '2026-09-25 12:00:00',
				'url'          => 'https://example.com/cjenik-2026-09-25.csv',
			),
		);
		PluginSwap::swap( 'publications', $pub );

		$probe = new PublicPageProbe();

		$first = $probe->render_archive();
		$this->assertSame( 1, $pub->index_calls );

		$second = $probe->render_archive();
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $pub->index_calls, 'Repeat render_archive calls must be served from cache.' );

		Plugin::instance()->rest_cache()->flush();

		$third = $probe->render_archive();
		$this->assertSame( 2, $pub->index_calls, 'Flushing cache must cause render_archive to rebuild.' );
	}
}
