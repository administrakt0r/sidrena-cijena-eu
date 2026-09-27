<?php
/**
 * Integration tests for LowestPriceExplainer.
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
use SidrenaCijena\Pricing\LowestPriceExplainer;
use SidrenaCijena\Pricing\SpecialSaleService;
use SidrenaCijena\Support\Dates;

/**
 * Tests that administrator calculation explanations agree with persisted database results.
 */
class Test_Lowest_Price_Explanation extends WP_UnitTestCase {

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
	 * Explainer under test.
	 *
	 * @var LowestPriceExplainer
	 */
	private $explainer;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$this->history   = new PriceHistoryRepository();
		$this->sales     = new SpecialSaleRepository();
		$this->service   = new SpecialSaleService( $this->sales, $this->history, new LowestPrice30DayCalculator() );
		$this->explainer = Plugin::instance()->lowest_price_explainer();
	}

	/**
	 * Helper to create a standalone catalog item.
	 *
	 * @param string $name  Item title.
	 * @param string $sku   Item SKU.
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
	 * Scenario 1: Multiple successive reductions agree with calculation.
	 *
	 * @return void
	 */
	public function test_explanation_agrees_with_multiple_successive_reductions() {
		$item  = $this->create_item( 'Maslinovo ulje ekstra', 'MASL-EKSTRA', 20.00 );
		$today = Dates::today();

		// Seed initial price 60 days ago.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 20.00,
				'valid_from' => Dates::add_days( $today, -60 ),
				'source'     => 'manual',
			)
		);

		// First reduction 20 days ago.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 16.00,
				'valid_from' => Dates::add_days( $today, -20 ),
				'valid_to'   => Dates::add_days( $today, -11 ),
				'source'     => 'manual',
			)
		);

		// Second reduction 10 days ago.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 13.50,
				'valid_from' => Dates::add_days( $today, -10 ),
				'valid_to'   => Dates::add_days( $today, -1 ),
				'source'     => 'manual',
			)
		);

		// Record special sale starting today.
		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'sale_name'   => 'Jesenska akcija',
				'start_date'  => $today,
				'end_date'    => Dates::add_days( $today, 7 ),
				'promo_price' => 11.00,
			)
		);
		$this->assertIsInt( $sale_id );

		$stored_sale = $this->sales->get( $sale_id );
		$this->assertNotNull( $stored_sale );
		$this->assertSame( 13.50, (float) $stored_sale['lowest_30_day_price'] );

		// Build explanation.
		$explanation = $this->explainer->explain( $item, $stored_sale );

		// Explanation strictly agrees with calculation and stored record.
		$this->assertSame( (float) $stored_sale['lowest_30_day_price'], $explanation['lowest_price'] );
		$this->assertSame( 13.50, $explanation['lowest_price'] );
		$this->assertContains( 'successive_reductions', $explanation['conditions'] );
		$this->assertNotNull( $explanation['determining_record'] );
		$this->assertSame( 13.50, $explanation['determining_record']['price'] );
		$this->assertGreaterThanOrEqual( 3, count( $explanation['source_records'] ) );
	}

	/**
	 * Scenario 2: Short history (< 30 days) agrees with calculation.
	 *
	 * @return void
	 */
	public function test_explanation_agrees_with_short_history() {
		$item  = $this->create_item( 'Novi proizvod', 'NOV-PROD', 15.00 );
		$today = Dates::today();

		// Product only existed for 12 days.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 15.00,
				'valid_from' => Dates::add_days( $today, -12 ),
				'source'     => 'manual',
			)
		);

		$sale_id = $this->service->create(
			$item,
			array(
				'sale_type'   => 'snizenje',
				'start_date'  => $today,
				'end_date'    => Dates::add_days( $today, 5 ),
				'promo_price' => 10.00,
			)
		);
		$this->assertIsInt( $sale_id );

		$stored_sale = $this->sales->get( $sale_id );
		$explanation = $this->explainer->explain( $item, $stored_sale );

		$this->assertSame( (float) $stored_sale['lowest_30_day_price'], $explanation['lowest_price'] );
		$this->assertSame( 15.00, $explanation['lowest_price'] );
		$this->assertTrue( $explanation['is_short'] );
		$this->assertLessThan( 30, $explanation['coverage_days'] );
		$this->assertContains( 'short_history', $explanation['conditions'] );
	}

	/**
	 * Scenario 3: No history (missing-history fallback) returns null and flags condition.
	 *
	 * @return void
	 */
	public function test_explanation_agrees_with_no_history() {
		$item  = $this->create_item( 'Artikl bez povijesti', 'BEZ-POV', 25.00 );
		$today = Dates::today();

		$explanation = $this->explainer->explain( $item, $today );

		$this->assertNull( $explanation['lowest_price'] );
		$this->assertFalse( $explanation['has_history'] );
		$this->assertContains( 'no_history', $explanation['conditions'] );
		$this->assertEmpty( $explanation['source_records'] );
	}

	/**
	 * Scenario 4: Imported or manual baseline agrees with calculation and attributes source.
	 *
	 * @return void
	 */
	public function test_explanation_agrees_with_imported_or_manual_baseline() {
		$item  = $this->create_item( 'Uvezeni artikl', 'IMP-ART', 8.50 );
		$today = Dates::today();

		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 8.50,
				'valid_from' => Dates::add_days( $today, -45 ),
				'source'     => 'import',
			)
		);

		$sale_id     = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'start_date'  => $today,
				'promo_price' => 6.00,
			)
		);
		$stored_sale = $this->sales->get( $sale_id );
		$explanation = $this->explainer->explain( $item, $stored_sale );

		$this->assertSame( (float) $stored_sale['lowest_30_day_price'], $explanation['lowest_price'] );
		$this->assertSame( 8.50, $explanation['lowest_price'] );
		$this->assertContains( 'imported_baseline', $explanation['conditions'] );
		$this->assertSame( 'import', $explanation['determining_record']['source'] );
	}

	/**
	 * Scenario 5: A sale that ended recently is factored into the 30-day calculation.
	 *
	 * @return void
	 */
	public function test_explanation_agrees_with_sale_that_ended_recently() {
		$item  = $this->create_item( 'Cokolada za kuhanje', 'COK-KUH', 4.00 );
		$today = Dates::today();

		// Baseline regular price 4.00 from 50 days ago.
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'regular',
				'price'      => 4.00,
				'valid_from' => Dates::add_days( $today, -50 ),
				'source'     => 'manual',
			)
		);

		// Prior sale 20 days ago that ended 10 days ago (price 2.80).
		$this->history->insert(
			array(
				'product_id' => $item->id,
				'item_type'  => 'product',
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 2.80,
				'valid_from' => Dates::add_days( $today, -20 ),
				'valid_to'   => Dates::add_days( $today, -10 ),
				'source'     => 'manual',
			)
		);

		// New sale starting today.
		$sale_id     = $this->service->create(
			$item,
			array(
				'sale_type'   => 'akcija',
				'start_date'  => $today,
				'promo_price' => 3.20,
			)
		);
		$stored_sale = $this->sales->get( $sale_id );

		// The lowest price in the 30 days before today must be 2.80 from the recently ended sale.
		$this->assertSame( 2.80, (float) $stored_sale['lowest_30_day_price'] );

		$explanation = $this->explainer->explain( $item, $stored_sale );
		$this->assertSame( 2.80, $explanation['lowest_price'] );
		$this->assertContains( 'recent_sale_ended', $explanation['conditions'] );
		$this->assertSame( 2.80, $explanation['determining_record']['price'] );
		$this->assertSame( 'sale', $explanation['determining_record']['price_type'] );
	}

	/**
	 * Rendered HTML contains identity, calculation window and source records table.
	 *
	 * @return void
	 */
	public function test_rendered_html_output() {
		$item        = $this->create_item( 'Jabuke Idared', 'JAB-IDA', 1.80 );
		$explanation = $this->explainer->explain( $item, Dates::today() );
		$html        = $this->explainer->render_html( $explanation );

		$this->assertStringContainsString( 'JAB-IDA', $html );
		$this->assertStringContainsString( 'sidrena-cijena-summary-box', $html );
		$this->assertStringContainsString( 'sidrena-cijena-source-records', $html );
	}
}
