<?php
/**
 * Unit tests for LowestPriceExplainer.
 *
 * @package SidrenaCijena\Tests\Unit\Pricing
 */

namespace SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Pricing\LowestPrice30DayCalculator;
use SidrenaCijena\Pricing\LowestPriceExplainer;

/**
 * Verifies that the administrator explanation agrees with calculation results
 * across all required audit scenarios.
 *
 * @covers \SidrenaCijena\Pricing\LowestPriceExplainer
 * @covers \SidrenaCijena\Pricing\LowestPrice30DayCalculator::audit_window
 */
class LowestPriceExplainerTest extends TestCase {

	/**
	 * Explainer under test.
	 *
	 * @var LowestPriceExplainer
	 */
	private $explainer;

	/**
	 * Calculator reference.
	 *
	 * @var LowestPrice30DayCalculator
	 */
	private $calculator;

	/**
	 * Set up test dependencies.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->calculator = new LowestPrice30DayCalculator();
		$this->explainer  = new LowestPriceExplainer( null, $this->calculator );
	}

	/**
	 * Helper to create a CatalogItem.
	 *
	 * @param array<string,mixed> $attrs Attributes.
	 * @return CatalogItem
	 */
	protected function item( array $attrs = array() ) {
		return new CatalogItem(
			array_merge(
				array(
					'id'            => 100,
					'variation_id'  => 0,
					'parent_id'     => 0,
					'name'          => 'Maslinovo ulje 1L',
					'sku'           => 'ULJE-1L',
					'currency'      => 'EUR',
					'regular_price' => 15.00,
					'source'        => 'woocommerce',
				),
				$attrs
			)
		);
	}

	/**
	 * Helper to create a history row.
	 *
	 * @param int         $id    Row ID.
	 * @param float       $price Price.
	 * @param string|null $from  Valid from date/datetime.
	 * @param string|null $to    Valid to date/datetime.
	 * @param string      $type  Price type.
	 * @param string      $src   Source.
	 * @return array<string,mixed>
	 */
	protected function row( $id, $price, $from = null, $to = null, $type = 'regular', $src = 'woocommerce' ) {
		return array(
			'id'         => $id,
			'price'      => $price,
			'valid_from' => $from,
			'valid_to'   => $to,
			'price_type' => $type,
			'source'     => $src,
		);
	}

	/**
	 * Exact product and variation identity is clearly distinguished.
	 *
	 * @return void
	 */
	public function test_explains_exact_product_and_variation_identity() {
		// 1. Simple WooCommerce product.
		$simple      = $this->item();
		$explanation = $this->explainer->explain( $simple, '2026-10-01', array() );

		$this->assertSame( 100, $explanation['product_id'] );
		$this->assertSame( 0, $explanation['variation_id'] );
		$this->assertSame( 0, $explanation['parent_id'] );
		$this->assertSame( 'ULJE-1L', $explanation['sku'] );
		$this->assertFalse( $explanation['is_variation'] );
		$this->assertStringContainsString( 'Product #100', $explanation['identity_label'] );
		$this->assertStringContainsString( 'ULJE-1L', $explanation['identity_label'] );

		// 2. Variable product variation.
		$variation       = $this->item(
			array(
				'id'           => 100,
				'variation_id' => 101,
				'parent_id'    => 100,
				'name'         => 'Maslinovo ulje — 500ml',
				'sku'          => 'ULJE-500ML',
			)
		);
		$var_explanation = $this->explainer->explain( $variation, '2026-10-01', array() );

		$this->assertSame( 100, $var_explanation['product_id'] );
		$this->assertSame( 101, $var_explanation['variation_id'] );
		$this->assertSame( 100, $var_explanation['parent_id'] );
		$this->assertSame( 'ULJE-500ML', $var_explanation['sku'] );
		$this->assertTrue( $var_explanation['is_variation'] );
		$this->assertStringContainsString( 'Variation #101', $var_explanation['identity_label'] );
		$this->assertStringContainsString( 'Product #100', $var_explanation['identity_label'] );

		// 3. Standalone item.
		$standalone        = $this->item(
			array(
				'id'     => 50,
				'sku'    => 'STAND-50',
				'name'   => 'Kruh domaći',
				'source' => 'standalone',
			)
		);
		$stand_explanation = $this->explainer->explain( $standalone, '2026-10-01', array() );

		$this->assertStringContainsString( 'Standalone item #50', $stand_explanation['identity_label'] );
	}

