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

	/**
	 * When a sale ends or expires, the 30-day display cleanly disappears, history remains intact,
	 * and subsequent 30-day calculations incorporate the prior promotional price.
	 *
	 * @return void
	 */
	public function test_sale_end_lifecycle_cleans_display_and_preserves_history_for_subsequent_calculation() {
		$item  = $this->create_item( 'Trajno mlijeko', 'MLJ-TRAJNO', 2.00 );
		$today = Dates::today();

		// Seed regular price 35 days ago.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 2.00,
				'valid_from' => Dates::add_days( $today, -35 ),
				'source'     => 'manual',
			)
		);

		// Sale 1: started 20 days ago, ended 5 days ago with promotional price 1.40.
		$sale_1_start = Dates::add_days( $today, -20 );
		$sale_1_end   = Dates::add_days( $today, -5 );

		$sale_1_id = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'sale_name'   => 'Prva akcija',
				'start_date'  => $sale_1_start,
				'end_date'    => $sale_1_end,
				'promo_price' => 1.40,
			)
		);
		$this->assertIsInt( $sale_1_id );

		// Record the applied promotional price for Sale 1 in history.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 1.40,
				'valid_from' => $sale_1_start,
				'valid_to'   => $sale_1_end . ' 23:59:59',
				'source'     => 'manual',
			)
		);

		// Record closure / return to regular price after Sale 1 ended.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => null,
				'valid_from' => Dates::add_days( $today, -4 ),
				'source'     => 'manual',
				'metadata'   => array( 'event' => 'price_interval_closed' ),
			)
		);

		// 1. Verify that now (sale ended 5 days ago), no active sale applies:
		$this->assertNull( $this->service->active( $item->id ) );

		// 2. Verify that the 30-day lowest price display cleanly disappears:
		$display  = new \SidrenaCijena\WooCommerce\Display\PriceDisplay( Plugin::instance()->anchors() );
		$rendered = $display->render_item( $item, 'single' );
		$this->assertStringNotContainsString( 'sidrena-cijena--30-day', $rendered );

		// 3. Verify historical records in wp_sidrena_price_history remain permanently intact:
		$history_rows = $this->history->query(
			array(
				'product_id' => $item->id,
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);
		$this->assertGreaterThanOrEqual( 3, count( $history_rows ) );

		$prices_recorded = array_column( $history_rows, 'price' );
		$this->assertContains( 1.40, array_map( 'floatval', array_filter( $prices_recorded ) ) );

		// 4. Create Sale 2 starting today:
		// The 30-day window covers today-30 to today-1, which includes Sale 1's 1.40 promotional price!
		$sale_2_id = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'sale_name'   => 'Druga akcija',
				'start_date'  => $today,
				'end_date'    => Dates::add_days( $today, 5 ),
				'promo_price' => 1.20,
			)
		);
		$this->assertIsInt( $sale_2_id );

		$sale_2 = $this->sales->get( $sale_2_id );
		$this->assertSame( 1.40, (float) $sale_2['lowest_30_day_price'], 'Subsequent 30-day calculation must incorporate prior promo price from history.' );
	}

	/**
	 * The refresh_statuses method transitions sales and flushes RestCache.
	 *
	 * @return void
	 */
	public function test_refresh_statuses_transitions_and_flushes_rest_cache() {
		$item  = $this->create_item( 'Sok jabuka', 'SOK-JAB', 3.00 );
		$today = Dates::today();

		// 1. Create a scheduled sale whose start date is today:
		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'  => 'akcija',
				'start_date' => $today,
				'end_date'   => Dates::add_days( $today, 3 ),
			)
		);

		$sale = $this->sales->get( $sale_id );
		$this->assertSame( 'scheduled', $sale['status'] );

		// Populate RestCache.
		$rest_cache = Plugin::instance()->rest_cache();
		$args       = array(
			'page'     => 'cjenik',
			'location' => 99,
		);
		$key_before = $rest_cache->key( $args );
		$rest_cache->set( $key_before, 'cached-html' );
		$this->assertSame( 'cached-html', $rest_cache->get( $key_before ) );

		$gen_before = $rest_cache->generation();

		// Transition statuses.
		$updated = $this->sales->refresh_statuses();
		$this->assertGreaterThanOrEqual( 1, $updated );

		// Verify status transitioned.
		$sale_refreshed = $this->sales->get( $sale_id );
		$this->assertSame( 'active', $sale_refreshed['status'] );

		// Verify RestCache generation bumped and cache flushed.
		$gen_after = $rest_cache->generation();
		$this->assertGreaterThan( $gen_before, $gen_after );

		$key_after = $rest_cache->key( $args );
		$this->assertNotSame( $key_before, $key_after );
		$this->assertNull( $rest_cache->get( $key_after ) );
	}
}
