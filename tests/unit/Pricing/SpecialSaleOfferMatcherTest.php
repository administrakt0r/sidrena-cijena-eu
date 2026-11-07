<?php
/**
 * Unit tests for SpecialSaleOfferMatcher.
 *
 * @package SidrenaCijena\Tests\Unit\Pricing
 */

namespace SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Pricing\SpecialSaleOfferMatcher;

/**
 * @covers \SidrenaCijena\Pricing\SpecialSaleOfferMatcher
 */
class SpecialSaleOfferMatcherTest extends TestCase {

	/**
	 * Standalone catalog items always match regardless of period.
	 *
	 * @return void
	 */
	public function test_standalone_item_always_matches() {
		$item = new CatalogItem(
			array(
				'id'     => 1,
				'source' => 'standalone',
			)
		);
		$sale = array(
			'start_date'  => '2026-11-01',
			'promo_price' => 15.0,
		);

		$this->assertTrue( SpecialSaleOfferMatcher::matches( $item, $sale, null ) );
	}

	/**
	 * WooCommerce items match when period is null (e.g. newly created sale without history).
	 *
	 * @return void
	 */
	public function test_woocommerce_item_matches_when_period_is_null() {
		$item = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale = array(
			'start_date'  => '2026-11-01',
			'promo_price' => 15.0,
		);

		$this->assertTrue( SpecialSaleOfferMatcher::matches( $item, $sale, null ) );
	}

	/**
	 * WooCommerce items match when period aligns in date and price.
	 *
	 * @return void
	 */
	public function test_woocommerce_item_matches_when_period_aligns() {
		$item   = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale   = array(
			'start_date'  => '2026-11-01 00:00:00',
			'promo_price' => 15.0,
		);
		$period = array(
			'valid_from' => '2026-11-01 10:30:00',
			'price'      => 15.0,
		);

		$this->assertTrue( SpecialSaleOfferMatcher::matches( $item, $sale, $period ) );
	}

	/**
	 * WooCommerce items reject when prior closure occurred on the same day as period start.
	 *
	 * @return void
	 */
	public function test_woocommerce_item_rejects_same_day_prior_closure() {
		$item   = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale   = array(
			'start_date'  => '2026-11-01',
			'promo_price' => 15.0,
		);
		$period = array(
			'valid_from'       => '2026-11-01 14:00:00',
			'prior_closure_at' => '2026-11-01 12:00:00',
			'price'            => 15.0,
		);

		$this->assertFalse( SpecialSaleOfferMatcher::matches( $item, $sale, $period ) );
	}

	/**
	 * WooCommerce items reject an older sale classification from before a prior closure.
	 *
	 * @return void
	 */
	public function test_woocommerce_item_rejects_sale_before_prior_closure() {
		$item   = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale   = array(
			'start_date'  => '2026-10-15',
			'promo_price' => 15.0,
		);
		$period = array(
			'valid_from'       => '2026-11-05 10:00:00',
			'prior_closure_at' => '2026-10-31 23:59:59',
			'price'            => 15.0,
		);

		$this->assertFalse( SpecialSaleOfferMatcher::matches( $item, $sale, $period ) );
	}

	/**
	 * WooCommerce items reject when start dates differ so reference window matches actual offer start.
	 *
	 * @return void
	 */
	public function test_woocommerce_item_rejects_differing_start_dates_when_period_present() {
		$item   = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale   = array(
			'start_date'  => '2026-11-05',
			'promo_price' => 15.0,
		);
		$period = array(
			'valid_from' => '2026-11-01 10:00:00',
			'price'      => 15.0,
		);

		$this->assertFalse( SpecialSaleOfferMatcher::matches( $item, $sale, $period ) );
	}

	/**
	 * Sale without start_date rejects when period is provided.
	 *
	 * @return void
	 */
	public function test_sale_without_start_date_rejects_when_period_provided() {
		$item   = new CatalogItem(
			array(
				'id'     => 10,
				'source' => 'woocommerce',
			)
		);
		$sale   = array(
			'start_date' => '',
		);
		$period = array(
			'valid_from' => '2026-11-01',
			'price'      => 15.0,
		);

		$this->assertFalse( SpecialSaleOfferMatcher::matches( $item, $sale, $period ) );
	}
}
