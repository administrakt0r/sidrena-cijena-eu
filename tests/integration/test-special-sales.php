<?php
/**
 * Special sale integration tests.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\LowestPrice30DayCalculator;
use SidrenaCijena\Pricing\SpecialSaleService;
use SidrenaCijena\Support\Dates;

/**
 * Covers sale classification, window persistence and append-only recomputation.
 */
class Test_Special_Sales extends WP_UnitTestCase {

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	private $history;

	/**
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository
	 */
	private $sales;

	/**
	 * Special sale service.
	 *
	 * @var SpecialSaleService
	 */
	private $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$this->history = new PriceHistoryRepository();
		$this->sales   = new SpecialSaleRepository();
		$this->service = new SpecialSaleService( $this->sales, $this->history, new LowestPrice30DayCalculator() );
	}

	/**
	 * Create a standalone item.
	 *
	 * @param string $name  Name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price.
	 * @return CatalogItem
	 */
	private function create_item( $name, $sku, $price ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );

		$item = Plugin::instance()->catalog()->get_item( $post_id );

		$this->assertInstanceOf( CatalogItem::class, $item );

		return $item;
	}

	/**
	 * Seed history for an item.
	 *
	 * @param CatalogItem $item Item.
	 * @return void
	 */
	private function seed_history( CatalogItem $item ) {
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 10.00,
				'valid_from' => '2026-08-01',
				'source'     => 'manual',
			)
		);

		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 8.00,
				'valid_from' => '2026-09-10',
				'valid_to'   => '2026-09-20',
				'source'     => 'manual',
			)
		);
	}

	/**
	 * Batched storefront lookup preserves active_for_item's newest-start winner.
	 *
	 * @return void
	 */
	public function test_active_map_matches_item_lookup_when_sales_overlap() {
		$item = $this->create_item( 'Overlapping sale item', 'SALE-OVERLAP', 10.00 );
		$now  = Dates::now_site();
		$this->sales->insert(
			array(
				'product_id'          => $item->id,
				'sale_type'           => 'promo',
				'start_date'          => $now->modify( '-2 hours' )->format( 'Y-m-d H:i:s' ),
				'lowest_30_day_price' => 4.00,
				'status'              => 'active',
			)
		);
		$this->sales->insert(
			array(
				'product_id'          => $item->id,
				'sale_type'           => 'promo',
				'start_date'          => $now->modify( '-1 hour' )->format( 'Y-m-d H:i:s' ),
				'lowest_30_day_price' => 5.00,
				'status'              => 'active',
			)
		);

		$expected = $this->sales->active_for_item( $item->id, 0 );
		$actual   = $this->sales->active_map()[ $item->id . ':0' ];

		$this->assertSame( (int) $expected['id'], (int) $actual['id'] );
		$this->assertSame( 5.0, (float) $actual['lowest_30_day_price'] );
	}

	/**
	 * Creating a sale stores the calculated window and lowest price.
	 *
	 * @return void
	 */
	public function test_create_stores_window_and_lowest_price() {
		$item = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );
		$this->seed_history( $item );

		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'sale_name'   => 'Jesenska akcija',
				'start_date'  => '2026-10-01',
				'end_date'    => '2026-10-15',
				'promo_price' => 7.50,
			)
		);

		$this->assertIsInt( $sale_id );

		$sale = $this->sales->get( $sale_id );

		$this->assertSame( 'akcija', $sale['sale_type'] );
		$this->assertSame( 'Jesenska akcija', $sale['sale_name'] );
		$this->assertSame( '2026-10-15 23:59:59', $sale['end_date'] );
		$this->assertSame( 8.0, (float) $sale['lowest_30_day_price'] );
		$this->assertSame( '2026-09-01', $sale['window_start'] );
		$this->assertSame( '2026-09-30', $sale['window_end'] );
		$this->assertSame( 7.5, (float) $sale['promo_price'] );
		$this->assertSame( 'scheduled', $sale['status'] );

		$rows = $this->history->query(
			array(
				'product_id' => $item->id,
				'price_type' => 'lowest_30_day',
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 8.0, (float) $rows[0]['price'] );

		$metadata = json_decode( (string) $rows[0]['metadata'], true );
		$this->assertSame( $sale_id, (int) $metadata['sale_id'] );
	}

	/**
	 * A promotion without a type is never treated as a special sale.
	 *
	 * @return void
	 */
	public function test_unclassified_sale_is_rejected() {
		$item = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );

		$result = $this->service->create(
			$item,
			array(
				'start_date'  => '2026-10-01',
				'promo_price' => 7.50,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'missing_sale_type', $result->get_error_code() );
		$this->assertCount( 0, $this->sales->for_item( $item->id ) );
	}

	/**
	 * Invalid dates are rejected.
	 *
	 * @return void
	 */
	public function test_invalid_dates_are_rejected() {
		$item = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );

		$result = $this->service->create(
			$item,
			array(
				'sale_type'  => 'akcija',
				'start_date' => '01.10.2026.',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_sale_start_date', $result->get_error_code() );
	}

	/**
	 * Recalculation appends history and never rewrites earlier rows.
	 *
	 * @return void
	 */
	public function test_recompute_appends_history_without_rewriting() {
		$item = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );
		$this->seed_history( $item );

		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'  => 'akcija',
				'start_date' => '2026-10-01',
			)
		);

		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'historical',
				'price'      => 6.50,
				'valid_from' => '2026-09-05',
				'source'     => 'manual',
			)
		);

		$lowest = $this->service->recompute( $item, $sale_id );

		$this->assertSame( 6.5, $lowest );

		$sale = $this->sales->get( $sale_id );
		$this->assertSame( 6.5, (float) $sale['lowest_30_day_price'] );

		$rows = $this->history->query(
			array(
				'product_id' => $item->id,
				'price_type' => 'lowest_30_day',
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);

		$this->assertCount( 2, $rows, 'A new snapshot row is appended.' );
		$this->assertSame( 8.0, (float) $rows[0]['price'], 'The earlier snapshot is preserved.' );
		$this->assertSame( 6.5, (float) $rows[1]['price'], 'The newest snapshot reflects the recalculation.' );
	}

	/**
	 * Status transitions and cancellation follow the sale dates.
	 *
	 * @return void
	 */
	public function test_status_transitions_and_cancellation() {
		$item = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );

		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'  => 'rasprodaja',
				'start_date' => Dates::today(),
			)
		);

		$this->assertIsInt( $sale_id );

		$this->sales->refresh_statuses();

		$sale = $this->sales->get( $sale_id );
		$this->assertSame( 'active', $sale['status'] );

		$active = $this->service->active( $item->id );
		$this->assertNotNull( $active );
		$this->assertSame( $sale_id, (int) $active['id'] );

		$this->assertTrue( $this->service->cancel( $sale_id ) );
		$this->assertNull( $this->service->active( $item->id ) );
	}

	/**
	 * The end date is inclusive: a one-day sale is active for that whole day.
	 *
	 * @return void
	 */
	public function test_sale_is_active_on_its_end_day() {
		$item  = $this->create_item( 'Kava mljevena', 'KAV-250', 10.00 );
		$today = Dates::today();

		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'  => 'akcija',
				'start_date' => $today,
				'end_date'   => $today,
			)
		);

		$this->assertIsInt( $sale_id );

		$sale = $this->sales->get( $sale_id );
		$this->assertSame( $today . ' 23:59:59', $sale['end_date'] );

		$this->sales->refresh_statuses();
		$this->assertSame( 'active', $this->sales->get( $sale_id )['status'] );

		$active = $this->service->active( $item->id );
		$this->assertNotNull( $active );
		$this->assertSame( $sale_id, (int) $active['id'] );
	}
}
