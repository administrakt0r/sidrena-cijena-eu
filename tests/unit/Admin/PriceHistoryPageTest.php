<?php
/**
 * Price-history correction form tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\FormState;
use SidrenaCijena\Admin\PriceHistoryPage;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Tests\Support\PluginSwap;

/**
 * Probe exposing the protected correction form renderer.
 */
class PriceHistoryPageProbe extends PriceHistoryPage {

	/**
	 * Capture the correction form markup.
	 *
	 * @param int $product_id Prefilled product ID.
	 * @return string
	 */
	public static function form( $product_id = 0 ) {
		ob_start();
		self::render_correction_form( $product_id );

		return (string) ob_get_clean();
	}
}

/**
 * @covers \SidrenaCijena\Admin\PriceHistoryPage
 */
class PriceHistoryPageTest extends TestCase {

	/**
	 * Reset the form state store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['sidrena_test_transients'] = array();
	}

	/**
	 * The filtered product is prefilled and the price starts empty.
	 *
	 * @return void
	 */
	public function test_form_prefills_the_filtered_product() {
		$html = PriceHistoryPageProbe::form( 42 );

		$this->assertStringContainsString( 'id="sidrena-history-product"', $html );
		$this->assertStringContainsString( 'value="42"', $html );
		$this->assertStringContainsString( 'name="price"', $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	/**
	 * A failed submission keeps every submitted value.
	 *
	 * @return void
	 */
	public function test_submitted_values_survive_a_failed_validation() {
		FormState::store(
			'history-correction',
			array(
				'product_id'        => 7,
				'variation_id'      => 3,
				'price_type'        => 'sale',
				'price'             => '11.00',
				'reference_date'    => '2026-09-10',
				'correction_reason' => 'Popravak unosa',
			),
			array( 'correction_reason' => 'Enter a correction reason.' )
		);

		$html = PriceHistoryPageProbe::form( 0 );

		$this->assertStringContainsString( 'value="7"', $html );
		$this->assertStringContainsString( 'value="3"', $html );
		$this->assertStringContainsString( 'value="11.00"', $html );
		$this->assertStringContainsString( 'value="2026-09-10"', $html );
		$this->assertStringContainsString( 'value="Popravak unosa"', $html );
		$this->assertStringContainsString( 'value="sale" selected=', $html );
	}

	/**
	 * The error is attached to the offending field.
	 *
	 * @return void
	 */
	public function test_field_errors_are_attached_to_the_offending_field() {
		FormState::store(
			'history-correction',
			array( 'price' => '' ),
			array( 'price' => 'The price is not a valid amount.' )
		);

		$html = PriceHistoryPageProbe::form( 0 );

		$this->assertStringContainsString( 'id="sidrena-history-price-error"', $html );
		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertStringContainsString( 'aria-describedby="sidrena-history-price-error"', $html );
		$this->assertStringContainsString( 'The price is not a valid amount.', $html );
		$this->assertStringNotContainsString( 'sidrena-history-reason-error', $html );
	}

	/**
	 * render() derives total from fetched rows without calling count() when first page has fewer rows than PER_PAGE.
	 *
	 * @return void
	 */
	public function test_render_bypasses_count_query_when_first_page_has_fewer_than_per_page_rows() {
		$history = $this->createMock( PriceHistoryRepository::class );
		$history->expects( $this->once() )
			->method( 'query' )
			->willReturn(
				array(
					array(
						'id'             => 1,
						'product_id'     => 10,
						'variation_id'   => 0,
						'price_type'     => 'regular',
						'price'          => '9.99',
						'currency'       => 'EUR',
						'reference_date' => '2026-09-10',
						'source'         => 'manual',
						'created_at'     => '2026-11-01 10:00:00',
					),
				)
			);
		// count() must NEVER be called because page 1 has 1 row < PER_PAGE.
		$history->expects( $this->never() )->method( 'count' );

		PluginSwap::swap( 'history', $history );

		$_GET = array();

		ob_start();
		PriceHistoryPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Sidrena cijena: Price history', $html );
		PluginSwap::swap( 'history', null );
	}
}
