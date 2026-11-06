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

	/**
	 * Expose context resolution for unit testing.
	 *
	 * @param \WC_Product|null $product Product.
	 * @return string
	 */
	public function test_context( $product = null ) {
		return $this->context( $product );
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
	 * A variation of the queried parent product uses single product context.
	 *
	 * Variations belonging to other products (e.g. upsells/related) use archive context.
	 *
	 * @return void
	 */
	public function test_variation_of_queried_product_uses_single_context() {
		$this->reset_state();

		$display = new PriceBlockDisplay();

		$parent_product    = new \WC_Product( array( 'id' => 5 ) );
		$queried_variation = new \WC_Product_Variation(
			array(
				'id'        => 501,
				'parent_id' => 5,
			)
		);
		$other_variation   = new \WC_Product_Variation(
			array(
				'id'        => 601,
				'parent_id' => 6,
			)
		);
		$other_product     = new \WC_Product( array( 'id' => 6 ) );

		$this->assertSame( 'single', $display->test_context( $parent_product ) );
		$this->assertSame( 'single', $display->test_context( $queried_variation ) );
		$this->assertSame( 'archive', $display->test_context( $other_variation ) );
		$this->assertSame( 'archive', $display->test_context( $other_product ) );

		// Also check via filter_price_html.
		$html_variation = $display->filter_price_html( '<span class="price">€10</span>', $queried_variation );
		$this->assertStringContainsString( 'data-display-context="single"', $html_variation );
		$this->assertStringContainsString( 'data-reference-product="501"', $html_variation );

		$html_other = $display->filter_price_html( '<span class="price">€10</span>', $other_variation );
		$this->assertStringContainsString( 'data-display-context="archive"', $html_other );
		$this->assertStringContainsString( 'data-reference-product="601"', $html_other );
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

	/**
	 * A price block in an archive query loop gets product context from WP_Block instance.
	 *
	 * @return void
	 */
	public function test_collection_block_resolves_product_from_block_instance() {
		$this->reset_state();

		$block_instance = (object) array(
			'context' => array( 'postId' => 6 ),
		);

		$out = ( new PriceBlockDisplay() )->filter_price_block(
			'<span class="product-price">€12.00</span>',
			array( 'blockName' => 'woocommerce/product-price' ),
			$block_instance
		);

		$this->assertStringContainsString( 'data-reference-product="6"', $out );
		$this->assertStringContainsString( 'data-display-context="archive"', $out );
	}

	/**
	 * Presets defined in Settings match expectations.
	 *
	 * @return void
	 */
	public function test_settings_anchor_label_presets() {
		$presets = \SidrenaCijena\Support\Settings::anchor_label_presets();

		$this->assertArrayHasKey( 'sidrena', $presets );
		$this->assertArrayHasKey( 'bazna', $presets );
		$this->assertArrayHasKey( 'referentna', $presets );
		$this->assertArrayHasKey( 'dodatna', $presets );
		$this->assertArrayHasKey( 'cijena_na_dan', $presets );
		$this->assertArrayHasKey( 'cijena_na', $presets );
		$this->assertArrayHasKey( 'najniza_redovna', $presets );
		$this->assertSame( 'Bazna cijena', $presets['bazna'] );
	}

	/**
	 * PriceDisplay formats static and dynamic date tokens in anchor labels.
	 *
	 * @return void
	 */
	public function test_format_anchor_label_resolves_tokens() {
		// Static label.
		$this->assertSame( 'Bazna cijena', PriceDisplay::format_anchor_label( 'Bazna cijena', '10. 9. 2026.', '2026-09-10' ) );

		// Dynamic {datum} token with general 2026 reference date.
		$this->assertSame(
			'Cijena na dan 10. 9. 2026.',
			PriceDisplay::format_anchor_label( 'Cijena na dan {datum}', '10. 9. 2026.', '2026-09-10' )
		);

		// Dynamic {datum} token with legacy 2025 grocery reference date.
		$this->assertSame(
			'Cijena na 2. 5. 2025.',
			PriceDisplay::format_anchor_label( 'Cijena na {datum}', '2. 5. 2025.', '2025-05-02' )
		);

		// Dynamic {date} synonym token.
		$this->assertSame(
			'Cijena na 10. 9. 2026.',
			PriceDisplay::format_anchor_label( 'Cijena na {date}', '10. 9. 2026.', '2026-09-10' )
		);

		// Dynamic {datum_iso} token.
		$this->assertSame(
			'Referentna cijena (2026-09-10)',
			PriceDisplay::format_anchor_label( 'Referentna cijena ({datum_iso})', '10. 9. 2026.', '2026-09-10' )
		);

		// Graceful fallback when reference date is empty.
		$this->assertSame(
			'Cijena na dan',
			PriceDisplay::format_anchor_label( 'Cijena na dan {datum}', '', '' )
		);
		$this->assertSame(
			'Najniža redovna cijena',
			PriceDisplay::format_anchor_label( 'Najniža redovna cijena ({datum})', '', '' )
		);
	}

	/**
	 * PriceDisplay title attribute avoids date duplication.
	 *
	 * @return void
	 */
	public function test_format_anchor_title_avoids_duplication() {
		// Date not in label -> appends (date).
		$this->assertSame(
			'Bazna cijena (10. 9. 2026.)',
			PriceDisplay::format_anchor_title( 'Bazna cijena', '10. 9. 2026.' )
		);

		// Date already in label -> does not duplicate.
		$this->assertSame(
			'Cijena na dan 10. 9. 2026.',
			PriceDisplay::format_anchor_title( 'Cijena na dan 10. 9. 2026.', '10. 9. 2026.' )
		);

		// Empty date -> returns label unchanged.
		$this->assertSame(
			'Bazna cijena',
			PriceDisplay::format_anchor_title( 'Bazna cijena', '' )
		);
	}
}
