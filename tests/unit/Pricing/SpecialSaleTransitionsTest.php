<?php
/**
 * Unit tests for time, daylight-saving transitions, cache invalidation, and sale-end suppression.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Pricing
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Api\RestCache;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Pricing\AnchorPriceService;
use Titanworks\SidrenaCijena\Pricing\LowestPrice30DayCalculator;
use Titanworks\SidrenaCijena\Support\Dates;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;
use Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay;

/**
 * Verifies time, cache and sale-end behavior (Task 8).
 *
 * @covers \Titanworks\SidrenaCijena\Pricing\LowestPrice30DayCalculator
 * @covers \Titanworks\SidrenaCijena\Support\Dates
 * @covers \Titanworks\SidrenaCijena\Database\SpecialSaleRepository
 * @covers \Titanworks\SidrenaCijena\Plugin
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay
 */
class SpecialSaleTransitionsTest extends TestCase {

	/**
	 * Reset test doubles before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wpdb']                  = new \wpdb();
		$GLOBALS['twbce_test_options']    = array();
		$GLOBALS['twbce_test_transients'] = array();
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'special_sales', null );
		PluginSwap::swap( 'history', null );

		parent::tearDown();
	}

	/**
	 * Daylight saving spring forward (23-hour day) does not drift the 30-day window.
	 *
	 * In Europe/Zagreb, the clock jumps forward on Sunday 2026-03-29. A sale starting
	 * on 2026-04-15 must cover exactly 30 calendar days (2026-03-16 to 2026-04-14).
	 *
	 * @return void
	 */
	public function test_dst_spring_transition_maintains_exact_30_calendar_day_window() {
		$calculator = new LowestPrice30DayCalculator();
		$window     = $calculator->window( '2026-04-15' );

		$this->assertNotNull( $window );
		$this->assertSame( '2026-03-16', $window['window_start'] );
		$this->assertSame( '2026-04-14', $window['window_end'] );

		// 30 whole calendar days:
		$this->assertSame( 30, Dates::diff_days( $window['window_start'], '2026-04-15' ) );
		$this->assertSame( 29, Dates::diff_days( $window['window_start'], $window['window_end'] ) );
	}

	/**
	 * Daylight saving autumn backward (25-hour day) does not drift the 30-day window.
	 *
	 * In Europe/Zagreb, the clock jumps back on Sunday 2026-10-25. A sale starting
	 * on 2026-11-10 must cover exactly 30 calendar days (2026-10-11 to 2026-11-09).
	 *
	 * @return void
	 */
	public function test_dst_autumn_transition_maintains_exact_30_calendar_day_window() {
		$calculator = new LowestPrice30DayCalculator();
		$window     = $calculator->window( '2026-11-10' );

		$this->assertNotNull( $window );
		$this->assertSame( '2026-10-11', $window['window_start'] );
		$this->assertSame( '2026-11-09', $window['window_end'] );

		// 30 whole calendar days:
		$this->assertSame( 30, Dates::diff_days( $window['window_start'], '2026-11-10' ) );
		$this->assertSame( 29, Dates::diff_days( $window['window_start'], $window['window_end'] ) );
	}