	/**
	 * Multiple successive reductions: explanation agrees with calculation results.
	 *
	 * @return void
	 */
	public function test_agrees_with_calculation_on_multiple_successive_reductions() {
		$item       = $this->item();
		$sale_start = '2026-10-01'; // Window: 2026-09-01 to 2026-09-30.

		// Timeline with initial price then successive promotional reductions.
		$history = array(
			$this->row( 1, 20.00, '2026-08-01 00:00:00', null, 'regular' ),
			$this->row( 2, 16.00, '2026-09-05 10:00:00', '2026-09-12 23:59:59', 'sale' ),
			$this->row( 3, 14.00, '2026-09-13 00:00:00', '2026-09-20 23:59:59', 'sale' ),
			$this->row( 4, 12.00, '2026-09-21 00:00:00', '2026-09-30 23:59:59', 'sale' ),
		);

		$expected_lowest = $this->calculator->calculate( $history, $sale_start );
		$this->assertSame( 12.0, $expected_lowest );

		$explanation = $this->explainer->explain( $item, $sale_start, $history );

		// Explanation agrees with calculation.
		$this->assertSame( $expected_lowest, $explanation['lowest_price'] );
		$this->assertSame( '2026-09-01', $explanation['window_start'] );
		$this->assertSame( '2026-09-30', $explanation['window_end'] );
		$this->assertContains( 'successive_reductions', $explanation['conditions'] );

		// Determining record is row 4.
		$this->assertNotNull( $explanation['determining_record'] );
		$this->assertSame( 4, $explanation['determining_record']['id'] );
		$this->assertSame( 12.0, $explanation['determining_record']['price'] );

		// All 4 source records overlap the window.
		$this->assertCount( 4, $explanation['source_records'] );
	}

	/**
	 * Short history (< 30 days): explanation agrees with calculation results.
	 *
	 * @return void
	 */
	public function test_agrees_with_calculation_on_short_history() {
		$item       = $this->item();
		$sale_start = '2026-10-01'; // Window: 2026-09-01 to 2026-09-30.

		// Earliest record is 2026-09-20 (11 days of coverage in 30-day window).
		$history = array(
			$this->row( 10, 18.00, '2026-09-20 08:00:00', null, 'regular' ),
		);

		$expected_lowest = $this->calculator->calculate( $history, $sale_start );
		$this->assertSame( 18.0, $expected_lowest );

		$explanation = $this->explainer->explain( $item, $sale_start, $history );

		// Agreement.
		$this->assertSame( $expected_lowest, $explanation['lowest_price'] );
		$this->assertTrue( $explanation['is_short'] );
		$this->assertSame( 11, $explanation['coverage_days'] );
		$this->assertSame( '2026-09-20', $explanation['coverage_start'] );
		$this->assertContains( 'short_history', $explanation['conditions'] );

		// Warning text explains the short history condition clearly.
		$this->assertNotEmpty( $explanation['warnings'] );
		$this->assertStringContainsString( '11', $explanation['warnings'][0] );
		$this->assertStringContainsString( '2026-09-20', $explanation['warnings'][0] );
	}

	/**
	 * No history (missing-history fallback): explanation agrees with calculation results.
	 *
	 * @return void
	 */
	public function test_agrees_with_calculation_on_no_history() {
		$item       = $this->item();
		$sale_start = '2026-10-01';

		$expected_lowest = $this->calculator->calculate( array(), $sale_start );
		$this->assertNull( $expected_lowest );

		$explanation = $this->explainer->explain( $item, $sale_start, array() );

		// Agreement.
		$this->assertNull( $explanation['lowest_price'] );
		$this->assertFalse( $explanation['has_history'] );
		$this->assertSame( 0, $explanation['rows'] );
		$this->assertEmpty( $explanation['source_records'] );
		$this->assertNull( $explanation['determining_record'] );
		$this->assertContains( 'no_history', $explanation['conditions'] );

		// Clear warning explaining missing-history condition.
		$this->assertNotEmpty( $explanation['warnings'] );
		$this->assertStringContainsString( '2026-09-01', $explanation['warnings'][0] );
		$this->assertStringContainsString( '2026-09-30', $explanation['warnings'][0] );
	}

