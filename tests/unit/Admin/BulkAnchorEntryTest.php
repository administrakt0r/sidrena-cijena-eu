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
use SidrenaCijena\Plugin;

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
		$this->assertStringContainsString( 'Proizvod Bez Sidrene', $html );
		$this->assertStringContainsString( 'PBS-101', $html );
	}
}