	/**
	 * Midnight boundaries and transitions at scheduled start and end dates.
	 *
	 * A sale starting 2026-10-01 00:00:00 and ending 2026-10-15 23:59:59 is:
	 * - Inactive at 2026-09-30 23:59:59
	 * - Active at 2026-10-01 00:00:00
	 * - Active at 2026-10-15 23:59:59
	 * - Inactive at 2026-10-16 00:00:00
	 *
	 * @return void
	 */
	public function test_midnight_boundary_transitions_at_scheduled_start_and_end() {
		$sale = array(
			'id'                  => 1,
			'product_id'          => 42,
			'variation_id'        => 0,
			'sale_type'           => 'akcija',
			'start_date'          => '2026-10-01 00:00:00',
			'end_date'            => '2026-10-15 23:59:59',
			'lowest_30_day_price' => 8.0,
			'status'              => 'active',
		);

		$repo = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'for_item' ) )
			->getMock();

		$repo->method( 'for_item' )->willReturn( array( $sale ) );

		// Before start.
		$this->assertNull( $repo->active_for_item( 42, 0, '2026-09-30 23:59:59' ) );

		// Exactly at start.
		$active_start = $repo->active_for_item( 42, 0, '2026-10-01 00:00:00' );
		$this->assertNotNull( $active_start );
		$this->assertSame( 1, $active_start['id'] );

		// Last second of end day.
		$active_end = $repo->active_for_item( 42, 0, '2026-10-15 23:59:59' );
		$this->assertNotNull( $active_end );
		$this->assertSame( 1, $active_end['id'] );

		// One second after end.
		$this->assertNull( $repo->active_for_item( 42, 0, '2026-10-16 00:00:00' ) );
	}

	/**
	 * Transitioning sale statuses fires twbce_cijena_special_sale_changed.
	 *
	 * @return void
	 */
	public function test_refresh_statuses_fires_change_action_when_sales_transition() {
		$action_fired = false;
		add_action(
			'twbce_cijena_special_sale_changed',
			static function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$wpdb = $this->getMockBuilder( \wpdb::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'query', 'prepare' ) )
			->getMock();
		$wpdb->method( 'prepare' )->willReturnCallback(
			static function ( $query ) {
				return $query;
			}
		);
		$wpdb->method( 'query' )->willReturn( 1 );
		$GLOBALS['wpdb'] = $wpdb;

		$repo    = new SpecialSaleRepository();
		$updated = $repo->refresh_statuses( '2026-10-16 00:00:00' );

		$this->assertSame( 2, $updated );
		$this->assertTrue( $action_fired, 'twbce_cijena_special_sale_changed must fire when statuses transition.' );
	}

	/**
	 * Plugin on_special_sale_changed flushes the REST cache and increments the generation marker.
	 *
	 * @return void
	 */
	public function test_on_special_sale_changed_flushes_rest_cache_and_cleans_transients() {
		$plugin     = Plugin::instance();
		$rest_cache = $plugin->rest_cache();

		$args       = array(
			'page'     => 'cjenik',
			'location' => 1,
		);
		$key_before = $rest_cache->key( $args );
		$rest_cache->set( $key_before, 'cached-response' );

		$this->assertSame( 'cached-response', $rest_cache->get( $key_before ) );

		$gen_before = $rest_cache->generation();
		$plugin->on_special_sale_changed( 10, 42, 0 );
		$gen_after = $rest_cache->generation();

		$this->assertGreaterThan( $gen_before, $gen_after );

		$key_after = $rest_cache->key( $args );
		$this->assertNotSame( $key_before, $key_after );
		$this->assertNull( $rest_cache->get( $key_after ) );
	}

	/**
	 * When a WooCommerce product is no longer on sale, the 30-day lowest price display cleanly disappears.
	 *
	 * @return void
	 */
	public function test_woocommerce_ended_sale_cleanly_suppresses_30_day_display() {
		$item = new CatalogItem(
			array(
				'id'            => 42,
				'variation_id'  => 0,
				'currency'      => 'EUR',
				'regular_price' => 20.00,
				'sale_price'    => null,
				'current_price' => 20.00,
				'source'        => 'woocommerce',
			)
		);

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();

		$sales->method( 'active_map' )->willReturn(
			array(
				'42:0' => array(
					'product_id'          => 42,
					'variation_id'        => 0,
					'start_date'          => '2026-09-01',
					'lowest_30_day_price' => 7.00,
				),
			)
		);

		PluginSwap::swap( 'special_sales', $sales );

		$display = new TestablePriceDisplay( $this->createMock( AnchorPriceService::class ) );
		$html    = $display->call_render_thirty_day( $item );

		$this->assertSame( '', $html, 'The 30-day lowest price must cleanly disappear when a WooCommerce item is not promotional.' );
	}
}

/**
 * Subclass exposing protected render_thirty_day for unit testing.
 */
class TestablePriceDisplay extends PriceDisplay {

	/**
	 * Call render_thirty_day.
	 *
	 * @param CatalogItem $item Item.
	 * @return string
	 */
	public function call_render_thirty_day( CatalogItem $item ) {
		return $this->render_thirty_day( $item, 'single', array() );
	}
}
