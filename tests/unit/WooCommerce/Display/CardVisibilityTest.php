<?php
/**
 * Card visibility unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\WooCommerce\Display
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\WooCommerce\Display;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\WooCommerce\Display\CardVisibility;

/**
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\CardVisibility
 */
class CardVisibilityTest extends TestCase {

	/**
	 * Set up test environment with WooCommerce stubs and clean state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/wc-stubs.php';

		$GLOBALS['twbce_test_can']         = true;
		$GLOBALS['twbce_test_wc_products'] = array();
		$GLOBALS['twbce_test_post_meta']   = array();
		$GLOBALS['twbce_test_options']     = array();
		$_POST                             = array();
	}

	/**
	 * Clean up after test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['twbce_test_wc_products'] = array();
		$GLOBALS['twbce_test_post_meta']   = array();
		$GLOBALS['twbce_test_options']     = array();
		$_POST                             = array();
		parent::tearDown();
	}

	/**
	 * Test is_visible returns true by default and respects explicit overrides.
	 *
	 * @return void
	 */
	public function test_is_visible_respects_post_meta() {
		$product_id = 42;

		// Initial state when no post meta exists: visible by default.
		$this->assertTrue( CardVisibility::is_visible( $product_id ) );

		// Set to '0': hidden.
		$GLOBALS['twbce_test_post_meta'][ $product_id ][ CardVisibility::META_KEY ] = '0';
		$this->assertFalse( CardVisibility::is_visible( $product_id ) );

		// Set to '1': visible.
		$GLOBALS['twbce_test_post_meta'][ $product_id ][ CardVisibility::META_KEY ] = '1';
		$this->assertTrue( CardVisibility::is_visible( $product_id ) );

		// Unset meta with card_display_default = false in settings.
		unset( $GLOBALS['twbce_test_post_meta'][ $product_id ][ CardVisibility::META_KEY ] );
		update_option( Settings::OPTION, array( 'card_display_default' => false ) );
		$this->assertFalse( CardVisibility::is_visible( $product_id ) );
	}

	/**
	 * Test render_control outputs expected markup with nonce and checkbox.
	 *
	 * @return void
	 */
	public function test_render_control_renders_expected_markup() {
		$product_id = 99;
		$GLOBALS['twbce_test_post_meta'][ $product_id ][ CardVisibility::META_KEY ] = '1';

		ob_start();
		CardVisibility::render_control( $product_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="' . CardVisibility::FIELD . '"', $html );
		$this->assertStringContainsString( 'id="sidrena-cijena-show-in-cards"', $html );
		$this->assertStringContainsString( 'checked=\'checked\'', $html );
		$this->assertStringContainsString( 'type="hidden"', $html );
	}

	/**
	 * Test save updates product metadata when nonce is valid and user has capability.
	 *
	 * @return void
	 */
	public function test_save_persists_flag_to_product_meta() {
		$product = new \WC_Product( array( 'id' => 123 ) );

		$_POST[ CardVisibility::NONCE ] = wp_create_nonce( CardVisibility::FIELD );
		$_POST[ CardVisibility::FIELD ] = '1';
		$_POST['_twbce_barcode']        = '3851234567890';

		CardVisibility::save( $product );

		$this->assertSame( '1', $product->get_meta( CardVisibility::META_KEY ) );
		$this->assertSame( '3851234567890', $product->get_meta( '_twbce_barcode' ) );

		// Now uncheck.
		$_POST[ CardVisibility::FIELD ] = '0';
		CardVisibility::save( $product );

		$this->assertSame( '0', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * Test save does nothing if user lacks capability.
	 *
	 * @return void
	 */
	public function test_save_bails_if_user_cannot_manage() {
		$product                        = new \WC_Product( array( 'id' => 123 ) );
		$GLOBALS['twbce_test_can']      = false;
		$_POST[ CardVisibility::NONCE ] = wp_create_nonce( CardVisibility::FIELD );
		$_POST[ CardVisibility::FIELD ] = '1';

		CardVisibility::save( $product );

		$this->assertSame( '', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * Test save does nothing if nonce is invalid.
	 *
	 * @return void
	 */
	public function test_save_bails_on_invalid_nonce() {
		$product                        = new \WC_Product( array( 'id' => 123 ) );
		$_POST[ CardVisibility::NONCE ] = 'invalid-nonce';
		$_POST[ CardVisibility::FIELD ] = '1';

		CardVisibility::save( $product );

		$this->assertSame( '', $product->get_meta( CardVisibility::META_KEY ) );
	}
}
