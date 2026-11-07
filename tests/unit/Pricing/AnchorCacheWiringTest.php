<?php
/**
 * Unit tests for the anchor cache wiring in the plugin container.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Pricing
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Pricing\AnchorCache;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * In-memory history fake that counts single-item lookups.
 */
class WiringFakeHistory extends PriceHistoryRepository {

	/**
	 * Number of latest() calls.
	 *
	 * @var int
	 */
	public $latest_calls = 0;

	/**
	 * Stored anchor row.
	 *
	 * @var array|null
	 */
	public $stored = null;

	/**
	 * Single-item lookup.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		unset( $product_id, $variation_id, $price_type );

		++$this->latest_calls;

		return $this->stored;
	}

	/**
	 * Record an insert.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int
	 */
	public function insert( array $data ) {
		$this->stored = array_merge(
			array( 'id' => 11 ),
			$data
		);

		return 11;
	}

	/**
	 * Record a correction insert.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int
	 */
	public function insert_correction( array $data ) {
		$this->stored = array_merge(
			array( 'id' => 12 ),
			$data
		);

		return 12;
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Plugin::anchors
 * @covers \Titanworks\SidrenaCijena\Plugin::invalidate_anchor_cache
 */
class AnchorCacheWiringTest extends TestCase {

	/**
	 * History fake behind the wired service.
	 *
	 * @var WiringFakeHistory
	 */
	protected $history;

	/**
	 * Swap the container behind the anchors service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options']    = array();
		$GLOBALS['twbce_test_transients'] = array();

		$this->history         = new WiringFakeHistory();
		$this->history->stored = array(
			'id'             => 5,
			'price'          => '7.49',
			'reference_date' => '2026-09-10',
		);

		PluginSwap::swap( 'history', $this->history );
		PluginSwap::swap( 'anchor_cache', new AnchorCache() );
		PluginSwap::swap( 'anchors', null );
	}

	/**
	 * Restore the container.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'history', null );
		PluginSwap::swap( 'anchor_cache', null );
		PluginSwap::swap( 'anchors', null );

		parent::tearDown();
	}

	/**
	 * Build a minimal catalog item.
	 *
	 * @return CatalogItem
	 */
	protected function item() {
		return new CatalogItem(
			array(
				'id'            => 7,
				'name'          => 'Kava 250g',
				'regular_price' => '7.49',
			)
		);
	}

	/**
	 * The container service shares the anchor cache across lookups.
	 *
	 * @return void
	 */
	public function test_plugin_anchors_service_caches_lookups() {
		$service = Plugin::instance()->anchors();

		$first  = $service->get_anchor( $this->item() );
		$second = $service->get_anchor( $this->item() );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->history->latest_calls );
	}

	/**
	 * The anchor-recorded hook forgets the stale cached anchor.
	 *
	 * @return void
	 */
	public function test_anchor_recorded_hook_invalidates_cache() {
		$service = Plugin::instance()->anchors();

		$this->assertSame( '7.49', $service->get_anchor( $this->item() )['price'] );
		$this->assertSame( 1, $this->history->latest_calls );

		$this->history->stored = array(
			'id'             => 6,
			'price'          => '6.99',
			'reference_date' => '2026-09-12',
		);

		Plugin::instance()->invalidate_anchor_cache( 6, 7, 0 );

		$this->assertSame( '6.99', $service->get_anchor( $this->item() )['price'] );
		$this->assertSame( 2, $this->history->latest_calls );
	}

	/**
	 * Recording through the wired service invalidates its cached anchor.
	 *
	 * @return void
	 */
	public function test_record_invalidates_cached_anchor() {
		$service = Plugin::instance()->anchors();

		$this->assertSame( '7.49', $service->get_anchor( $this->item() )['price'] );

		$id = $service->record(
			$this->item(),
			'6.99',
			array(
				'reference_date'    => '2026-09-12',
				'correction_reason' => 'Supplier correction',
			)
		);

		$this->assertSame( 12, $id );
		$this->assertEquals( '6.99', $service->get_anchor( $this->item() )['price'] );
	}
}
