<?php
/**
 * WooCommerce product price block display regressions.
 *
 * @package SidrenaCijena\Tests\Unit\WooCommerce\Display
 */

namespace SidrenaCijena\Tests\Unit\WooCommerce\Display;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;

require_once dirname( __DIR__, 2 ) . '/wc-stubs.php';

/**
 * Price display double that records which product a block receives.
 */
class PriceBlockDisplay extends PriceDisplay {
	/**
	 * Skip the production anchor-service dependency.
	 */
	public function __construct() {}

	/**
	 * Mark the product and context selected by the block filter.
	 *
	 * @param mixed  $product Product.
	 * @param string $context Display context.
	 * @param array  $args    Render overrides.
	 * @return string
	 */
	public function render( $product, $context = 'single', array $args = array() ) {
		unset( $args );

		return sprintf( '<span data-reference-product="%d" data-display-context="%s"></span>', $product->get_id(), $context );
	}
}

/**
 * Covers exact product identification for WooCommerce price blocks.
 *
 * @covers \SidrenaCijena\WooCommerce\Display\PriceDisplay::filter_price_block
 */
class PriceBlockDisplayTest extends TestCase {
	/**
	 * Reset the WooCommerce and display settings stubs.
	 *
	 * @return void
	 */
	private function reset_state() {
		$GLOBALS['sidrena_test_options']           = array(
			'sidrena_cijena_settings' => array( 'display_location' => 'price_html' ),
		);
		$GLOBALS['sidrena_test_is_product']        = true;
		$GLOBALS['sidrena_test_queried_object_id'] = 5;
		$GLOBALS['sidrena_test_wc_products']       = array(
			5 => array( 'id' => 5 ),
			6 => array( 'id' => 6 ),
		);

		global $product;
		$product = new \WC_Product( array( 'id' => 5 ) );
	}

	/**
	 * A product-context block displays the reference for that exact product.
	 *
	 * @return void
	 */
	public function test_product_context_uses_its_own_product() {
		$this->reset_state();

		$out = ( new PriceBlockDisplay() )->filter_price_block(
			'<span class="product-price">€12.00</span>',
			array(
				'blockName' => 'woocommerce/product-price',
				'context'   => array( 'productId' => 6 ),
			)
		);

		$this->assertStringContainsString( 'data-reference-product="6"', $out );
		$this->assertStringContainsString( 'data-display-context="archive"', $out );
	}

	/**
	 * A price block inside a single-product template uses the queried product.
	 *
	 * @return void
	 */
	public function test_single_product_template_uses_queried_product() {
		$this->reset_state();

		$out = ( new PriceBlockDisplay() )->filter_price_block(
			'<span class="product-price">€12.00</span>',
			array(
				'blockName' => 'woocommerce/product-price',
				'attrs'     => array( 'isDescendentOfSingleProductTemplate' => true ),
			)
		);

		$this->assertStringContainsString( 'data-reference-product="5"', $out );
		$this->assertStringContainsString( 'data-display-context="single"', $out );
	}

	/**
	 * An ambiguous collection price is left unchanged rather than misattributed.
	 *
	 * @return void
	 */
	public function test_collection_block_without_product_context_is_unchanged() {
		$this->reset_state();

		$content = '<span class="product-price">€12.00</span>';
		$out     = ( new PriceBlockDisplay() )->filter_price_block(
			$content,
			array( 'blockName' => 'woocommerce/product-price' )
		);

		$this->assertSame( $content, $out );
	}
}
