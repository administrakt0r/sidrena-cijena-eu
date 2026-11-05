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
use SidrenaCijena\Database\SpecialSaleRepository;
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
 * Exposes the separate 30-day reference renderer to focused unit tests.
 */
class DisplayPriceForTest extends PriceDisplay {
	/**
	 * Render the 30-day price block for an item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return string
	 */
	public function render_thirty_day_for_test( CatalogItem $item ) {
		return $this->render_thirty_day( $item, 'variation', array() );
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
		$anchors->expects( $this->exactly( 2 ) )->method( 'get_anchor' )->with( $item )->willReturn(
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
			$this->assertSame( '9,00 €', $data['sidrena_cijena_anchor'] );
			$this->assertSame( '10. 9. 2026.', $data['sidrena_cijena_reference_date_display'] );
			$this->assertSame( '2026-09-10', $data['sidrena_cijena_reference_date'] );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * A variation without an anchor has null anchor data so frontend hides anchor container.
	 *
	 * @return void
	 */
	public function test_variation_without_anchor_returns_null_anchor() {
		$item    = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->with( 10, 12 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $catalog );
		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->with( $item )->willReturn( null );
		$variation = new DisplayVariationProduct(
			array(
				'id'        => 12,
				'parent_id' => 10,
				'type'      => 'variation',
			)
		);

		try {
			$data = ( new VariationDisplay( $anchors ) )->filter_available_variation( array( 'price_html' => '' ), null, $variation );
			$this->assertNull( $data['sidrena_cijena_anchor'] );
			$this->assertNull( $data['sidrena_cijena_anchor_raw'] );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * A variation's 30-day display never borrows its parent's active sale.
	 *
	 * @return void
	 */
	public function test_variation_30_day_display_uses_only_its_own_active_sale() {
		$item  = new CatalogItem(
			array(
				'id'           => 10,
				'variation_id' => 11,
				'currency'     => 'EUR',
			)
		);
		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:0'  => array( 'lowest_30_day_price' => 99.0 ),
				'10:11' => array( 'lowest_30_day_price' => 7.0 ),
			)
		);
		$anchors = $this->createMock( AnchorPriceService::class );
		PluginSwap::swap( 'special_sales', $sales );

		try {
			$html = ( new DisplayPriceForTest( $anchors ) )->render_thirty_day_for_test( $item );
			$this->assertStringContainsString( '7,00', $html );
			$this->assertStringNotContainsString( '99,00', $html );
		} finally {
			PluginSwap::swap( 'special_sales', null );
		}
	}

	/**
	 * A parent 30-day reference is not rendered for a variation without its own.
	 *
	 * @return void
	 */
	public function test_variation_without_active_sale_does_not_render_parent_reference() {
		$item  = new CatalogItem(
			array(
				'id'           => 10,
				'variation_id' => 11,
				'currency'     => 'EUR',
			)
		);
		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		$sales->method( 'active_map' )->willReturn(
			array( '10:0' => array( 'lowest_30_day_price' => 99.0 ) )
		);
		PluginSwap::swap( 'special_sales', $sales );

		try {
			$this->assertSame( '', ( new DisplayPriceForTest( $this->createMock( AnchorPriceService::class ) ) )->render_thirty_day_for_test( $item ) );
		} finally {
			PluginSwap::swap( 'special_sales', null );
		}
	}
}
