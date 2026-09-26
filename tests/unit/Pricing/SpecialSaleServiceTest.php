<?php
/**
 * Unit tests for the special sale service validation paths.
 *
 * @package SidrenaCijena\Tests\Unit\Pricing
 */

namespace SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Pricing\LowestPrice30DayCalculator;
use SidrenaCijena\Pricing\SpecialSaleService;

/**
 * Covers the sale-type gate (FR-023), date validation and not-found paths.
 *
 * Repositories run against the wpdb test double, so inserts succeed while
 * reads return nothing; history-dependent calculations see an empty timeline.
 *
 * @covers \SidrenaCijena\Pricing\SpecialSaleService
 */
class SpecialSaleServiceTest extends TestCase {

	/**
	 * Reset database and transient doubles before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wpdb'] = new \wpdb();
	}

	/**
	 * A sale without a type is never stored (FR-023).
	 *
	 * @return void
	 */
	public function test_create_rejects_missing_sale_type() {
		$result = $this->service()->create(
			$this->item(),
			array(
				'sale_type'  => '',
				'start_date' => '2026-10-01',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_sale_type', $result->get_error_code() );
	}

	/**
	 * A sale needs a valid start date.
	 *
	 * @return void
	 */
	public function test_create_rejects_invalid_start_date() {
		$result = $this->service()->create(
			$this->item(),
			array(
				'sale_type'  => 'akcija',
				'start_date' => 'not-a-date',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_sale_start_date', $result->get_error_code() );
	}

	/**
	 * The end date cannot precede the start date.
	 *
	 * @return void
	 */
	public function test_create_rejects_end_before_start() {
		$result = $this->service()->create(
			$this->item(),
			array(
				'sale_type'  => 'akcija',
				'start_date' => '2026-10-05',
				'end_date'   => '2026-10-01',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_sale_end_date', $result->get_error_code() );
	}

	/**
	 * A valid sale is stored and gets an ID.
	 *
	 * @return void
	 */
	public function test_create_stores_valid_sale() {
		$result = $this->service()->create(
			$this->item(),
			array(
				'sale_type'   => 'akcija',
				'sale_name'   => 'Jesenska akcija',
				'start_date'  => '2026-10-01',
				'end_date'    => '2026-10-10',
				'promo_price' => '5.99',
			)
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * An open-ended sale (no end date) is stored.
	 *
	 * @return void
	 */
	public function test_create_stores_open_ended_sale() {
		$result = $this->service()->create(
			$this->item(),
			array(
				'sale_type'  => 'snizenje',
				'start_date' => '2026-10-01',
			)
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * Updating a sale the repository cannot find is a 404.
	 *
	 * @return void
	 */
	public function test_update_unknown_sale_returns_not_found() {
		$result = $this->service()->update(
			$this->item(),
			9999,
			array(
				'sale_type'  => 'akcija',
				'start_date' => '2026-10-01',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'special_sale_not_found', $result->get_error_code() );
	}

	/**
	 * Recomputing a sale the repository cannot find is a 404.
	 *
	 * @return void
	 */
	public function test_recompute_unknown_sale_returns_not_found() {
		$result = $this->service()->recompute( $this->item(), 9999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'special_sale_not_found', $result->get_error_code() );
	}

	/**
	 * Cancelling a sale the repository cannot find fails without a fatal.
	 *
	 * @return void
	 */
	public function test_cancel_unknown_sale_returns_false() {
		$this->assertFalse( $this->service()->cancel( 9999 ) );
	}

	/**
	 * With no stored rows no sale applies to the item.
	 *
	 * @return void
	 */
	public function test_active_returns_null_without_rows() {
		$this->assertNull( $this->service()->active( 42, 0 ) );
	}

	/**
	 * Service instance with repository doubles.
	 *
	 * @return SpecialSaleService
	 */
	protected function service() {
		return new SpecialSaleService(
			new SpecialSaleRepository(),
			new PriceHistoryRepository(),
			new LowestPrice30DayCalculator()
		);
	}

	/**
	 * Build a catalog item.
	 *
	 * @return CatalogItem
	 */
	protected function item() {
		return new CatalogItem(
			array(
				'id'            => 42,
				'type'          => 'product',
				'name'          => 'Kava mljevena 250 g',
				'sku'           => 'KAV-250',
				'brand'         => 'Franck',
				'barcode'       => '3851234567890',
				'unit'          => 'kg',
				'unit_price'    => 28.0,
				'currency'      => 'EUR',
				'availability'  => 'available',
				'regular_price' => 6.99,
				'sale_price'    => 5.99,
				'current_price' => 5.99,
				'tax_inclusive' => true,
				'first_seen'    => '2025-01-10',
				'categories'    => array(),
				'source'        => 'standalone',
			)
		);
	}
}