	/**
	 * Imported / manual price baseline: explanation identifies the source and agrees with calculation.
	 *
	 * @return void
	 */
	public function test_agrees_with_calculation_on_imported_or_manual_baseline() {
		$item       = $this->item();
		$sale_start = '2026-10-01';

		// 1. Imported baseline record.
		$history_import = array(
			$this->row( 25, 14.50, '2026-08-15 00:00:00', null, 'regular', 'import' ),
		);

		$expected_lowest = $this->calculator->calculate( $history_import, $sale_start );
		$this->assertSame( 14.5, $expected_lowest );

		$explanation = $this->explainer->explain( $item, $sale_start, $history_import );

		$this->assertSame( $expected_lowest, $explanation['lowest_price'] );
		$this->assertContains( 'imported_baseline', $explanation['conditions'] );
		$this->assertSame( 'import', $explanation['determining_record']['source'] );

		// 2. Manual anchor baseline record.
		$history_manual = array(
			$this->row( 26, 13.00, '2026-08-10 00:00:00', null, 'anchor', 'manual' ),
		);

		$explanation_manual = $this->explainer->explain( $item, $sale_start, $history_manual );
		$this->assertSame( 13.0, $explanation_manual['lowest_price'] );
		$this->assertContains( 'manual_baseline', $explanation_manual['conditions'] );
	}

	/**
	 * A sale that ended recently: explanation agrees with calculation incorporating the sale price.
	 *
	 * @return void
	 */
	public function test_agrees_with_calculation_on_sale_that_ended_recently() {
		$item       = $this->item();
		$sale_start = '2026-10-01'; // Window: 2026-09-01 to 2026-09-30.

		// Regular price 25.00, but a special sale occurred 2026-09-05 to 2026-09-15 at 19.99,
		// and ended recently before this new sale.
		$history = array(
			$this->row( 30, 25.00, '2026-08-01 00:00:00', null, 'regular' ),
			$this->row( 31, 19.99, '2026-09-05 00:00:00', '2026-09-15 23:59:59', 'sale' ),
		);

		$expected_lowest = $this->calculator->calculate( $history, $sale_start );
		$this->assertSame( 19.99, $expected_lowest );

		$explanation = $this->explainer->explain( $item, $sale_start, $history );

		// Agreement with calculation.
		$this->assertSame( $expected_lowest, $explanation['lowest_price'] );
		$this->assertContains( 'recent_sale_ended', $explanation['conditions'] );

		// Determining record is the promotional sale record.
		$this->assertSame( 31, $explanation['determining_record']['id'] );
		$this->assertSame( 'sale', $explanation['determining_record']['price_type'] );
		$this->assertSame( 19.99, $explanation['determining_record']['price'] );

		// Warnings include mention of recently ended sale.
		$warning_found = false;
		foreach ( $explanation['warnings'] as $warning ) {
			if ( false !== strpos( $warning, '2026-09-15' ) && ( false !== strpos( $warning, '19,99' ) || false !== strpos( $warning, '19.99' ) ) ) {
				$warning_found = true;
				break;
			}
		}
		$this->assertTrue( $warning_found, 'Warning must explain recently ended sale and its price.' );
	}

	/**
	 * HTML rendering includes the required audit elements cleanly.
	 *
	 * @return void
	 */
	public function test_render_html_contains_audit_elements() {
		$item        = $this->item();
		$sale_start  = '2026-10-01';
		$history     = array(
			$this->row( 40, 15.00, '2026-08-01 00:00:00', null, 'regular' ),
			$this->row( 41, 11.50, '2026-09-10 00:00:00', '2026-09-20 23:59:59', 'sale' ),
		);
		$explanation = $this->explainer->explain( $item, $sale_start, $history );
		$html        = $this->explainer->render_html( $explanation );

		$this->assertStringContainsString( 'ULJE-1L', $html );
		$this->assertStringContainsString( '2026-09-01 to 2026-09-30', $html );
		$this->assertStringContainsString( '11,50', $html );
		$this->assertStringContainsString( '#41', $html );
		$this->assertStringContainsString( 'sidrena-cijena-source-records', $html );
		$this->assertStringContainsString( 'Determining lowest price', $html );
	}

	/**
	 * Verify explain handles an array missing start_date without warning or notice.
	 *
	 * @return void
	 */
	public function test_explain_handles_array_without_start_date() {
		$item        = $this->item();
		$explanation = $this->explainer->explain( $item, array( 'sale_type' => 'akcija' ), array() );

		$this->assertIsArray( $explanation );
		$this->assertArrayHasKey( 'lowest_price', $explanation );
	}
}
