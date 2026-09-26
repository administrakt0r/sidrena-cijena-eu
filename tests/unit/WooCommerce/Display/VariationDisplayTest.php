<?php
/**
 * Variable product storefront display regressions.
 *
 * @package SidrenaCijena\Tests\Unit\WooCommerce\Display
 */

namespace SidrenaCijena\Tests\Unit\WooCommerce\Display;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Pricing\AnchorPriceService;
use SidrenaCijena\Tests\Support\PluginSwap;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;
use SidrenaCijena\WooCommerce\Display\VariationDisplay;

require_once dirname( __DIR__, 2 ) . '/wc-stubs.php';
require_once dirname( __DIR__, 2 ) . '/PluginSwap.php';

/**
 * A variation with a stable price HTML value.
 */
class DisplayVariationProduct extends \WC_Product {
	/**
	 * @return string
	 */
	public function get_price_html() {
		return '<span>12,00 €</span>';
	}
}

/**
 * @covers \SidrenaCijena\WooCommerce\Display\VariationDisplay
 * @covers \SidrenaCijena\WooCommerce\Display\PriceDisplay
 */
class VariationDisplayTest extends TestCase {
	/**
	 * Parent products must not claim an anchor belonging to one variation.
	 *
	 * @return void
	 */
	public function test_variable_parent_has_no_single_anchor() {
		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->expects( $this->never() )->method( 'get_anchor' );
		$display = new PriceDisplay( $anchors );

		$this->assertSame(
			'',
			$display->render(
				new \WC_Product(
					array(
						'id'   => 10,
						'type' => 'variable',
					)
				)
			)
		);
	}

	/**
	 * WooCommerce receives the selected variation's own dated anchor.
	 *
	 * @return void
	 */
	public function test_variation_data_contains_matching_price_and_anchor() {
		$item    = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 12.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->with( 10, 11 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $catalog );
		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturn(
			array(
				'price'          => 9.0,
				'reference_date' => '2026-09-10',
			)
		);
		$variation = new DisplayVariationProduct(
			array(
				'id'        => 11,
				'parent_id' => 10,
				'type'      => 'variation',
			)
		);

		try {
			$data = ( new VariationDisplay( $anchors ) )->filter_available_variation( array( 'price_html' => '' ), null, $variation );
			$this->assertStringContainsString( '12,00', $data['price_html'] );
			$this->assertStringContainsString( 'data-variation-id="11"', $data['price_html'] );
			$this->assertStringContainsString( '10. 9. 2026.', $data['price_html'] );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}
}
