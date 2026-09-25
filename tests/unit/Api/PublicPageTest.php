<?php
/**
 * Unit tests for the public HTML price list and archive pages.
 *
 * @package SidrenaCijena\Tests\Unit\Api
 */

namespace SidrenaCijena\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\PublicPage;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
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
	 * Publication index.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return array<mixed>
	 */
	public function index( array $args = array() ) {
		unset( $args );

		return array( 'items' => $this->rows );
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
	 * Reset shared test state.
	 *
	 * @return void
	 */
	private function reset_state() {
		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );
		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
		PluginSwap::swap( 'special_sales', null );
		PublicPageProbe::reset_styles();

		$GLOBALS['sidrena_test_options'] = array();
		$_GET                            = array();
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
		$out   = $probe->decorate( $this->row() );

		$this->assertSame( '1,33 €', $out['unit_price_display'] );
		$this->assertSame( '1,99 €', $out['retail_display'] );
		$this->assertSame( '1,75 €', $out['anchor_display'] );
		$this->assertSame( wp_date( 'd.m.Y.', (int) strtotime( '2025-05-02' ) ), $out['reference_display'] );
		$this->assertSame( 'Product', $out['type_display'] );
		$this->assertSame( 'Akcija', $out['sale_display'] );
		$this->assertSame( 'Available', $out['availability_display'] );
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
				'q'    => 'mlijeko',
				'type' => 'proizvod',
			),
			$probe->request_arguments()
		);

		$_GET = array();
		$this->assertSame(
			array(
				'q'    => '',
				'type' => '',
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
	 * @return void
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
	}
}
