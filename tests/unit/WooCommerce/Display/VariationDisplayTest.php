<?php
/**
 * Variable product storefront display regressions.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\WooCommerce\Display
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\WooCommerce\Display;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Catalog\CatalogProvider;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Pricing\AnchorPriceService;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;
use Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay;
use Titanworks\SidrenaCijena\WooCommerce\Display\VariationDisplay;

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
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\VariationDisplay
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay
 */
class VariationDisplayTest extends TestCase {

	/**
	 * Reset test environment state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_is_admin']          = false;
		$GLOBALS['twbce_test_is_product']        = false;
		$GLOBALS['twbce_test_queried_object_id'] = 0;
		$GLOBALS['twbce_test_options']           = array(
			'twbce_cijena_settings' => array(
				'display_enabled'       => true,
				'display_archives'      => true,
				'display_product_pages' => true,
				'display_location'      => 'price_html',
				'card_display_default'  => true,
			),
		);
		\Titanworks\SidrenaCijena\Support\Settings::reset_cache();
		PriceDisplay::reset_rendered_cache();
	}

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
			$this->assertStringContainsString( '10.09.2026.', $data['price_html'] );
			$this->assertSame( '9,00 €', $data['twbce_cijena_anchor'] );
			$this->assertSame( '10.09.2026.', $data['twbce_cijena_reference_date_display'] );
			$this->assertSame( '2026-09-10', $data['twbce_cijena_reference_date'] );
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
			$this->assertNull( $data['twbce_cijena_anchor'] );
			$this->assertNull( $data['twbce_cijena_anchor_raw'] );
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

	/**
	 * Variable products render variation anchor prices in shop cards.
	 *
	 * @return void
	 */
	public function test_variable_product_renders_anchor_in_archive_cards() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->with( 10, 11 )->willReturn( $item1 );
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->with( $item1 )->willReturn(
			array(
				'price'          => 12.0,
				'reference_date' => '2026-09-10',
			)
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena', $html );
			$this->assertStringContainsString( '12,00', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * Variable products with different variation anchors render a price range in shop cards.
	 *
	 * @return void
	 */
	public function test_variable_product_renders_anchor_range_in_archive_cards() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 25.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) use ( $item1, $item2 ) {
				if ( 11 === (int) $item->variation_id ) {
					return array(
						'price'          => 10.0,
						'reference_date' => '2026-09-10',
					);
				}
				if ( 12 === (int) $item->variation_id ) {
					return array(
						'price'          => 20.0,
						'reference_date' => '2026-09-10',
					);
				}
				return null;
			}
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena', $html );
			$this->assertStringContainsString( '10,00', $html );
			$this->assertStringContainsString( '20,00', $html );
			$this->assertStringContainsString( '–', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * Active special sales render thirty day lowest price on promotional items.
	 *
	 * @return void
	 */
	public function test_render_thirty_day_renders_when_active_sale_exists() {
		$item = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 0,
				'currency'      => 'EUR',
				'source'        => 'woocommerce',
				'regular_price' => 25.0,
				'current_price' => 18.0,
				'sale_price'    => 18.0,
			)
		);

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:0' => array(
					'product_id'          => 10,
					'variation_id'        => 0,
					'lowest_30_day_price' => 16.50,
					'start_date'          => '2026-11-01',
					'status'              => 'active',
				),
			)
		);
		PluginSwap::swap( 'special_sales', $sales );

		try {
			$display = new DisplayPriceForTest( $this->createMock( AnchorPriceService::class ) );
			$html    = $display->render_thirty_day_for_test( $item );
			$this->assertStringContainsString( 'sidrena-cijena--30-day', $html );
			$this->assertStringContainsString( '16,50', $html );
		} finally {
			PluginSwap::swap( 'special_sales', null );
		}
	}

	/**
	 * When only_when_different is enabled, variable products compare the full range.
	 *
	 * If one child variation matches but another differs, the range must still render.
	 *
	 * @return void
	 */
	public function test_variable_product_only_when_different_compares_complete_range() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 10.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				if ( 11 === (int) $item->variation_id ) {
					return array(
						'price'          => 10.0,
						'reference_date' => '2026-09-10',
					);
				}
				if ( 12 === (int) $item->variation_id ) {
					return array(
						'price'          => 20.0,
						'reference_date' => '2026-09-10',
					);
				}
				return null;
			}
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			// Range is €10–€20 anchor vs €10–€15 current; must NOT be hidden despite child 11 matching.
			$html = $display->render( $product, 'archive', array( 'only_when_different' => true ) );
			$this->assertStringContainsString( 'sidrena-cijena', $html );
			$this->assertStringContainsString( '10,00', $html );
			$this->assertStringContainsString( '20,00', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * When all variations match, only_when_different hides the range.
	 *
	 * @return void
	 */
	public function test_variable_product_only_when_different_hides_when_all_match() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 10.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 20.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				if ( 11 === (int) $item->variation_id ) {
					return array(
						'price'          => 10.0,
						'reference_date' => '2026-09-10',
					);
				}
				if ( 12 === (int) $item->variation_id ) {
					return array(
						'price'          => 20.0,
						'reference_date' => '2026-09-10',
					);
				}
				return null;
			}
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive', array( 'only_when_different' => true ) );
			$this->assertSame( '', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * Unavailable variations without retail price are excluded from the card range.
	 *
	 * @return void
	 */
	public function test_variable_product_excludes_unavailable_variations_without_price() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => null, // No active retail price / unpurchasable.
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 20.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				if ( 11 === (int) $item->variation_id ) {
					return array(
						'price'          => 5.0,
						'reference_date' => '2026-09-10',
					);
				}
				if ( 12 === (int) $item->variation_id ) {
					return array(
						'price'          => 20.0,
						'reference_date' => '2026-09-10',
					);
				}
				return null;
			}
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena', $html );
			$this->assertStringNotContainsString( '5,00', $html );
			$this->assertStringContainsString( '20,00', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * Multiple cards for the same product across different sections on the same page both receive anchors.
	 *
	 * @return void
	 */
	public function test_multiple_cards_for_same_product_both_rendered() {
		$item    = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 0,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_item' )->with( 10 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->with( $item )->willReturn(
			array(
				'price'          => 12.0,
				'reference_date' => '2026-09-10',
			)
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'   => 10,
				'type' => 'simple',
			)
		);

		try {
			// Card 1.
			$card1 = $display->filter_price_html( '<span class="price">15,00 €</span>', $product );
			$this->assertStringContainsString( 'sidrena-cijena', $card1 );

			PriceDisplay::reset_active_card();

			// Card 2 for the same product in a separate section (e.g. featured products).
			$card2 = $display->filter_price_html( '<span class="price">15,00 €</span>', $product );
			$this->assertStringContainsString( 'sidrena-cijena', $card2 );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * Variable products retain distinct reference dates for each amount instead of collapsing to one.
	 *
	 * @return void
	 */
	public function test_variable_product_groups_anchors_by_distinct_reference_dates() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 25.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				if ( 11 === (int) $item->variation_id ) {
					return array(
						'price'          => 10.0,
						'reference_date' => '2026-09-10',
					);
				}
				if ( 12 === (int) $item->variation_id ) {
					return array(
						'price'          => 20.0,
						'reference_date' => '2026-10-15',
					);
				}
				return null;
			}
		);

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( '10,00', $html );
			$this->assertStringContainsString( '20,00', $html );
			$this->assertStringContainsString( '10.09.2026.', $html );
			$this->assertStringContainsString( '15.10.2026.', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
		}
	}

	/**
	 * When variations have different special sale references, the archive card renders the 30-day range.
	 *
	 * @return void
	 */
	public function test_variable_product_renders_qualifying_special_sale_range_in_archive() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 12.0,
				'tax_inclusive' => false,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 22.0,
				'tax_inclusive' => false,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				return array(
					'price'          => 15.0,
					'reference_date' => '2026-09-10',
				);
			}
		);

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:11' => array( 'lowest_30_day_price' => 10.0 ),
				'10:12' => array( 'lowest_30_day_price' => 20.0 ),
			)
		);
		PluginSwap::swap( 'special_sales', $sales );

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena--30-day', $html );
			$this->assertStringContainsString( '10,00 € – 20,00 €', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
			PluginSwap::swap( 'special_sales', null );
		}
	}

	/**
	 * When only one child has a qualifying special sale, the card displays that sale reference.
	 *
	 * @return void
	 */
	public function test_variable_product_renders_single_variation_special_sale_when_only_one_qualifies() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 22.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				return array(
					'price'          => 15.0,
					'reference_date' => '2026-09-10',
				);
			}
		);

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		// Only child 12 has a qualifying sale.
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:12' => array( 'lowest_30_day_price' => 18.0 ),
			)
		);
		PluginSwap::swap( 'special_sales', $sales );

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena--30-day', $html );
			$this->assertStringContainsString( '18,00', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
			PluginSwap::swap( 'special_sales', null );
		}
	}

	/**
	 * Display locations never duplicate anchor blocks on the same card.
	 *
	 * @return void
	 */
	public function test_archive_fallback_and_filter_price_html_never_duplicate() {
		$item    = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 0,
				'currency'      => 'EUR',
				'current_price' => 15.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_item' )->with( 10 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->with( $item )->willReturn(
			array(
				'price'          => 12.0,
				'reference_date' => '2026-09-10',
			)
		);

		$display = new PriceDisplay( $anchors );

		global $product;
		$product = new \WC_Product(
			array(
				'id'   => 10,
				'type' => 'simple',
			)
		);

		try {
			// Case A: in price_html mode, filter renders while archive action remains quiet.
			$filtered = $display->filter_price_html( '<span class="price">15,00 €</span>', $product );
			$this->assertStringContainsString( 'sidrena-cijena', $filtered );

			ob_start();
			$display->render_archive_item();
			$fallback_output = ob_get_clean();
			$this->assertSame( '', $fallback_output );

			// Case B: in summary mode, archive action renders while price filter remains quiet.
			update_option(
				'twbce_cijena_settings',
				array_merge(
					\Titanworks\SidrenaCijena\Support\Settings::defaults(),
					array(
						'display_enabled'  => true,
						'display_archives' => true,
						'display_location' => 'summary',
					)
				)
			);

			ob_start();
			$display->render_archive_item();
			$fallback_output2 = ob_get_clean();
			$this->assertStringContainsString( 'sidrena-cijena', $fallback_output2 );

			$price_output = $display->filter_price_html( '<span class="price">15,00 €</span>', $product );
			$this->assertSame( '<span class="price">15,00 €</span>', $price_output );
		} finally {
			$product = null;
			PluginSwap::swap( 'catalog', null );
			update_option( 'twbce_cijena_settings', \Titanworks\SidrenaCijena\Support\Settings::defaults() );
		}
	}

	/**
	 * Non-AJAX admin requests return empty string and do not render variable product cards.
	 *
	 * @return void
	 */
	public function test_variable_product_returns_empty_in_admin() {
		$GLOBALS['twbce_test_is_admin'] = true;

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->expects( $this->never() )->method( 'get_anchor' );

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertSame( '', $html );
		} finally {
			$GLOBALS['twbce_test_is_admin'] = false;
		}
	}

	/**
	 * Variable product 30-day sale prices are tax-adjusted before range calculation.
	 *
	 * @return void
	 */
	public function test_variable_product_30_day_range_is_tax_adjusted() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 12.0,
				'tax_inclusive' => false,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 22.0,
				'tax_inclusive' => false,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturnCallback(
			static function ( $item ) {
				return array(
					'price'          => 15.0,
					'reference_date' => '2026-09-10',
				);
			}
		);

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		// Stored net prices: €10 and €20.
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:11' => array( 'lowest_30_day_price' => 10.0 ),
				'10:12' => array( 'lowest_30_day_price' => 20.0 ),
			)
		);
		PluginSwap::swap( 'special_sales', $sales );

		// Register WC products for variation tax lookups.
		$GLOBALS['twbce_test_wc_products'][11] = array(
			'id'     => 11,
			'type'   => 'variation',
			'status' => 'publish',
			'price'  => 12.0,
		);
		$GLOBALS['twbce_test_wc_products'][12] = array(
			'id'     => 12,
			'type'   => 'variation',
			'status' => 'publish',
			'price'  => 22.0,
		);

		// 25% VAT: 10 * 1.25 = 12.50, 20 * 1.25 = 25.00
		$GLOBALS['twbce_test_tax_multiplier'] = 1.25;

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena--30-day', $html );
			$this->assertStringContainsString( '12,50 € – 25,00 €', $html );
		} finally {
			unset( $GLOBALS['twbce_test_wc_products'][11], $GLOBALS['twbce_test_wc_products'][12], $GLOBALS['twbce_test_tax_multiplier'] );
			PluginSwap::swap( 'catalog', null );
			PluginSwap::swap( 'special_sales', null );
		}
	}

	/**
	 * When variations have qualifying special sales but no anchors recorded, card renders the sale reference.
	 *
	 * @return void
	 */
	public function test_variable_product_renders_variation_sales_when_no_anchors_recorded() {
		$item1   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 11,
				'currency'      => 'EUR',
				'current_price' => 12.0,
			)
		);
		$item2   = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 12,
				'currency'      => 'EUR',
				'current_price' => 22.0,
			)
		);
		$catalog = $this->createMock( CatalogProvider::class );
		$catalog->method( 'get_variation' )->willReturnMap(
			array(
				array( 10, 11, $item1 ),
				array( 10, 12, $item2 ),
			)
		);
		PluginSwap::swap( 'catalog', $catalog );

		$anchors = $this->createMock( AnchorPriceService::class );
		$anchors->method( 'get_anchor' )->willReturn( null ); // No anchor recorded for any variation.

		$sales = $this->getMockBuilder( SpecialSaleRepository::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'active_map' ) )
			->getMock();
		$sales->method( 'active_map' )->willReturn(
			array(
				'10:11' => array( 'lowest_30_day_price' => 10.0 ),
				'10:12' => array( 'lowest_30_day_price' => 20.0 ),
			)
		);
		PluginSwap::swap( 'special_sales', $sales );

		$display = new PriceDisplay( $anchors );
		$product = new \WC_Product(
			array(
				'id'       => 10,
				'type'     => 'variable',
				'children' => array( 11, 12 ),
			)
		);

		try {
			$html = $display->render( $product, 'archive' );
			$this->assertStringContainsString( 'sidrena-cijena--30-day', $html );
			$this->assertStringContainsString( '10,00 € – 20,00 €', $html );
		} finally {
			PluginSwap::swap( 'catalog', null );
			PluginSwap::swap( 'special_sales', null );
		}
	}
}
