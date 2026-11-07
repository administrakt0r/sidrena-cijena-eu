<?php
/**
 * Unit tests for WooCommerce price-history capture.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\WooCommerce
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Catalog\CatalogProvider;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;
use Titanworks\SidrenaCijena\WooCommerce\PriceHistoryRecorder;

require_once dirname( __DIR__ ) . '/PluginSwap.php';
require_once dirname( __DIR__ ) . '/wc-stubs.php';

/**
 * In-memory catalog rows with call recording.
 */
class RecorderCatalogFake implements CatalogProvider {

	/**
	 * Items served by the fake.
	 *
	 * @var CatalogItem[]
	 */
	public $items = array();

	/**
	 * Recorded lookup calls.
	 *
	 * @var array<int,array<mixed>>
	 */
	public $calls = array();

	/**
	 * Provider source.
	 *
	 * @return string
	 */
	public function get_source() {
		return 'woocommerce';
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
		$this->calls[] = array( 'get_item', (int) $id );

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
		$this->calls[] = array( 'get_variation', (int) $parent_id, (int) $variation_id );

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
 * In-memory price history with insert recording.
 */
class RecorderHistoryFake extends PriceHistoryRepository {

	/**
	 * Recorded rows.
	 *
	 * @var array<int,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Record an insert.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int
	 */
	public function insert( array $data ) {
		$data['id']   = count( $this->rows ) + 1;
		$this->rows[] = $data;

		return $data['id'];
	}

	/**
	 * Latest row for an item/variation/type.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array<mixed>|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		for ( $i = count( $this->rows ) - 1; $i >= 0; $i-- ) {
			$row = $this->rows[ $i ];

			if ( (int) $row['product_id'] === (int) $product_id
				&& (int) $row['variation_id'] === (int) $variation_id
				&& $row['price_type'] === $price_type ) {
				return $row;
			}
		}

		return null;
	}
}

/**
 * Covers capture identity, deduplication and empty-price handling.
 *
 * @covers \Titanworks\SidrenaCijena\WooCommerce\PriceHistoryRecorder
 */
class PriceHistoryRecorderTest extends TestCase {

	/**
	 * Reset shared test state.
	 *
	 * @return void
	 */
	private function reset_state() {
		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );

		$GLOBALS['twbce_test_options']     = array();
		$GLOBALS['twbce_test_wc_products'] = array();
	}

	/**
	 * A new regular price is recorded with the witnessed metadata.
	 *
	 * @return void
	 */
	public function test_records_new_regular_price() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '',
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem(
				array(
					'id'            => 10,
					'sku'           => 'SKU-10',
					'regular_price' => 42.0,
				)
			),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 10 );

		$this->assertCount( 1, $history->rows );
		$row = $history->rows[0];

		$this->assertSame( 10, $row['product_id'] );
		$this->assertSame( 0, $row['variation_id'] );
		$this->assertSame( 'product', $row['item_type'] );
		$this->assertSame( 'SKU-10', $row['sku'] );
		$this->assertSame( 'regular', $row['price_type'] );
		$this->assertSame( 42.0, $row['price'] );
		$this->assertSame( 'EUR', $row['currency'] );
		$this->assertTrue( $row['tax_inclusive'] );
		$this->assertSame( 'woocommerce', $row['source'] );
		$this->assertSame( 0, $row['created_by'] );
		$this->assertSame( 1, preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['valid_from'] ) );
	}

	/**
	 * An unchanged price is not recorded twice.
	 *
	 * @return void
	 */
	public function test_skips_unchanged_price() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '',
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem( array( 'id' => 10 ) ),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		$recorder = new PriceHistoryRecorder();
		$recorder->capture( 10 );
		$recorder->capture( 10 );

		$this->assertCount( 1, $history->rows );
	}

	/**
	 * An empty sale price does not add a closure marker when no sale was recorded.
	 *
	 * @return void
	 */
	public function test_empty_sale_price_does_not_create_an_orphan_closure() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '',
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem( array( 'id' => 10 ) ),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 10 );

		foreach ( $history->rows as $row ) {
			$this->assertSame( 'regular', $row['price_type'] );
		}
		$this->assertCount( 1, $history->rows );
	}

	/**
	 * A sale price change is recorded alongside the regular price.
	 *
	 * @return void
	 */
	public function test_records_sale_price_change() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '35.00',
			'price'         => '35.00',
			'on_sale'       => true,
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem( array( 'id' => 10 ) ),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 10 );

		$this->assertCount( 2, $history->rows );
		$types = array_map(
			static function ( $row ) {
				return $row['price_type'];
			},
			$history->rows
		);
		$this->assertContains( 'regular', $types );
		$this->assertContains( 'sale', $types );
	}

	/**
	 * Removing a recorded sale appends one null-price event and repeated saves dedupe it.
	 *
	 * @return void
	 */
	public function test_sale_removal_appends_one_closure_event() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '35.00',
			'price'         => '35.00',
			'on_sale'       => true,
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array( new CatalogItem( array( 'id' => 10 ) ) );
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		$recorder = new PriceHistoryRecorder();
		$recorder->capture( 10 );

		$GLOBALS['twbce_test_wc_products'][10]['sale_price'] = '';
		$GLOBALS['twbce_test_wc_products'][10]['price']      = '42.00';
		$GLOBALS['twbce_test_wc_products'][10]['on_sale']    = false;
		$recorder->capture( 10 );
		$recorder->capture( 10 );

		$sale_rows = array_values(
			array_filter(
				$history->rows,
				static function ( $row ) {
					return 'sale' === $row['price_type'];
				}
			)
		);

		$this->assertCount( 2, $sale_rows );
		$this->assertSame( 35.0, $sale_rows[0]['price'] );
		$this->assertNull( $sale_rows[1]['price'] );
		$this->assertSame( 'price_interval_closed', $sale_rows[1]['metadata']['event'] );
	}

	/**
	 * Scheduled sale boundaries are stored in site time, including DST and the inclusive end date.
	 *
	 * @return void
	 */
	public function test_scheduled_sale_boundaries_use_site_timezone() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'                => 10,
			'type'              => 'simple',
			'regular_price'     => '42.00',
			'sale_price'        => '35.00',
			'price'             => '35.00',
			'on_sale'           => true,
			'date_on_sale_from' => new \DateTimeImmutable( '2026-03-29 01:00:00', new \DateTimeZone( 'UTC' ) ),
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array( new CatalogItem( array( 'id' => 10 ) ) );
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		$recorder = new PriceHistoryRecorder();
		$recorder->begin_scheduled_sale_start( 10 );
		$recorder->capture( 10 );
		$recorder->end_scheduled_sale_transition( 10 );

		$sale_row = $history->latest( 10, 0, 'sale' );
		$this->assertSame( '2026-03-29 03:00:00', $sale_row['valid_from'] );

		$GLOBALS['twbce_test_wc_products'][10]['on_sale']         = false;
		$GLOBALS['twbce_test_wc_products'][10]['price']           = '42.00';
		$GLOBALS['twbce_test_wc_products'][10]['date_on_sale_to'] = new \DateTimeImmutable( '2026-03-29 21:59:59', new \DateTimeZone( 'UTC' ) );
		$recorder->begin_scheduled_sale_end( 10 );
		$recorder->capture( 10 );

		$closed_row = $history->latest( 10, 0, 'sale' );
		$this->assertNull( $closed_row['price'] );
		$this->assertSame( '2026-03-30 00:00:00', $closed_row['valid_from'] );
	}

	/**
	 * A scheduled start save does not close the previous sale before WooCommerce updates the active price.
	 *
	 * @return void
	 */
	public function test_scheduled_start_waits_for_applied_active_price() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'                => 10,
			'type'              => 'simple',
			'regular_price'     => '42.00',
			'sale_price'        => '35.00',
			'price'             => '35.00',
			'on_sale'           => true,
			'date_on_sale_from' => new \DateTimeImmutable( '2026-09-01 10:00:00', new \DateTimeZone( 'UTC' ) ),
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array( new CatalogItem( array( 'id' => 10 ) ) );
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		$recorder = new PriceHistoryRecorder();
		$recorder->capture( 10 );

		$GLOBALS['twbce_test_wc_products'][10]['sale_price'] = '30.00';
		$recorder->begin_scheduled_sale_start( 10 );
		$recorder->capture( 10 );

		$sale_rows = array_values(
			array_filter(
				$history->rows,
				static function ( $row ) {
					return 'sale' === $row['price_type'];
				}
			)
		);
		$this->assertCount( 1, $sale_rows );

		$GLOBALS['twbce_test_wc_products'][10]['price'] = '30.00';
		$recorder->capture( 10 );
		$recorder->end_scheduled_sale_transition( 10 );

		$sale_rows = array_values(
			array_filter(
				$history->rows,
				static function ( $row ) {
					return 'sale' === $row['price_type'];
				}
			)
		);
		$this->assertCount( 2, $sale_rows );
		$this->assertSame( 30.0, $sale_rows[1]['price'] );
		$this->assertSame( '2026-09-01 12:00:00', $sale_rows[1]['valid_from'] );
	}

	/**
	 * Successive sale reductions on one local day are retained as separate witnessed prices.
	 *
	 * @return void
	 */
	public function test_records_successive_same_day_sale_reductions() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][10] = array(
			'id'            => 10,
			'type'          => 'simple',
			'regular_price' => '42.00',
			'sale_price'    => '35.00',
			'price'         => '35.00',
			'on_sale'       => true,
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array( new CatalogItem( array( 'id' => 10 ) ) );
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		$recorder = new PriceHistoryRecorder();
		$recorder->capture( 10 );
		$GLOBALS['twbce_test_wc_products'][10]['sale_price'] = '30.00';
		$GLOBALS['twbce_test_wc_products'][10]['price']      = '30.00';
		$recorder->capture( 10 );
		$GLOBALS['twbce_test_wc_products'][10]['sale_price'] = '25.00';
		$GLOBALS['twbce_test_wc_products'][10]['price']      = '25.00';
		$recorder->capture( 10 );

		$sale_rows = array_values(
			array_filter(
				$history->rows,
				static function ( $row ) {
					return 'sale' === $row['price_type'];
				}
			)
		);

		$this->assertCount( 3, $sale_rows );
		$this->assertSame( array( 35.0, 30.0, 25.0 ), array_column( $sale_rows, 'price' ) );
	}

	/**
	 * Variations record exact variation identity under the parent product.
	 *
	 * @return void
	 */
	public function test_records_variation_identity() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][12] = array(
			'id'            => 12,
			'parent_id'     => 10,
			'type'          => 'variation',
			'regular_price' => '5.00',
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem(
				array(
					'id'           => 10,
					'variation_id' => 12,
					'parent_id'    => 10,
				)
			),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 12 );

		$this->assertCount( 1, $history->rows );
		$this->assertSame( 10, $history->rows[0]['product_id'] );
		$this->assertSame( 12, $history->rows[0]['variation_id'] );
		$this->assertContains( array( 'get_variation', 10, 12 ), $catalog->calls );
	}

	/**
	 * Services are recorded with the service item type.
	 *
	 * @return void
	 */
	public function test_records_service_item_type() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][30] = array(
			'id'            => 30,
			'type'          => 'simple',
			'regular_price' => '25.00',
		);

		$catalog        = new RecorderCatalogFake();
		$catalog->items = array(
			new CatalogItem(
				array(
					'id'   => 30,
					'type' => 'service',
				)
			),
		);
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 30 );

		$this->assertCount( 1, $history->rows );
		$this->assertSame( 'service', $history->rows[0]['item_type'] );
	}

	/**
	 * Unknown products are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_unknown_products() {
		$this->reset_state();

		$catalog = new RecorderCatalogFake();
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 999 );

		$this->assertCount( 0, $history->rows );
		$this->assertSame( array(), $catalog->calls );
	}

	/**
	 * Products missing from the catalog record nothing.
	 *
	 * @return void
	 */
	public function test_ignores_items_missing_from_catalog() {
		$this->reset_state();

		$GLOBALS['twbce_test_wc_products'][20] = array(
			'id'            => 20,
			'type'          => 'simple',
			'regular_price' => '10.00',
		);

		$catalog = new RecorderCatalogFake();
		PluginSwap::swap( 'catalog', $catalog );

		$history = new RecorderHistoryFake();
		PluginSwap::swap( 'history', $history );

		( new PriceHistoryRecorder() )->capture( 20 );

		$this->assertCount( 0, $history->rows );
		$this->assertContains( array( 'get_item', 20 ), $catalog->calls );
	}
}
