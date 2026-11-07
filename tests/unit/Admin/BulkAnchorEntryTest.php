<?php
/**
 * Bulk anchor entry screen tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\BulkAnchorEntryPage;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * Fake catalog provider for bulk anchor tests.
 */
class BulkFakeCatalog implements CatalogProvider {

	/**
	 * Items array.
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
	 * Available.
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
	 * @param int $parent_id Parent.
	 * @param int $variation_id Variation.
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
 * History repository double that only answers batched existence lookups.
 */
class BulkFakeHistory extends PriceHistoryRepository {

	/**
	 * Number of `exists_map()` calls.
	 *
	 * @var int
	 */
	public $exists_map_calls = 0;

	/**
	 * Number of per-item `latest()` calls.
	 *
	 * @var int
	 */
	public $latest_calls = 0;

	/**
	 * Anchored keys (`product:variation`).
	 *
	 * @var array<string,bool>
	 */
	public $anchored = array();

	/**
	 * Batched existence lookup.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,bool>
	 */
	public function exists_map( array $product_ids, $price_type ) {
		++$this->exists_map_calls;

		$ids = array_map( 'intval', $product_ids );
		$map = array();

		foreach ( $this->anchored as $key => $value ) {
			$parts = explode( ':', (string) $key );

			if ( in_array( (int) $parts[0], $ids, true ) ) {
				$map[ $key ] = $value;
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
 * @covers \SidrenaCijena\Admin\BulkAnchorEntryPage
 */
class BulkAnchorEntryTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/wc-stubs.php';

		$GLOBALS['sidrena_test_options'] = array();

		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );
	}

	/**
	 * Verify constants.
	 *
	 * @return void
	 */
	public function test_constants() {
		$this->assertSame( 'sidrena-cijena-anchors', BulkAnchorEntryPage::SLUG );
		$this->assertSame( 'sidrena_cijena_bulk_anchors', BulkAnchorEntryPage::ACTION );
		$this->assertSame( 'sidrena_cijena_set_all_anchors_current', BulkAnchorEntryPage::ACTION_ALL_CURRENT );
	}

	/**
	 * Bulk anchor entry renders batch-set form even with empty catalog.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_renders_batch_card() {
		ob_start();
		BulkAnchorEntryPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( BulkAnchorEntryPage::ACTION_ALL_CURRENT, $html );
		$this->assertStringContainsString( 'sidrena-bulk-all-card', $html );
	}

	/**
	 * When items need anchors, bulk table and select all buttons render.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_renders_selection_buttons_when_items_present() {
		$catalog = new BulkFakeCatalog();

		$catalog->items[101] = new CatalogItem(
			array(
				'id'            => 101,
				'name'          => 'Proizvod Bez Sidrene',
				'sku'           => 'PBS-101',
				'regular_price' => '24.99',
				'currency'      => 'EUR',
			)
		);
		Plugin::instance()->set_catalog( $catalog );

		ob_start();
		BulkAnchorEntryPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'sidrena-btn-select-all', $html );
		$this->assertStringContainsString( 'sidrena-btn-deselect-all', $html );
		$this->assertStringContainsString( 'sidrena-select-all', $html );
		$this->assertStringContainsString( 'sidrena-datepicker', $html );
		$this->assertStringContainsString( 'placeholder="d.m.Y."', $html );
		$this->assertStringContainsString( 'Proizvod Bez Sidrene', $html );
		$this->assertStringContainsString( 'PBS-101', $html );
	}

	/**
	 * Existing anchors are resolved with one batched query, never per item.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_uses_batched_existence_lookup() {
		$catalog = new BulkFakeCatalog();

		$catalog->items[101] = new CatalogItem(
			array(
				'id'            => 101,
				'name'          => 'Proizvod Sa Sidrenom',
				'sku'           => 'PSS-101',
				'regular_price' => '24.99',
			)
		);
		$catalog->items[102] = new CatalogItem(
			array(
				'id'            => 102,
				'name'          => 'Proizvod Bez Sidrene',
				'sku'           => 'PBS-102',
				'regular_price' => '9.99',
			)
		);

		$history           = new BulkFakeHistory();
		$history->anchored = array( '101:0' => true );

		Plugin::instance()->set_catalog( $catalog );
		PluginSwap::swap( 'history', $history );

		ob_start();
		BulkAnchorEntryPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Proizvod Bez Sidrene', $html );
		$this->assertStringNotContainsString( 'Proizvod Sa Sidrenom', $html );
		$this->assertStringContainsString( '1 item(s) have no anchor price yet.', $html );
		$this->assertSame( 0, $history->latest_calls, 'The screen must not run per-item anchor lookups.' );
		$this->assertGreaterThanOrEqual( 1, $history->exists_map_calls, 'Anchors must be resolved with batched existence queries.' );
	}

	/**
	 * Pagination slices the filtered (missing) set, not the raw catalog page.
	 *
	 * A page of raw catalog items that are all anchored must not hide later
	 * unanchored items nor print the all-clear.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_paginates_the_filtered_missing_set() {
		$catalog = new BulkFakeCatalog();

		for ( $id = 1; $id <= 60; $id++ ) {
			$catalog->items[ $id ] = new CatalogItem(
				array(
					'id'            => $id,
					'name'          => 'Item ' . $id,
					'sku'           => 'SKU-' . $id,
					'regular_price' => '9.99',
				)
			);
		}

		$history = new BulkFakeHistory();

		for ( $id = 1; $id <= 50; $id++ ) {
			$history->anchored[ $id . ':0' ] = true;
		}

		Plugin::instance()->set_catalog( $catalog );
		PluginSwap::swap( 'history', $history );

		$_GET['paged'] = 1;

		ob_start();
		BulkAnchorEntryPage::render();
		$html = ob_get_clean();

		unset( $_GET['paged'] );

		$this->assertStringContainsString( 'SKU-51', $html );
		$this->assertStringContainsString( 'SKU-60', $html );
		$this->assertStringNotContainsString( 'SKU-50', $html );
		$this->assertStringContainsString( '10 item(s) have no anchor price yet.', $html );
		$this->assertStringNotContainsString( 'Every catalog item has an anchor price recorded.', $html );
	}
}
