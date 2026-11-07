<?php
/**
 * Currency and monetary formatting tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Support\Currency;

/**
 * @covers \Titanworks\SidrenaCijena\Support\Currency
 */
class CurrencyTest extends TestCase {

	/**
	 * Reset option state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options'] = array();
	}

	/**
	 * Storage normalization accepts Croatian decimal commas.
	 *
	 * @return void
	 */
	public function test_to_storage_normalizes_commas() {
		$this->assertSame( 6.99, Currency::to_storage( '6,99' ) );
		$this->assertSame( 1234.5, Currency::to_storage( '1234,50' ) );
		$this->assertSame( 12.345678, Currency::to_storage( 12.3456784 ) );
	}

	/**
	 * Empty and invalid values normalize to null.
	 *
	 * @return void
	 */
	public function test_to_storage_null_cases() {
		$this->assertNull( Currency::to_storage( null ) );
		$this->assertNull( Currency::to_storage( '' ) );
		$this->assertNull( Currency::to_storage( 'not-a-price' ) );
	}

	/**
	 * Storage keeps six decimals.
	 *
	 * @return void
	 */
	public function test_to_storage_rounds_to_six_decimals() {
		$this->assertSame( 1.234568, Currency::to_storage( '1.2345678' ) );
	}

	/**
	 * CSV decimal uses a comma and two decimals.
	 *
	 * @return void
	 */
	public function test_format_decimal() {
		$this->assertSame( '6,99', Currency::format_decimal( 6.99 ) );
		$this->assertSame( '1234,50', Currency::format_decimal( 1234.5 ) );
		$this->assertSame( '0,00', Currency::format_decimal( 0 ) );
		$this->assertSame( '', Currency::format_decimal( null ) );
	}

	/**
	 * XML decimal uses a point and two decimals.
	 *
	 * @return void
	 */
	public function test_format_decimal_xml() {
		$this->assertSame( '6.99', Currency::format_decimal_xml( 6.99 ) );
		$this->assertSame( '1234.50', Currency::format_decimal_xml( 1234.5 ) );
		$this->assertSame( '', Currency::format_decimal_xml( null ) );
	}

	/**
	 * Half-up rounding to two decimals.
	 *
	 * @return void
	 */
	public function test_to_display_half_up() {
		$this->assertSame( 2.35, Currency::to_display( 2.345 ) );
		$this->assertSame( 2.34, Currency::to_display( 2.3449 ) );
	}

	/**
	 * Human-readable formatting honors the currency display setting.
	 *
	 * @return void
	 */
	public function test_format_variants() {
		$this->assertSame( '6,99 €', Currency::format( 6.99, 'EUR', array( 'display' => 'symbol' ) ) );
		$this->assertSame( '6,99 EUR', Currency::format( 6.99, 'EUR', array( 'display' => 'code' ) ) );
		$this->assertSame( '6,99', Currency::format( 6.99, 'EUR', array( 'display' => 'none' ) ) );
	}

	/**
	 * Currency code validation.
	 *
	 * @return void
	 */
	public function test_is_valid_code() {
		$this->assertTrue( Currency::is_valid_code( 'EUR' ) );
		$this->assertFalse( Currency::is_valid_code( 'eur' ) );
		$this->assertFalse( Currency::is_valid_code( 'EURO' ) );
		$this->assertFalse( Currency::is_valid_code( '' ) );
	}
}
