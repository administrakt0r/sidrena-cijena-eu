<?php
/**
 * Unit tests for page-builder price element compatibility.
 *
 * @package SidrenaCijena\Tests\Unit\WooCommerce\Display
 */

namespace SidrenaCijena\Tests\Unit\WooCommerce\Display;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\WooCommerce\Display\BuilderCompat;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;

require_once dirname( __DIR__, 2 ) . '/wc-stubs.php';

/**
 * Anchor renderer double returning a fixed marker.
 */
class FakeDisplay extends PriceDisplay {

	/**
	 * Constructor without the production dependency.
	 */
	public function __construct() {}

	/**
	 * Render a fixed anchor marker.
	 *
	 * @param mixed  $product  Product.
	 * @param string $context  Context.
	 * @param array  $args     Arguments.
	 * @return string
	 */
	public function render( $product, $context = 'single', array $args = array() ) {
		unset( $product, $context, $args );

		return '<b class="anchor-mark">1,75 €</b>';
	}
}

/**
 * Covers the builder hook routing and single-product append rules.
 *
 * @covers \SidrenaCijena\WooCommerce\Display\BuilderCompat
 */
class BuilderCompatTest extends TestCase {

	/**
	 * Marker returned by the display double.
	 *
	 * @var string
	 */
	const MARKER = '<b class="anchor-mark">1,75 €</b>';

	/**
	 * Price element content used by the tests.
	 *
	 * @var string
	 */
	const PRICE = '<span class="woocommerce-Price-amount">5,00 €</span>';

	/**
	 * Reset shared test state.
	 *
	 * @return void
	 */
	private function reset_state() {
		$GLOBALS['sidrena_test_options']           = array();
		$GLOBALS['sidrena_test_is_product']        = true;
		$GLOBALS['sidrena_test_queried_object_id'] = 5;
		$GLOBALS['sidrena_test_wc_products']       = array(
			5 => array( 'id' => 5 ),
		);

		unset( $GLOBALS['product'] );
	}

	/**
	 * Build the compatibility filter.
	 *
	 * @return BuilderCompat
	 */
	private function compat() {
		return new BuilderCompat( new FakeDisplay() );
	}

	/**
	 * Divi modules without a price slug pass through unchanged.
	 *
	 * @return void
	 */
	public function test_divi_requires_price_slug() {
		$this->reset_state();

		$out = $this->compat()->filter_divi( self::PRICE, 'et_pb_wc_title' );

		$this->assertSame( self::PRICE, $out );
	}

	/**
	 * Divi price modules receive the anchor.
	 *
	 * @return void
	 */
	public function test_divi_price_module_is_appended() {
		$this->reset_state();

		$out = $this->compat()->filter_divi( self::PRICE, 'et_pb_wc_price' );

		$this->assertSame( self::PRICE . self::MARKER, $out );
	}

	/**
	 * Beaver Builder needs a module object with a price slug.
	 *
	 * @return void
	 */
	public function test_beaver_requires_price_slug() {
		$this->reset_state();

		$compat = $this->compat();

		$this->assertSame( self::PRICE, $compat->filter_beaver( self::PRICE, 'not-a-module' ) );
		$this->assertSame( self::PRICE, $compat->filter_beaver( self::PRICE, (object) array( 'slug' => 'heading' ) ) );
		$this->assertSame( self::PRICE . self::MARKER, $compat->filter_beaver( self::PRICE, (object) array( 'slug' => 'price' ) ) );
	}

	/**
	 * Bricks needs an element array with a price name.
	 *
	 * @return void
	 */
	public function test_bricks_requires_price_name() {
		$this->reset_state();

		$compat = $this->compat();

		$this->assertSame( self::PRICE, $compat->filter_bricks( self::PRICE, 'not-an-element' ) );
		$this->assertSame( self::PRICE, $compat->filter_bricks( self::PRICE, array( 'name' => 'title' ) ) );
		$this->assertSame( self::PRICE . self::MARKER, $compat->filter_bricks( self::PRICE, array( 'name' => 'product-price' ) ) );
	}

	/**
	 * Non-string and empty content passes through.
	 *
	 * @return void
	 */
	public function test_append_skips_non_string_and_empty_content() {
		$this->reset_state();

		$compat = $this->compat();

		$this->assertSame( array(), $compat->filter_content( array() ) );
		$this->assertSame( '', $compat->filter_content( '' ) );
	}

	/**
	 * Content that already carries the anchor is never double-appended.
	 *
	 * @return void
	 */
	public function test_append_skips_already_annotated_content() {
		$this->reset_state();

		$content = self::PRICE . '<div class="sidrena-cijena">1,75 €</div>';

		$this->assertSame( $content, $this->compat()->filter_content( $content ) );
	}

	/**
	 * Anchors only render in the configured display location.
	 *
	 * @return void
	 */
	public function test_append_respects_display_location_setting() {
		$this->reset_state();

		$GLOBALS['sidrena_test_options']['sidrena_cijena_settings'] = array( 'display_location' => 'summary' );

		$this->assertSame( self::PRICE, $this->compat()->filter_content( self::PRICE ) );
	}

	/**
	 * Only price elements with amount markup are extended.
	 *
	 * @return void
	 */
	public function test_append_requires_price_amount_markup() {
		$this->reset_state();

		$this->assertSame( '<p>Text</p>', $this->compat()->filter_content( '<p>Text</p>' ) );
	}

	/**
	 * Collection contexts never receive anchors.
	 *
	 * @return void
	 */
	public function test_append_requires_single_product_context() {
		$this->reset_state();

		$GLOBALS['sidrena_test_is_product'] = false;

		$this->assertSame( self::PRICE, $this->compat()->filter_content( self::PRICE ) );
	}

	/**
	 * The queried product receives the anchor.
	 *
	 * @return void
	 */
	public function test_append_uses_queried_product() {
		$this->reset_state();

		$this->assertSame( self::PRICE . self::MARKER, $this->compat()->filter_content( self::PRICE ) );
	}

	/**
	 * The global product is the fallback when the query resolves to nothing.
	 *
	 * @return void
	 */
	public function test_append_falls_back_to_global_product() {
		$this->reset_state();

		$GLOBALS['sidrena_test_queried_object_id'] = 0;
		$GLOBALS['product']                        = new \WC_Product( array( 'id' => 9 ) );

		$this->assertSame( self::PRICE . self::MARKER, $this->compat()->filter_content( self::PRICE ) );
	}

	/**
	 * Without any product the content is returned untouched.
	 *
	 * @return void
	 */
	public function test_append_without_product_returns_content() {
		$this->reset_state();

		$GLOBALS['sidrena_test_queried_object_id'] = 0;

		$this->assertSame( self::PRICE, $this->compat()->filter_content( self::PRICE ) );
	}
}
