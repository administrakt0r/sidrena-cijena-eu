<?php
/**
 * WooCommerce product editor form and save workflow tests.
 *
 * Verifies that the native WooCommerce product editor form, plugin controls,
 * nonces, submit buttons, and POST handlers function safely:
 * - Ordinary product saves update card visibility without interference.
 * - Dedicated plugin actions (anchor record and special-sale save) submit
 *   independently to admin-post.php and return back to the editor.
 * - Controls contain no invalid nested forms, no duplicate/shared _wpnonce fields,
 *   no plugin action overrides of the normal Update action, and no HTML5 required
 *   attributes that block unrelated product saves.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\WooCommerce
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\AnchorMetaBox;
use Titanworks\SidrenaCijena\Admin\SpecialSaleMetaBox;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;
use Titanworks\SidrenaCijena\WooCommerce\Display\CardVisibility;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\CardVisibility
 * @covers \Titanworks\SidrenaCijena\Admin\AnchorMetaBox
 * @covers \Titanworks\SidrenaCijena\Admin\SpecialSaleMetaBox
 */
class ProductEditorSaveTest extends TestCase {

	/**
	 * Set up test environment with WooCommerce stubs and clean state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/wc-stubs.php';

		$GLOBALS['twbce_test_can']                 = true;
		$GLOBALS['twbce_test_wc_products']         = array();
		$GLOBALS['twbce_test_post_meta']           = array();
		$GLOBALS['twbce_test_posts']               = array();
		$GLOBALS['twbce_test_last_redirect']       = null;
		$GLOBALS['twbce_test_price_history_table'] = array();
		$GLOBALS['twbce_test_special_sales_table'] = array();
		$_POST                                     = array();
		$_GET                                      = array();
		$_REQUEST                                  = array();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'catalog', null );
		PluginSwap::swap( 'history', null );
		PluginSwap::swap( 'special_sales', null );

		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		unset( $GLOBALS['twbce_test_can'], $GLOBALS['twbce_test_wc_products'], $GLOBALS['twbce_test_last_redirect'] );

		parent::tearDown();
	}

	/**
	 * CardVisibility render_control generates valid markup without colliding nonces or referers.
	 *
	 * @return void
	 */
	public function test_card_visibility_render_control_markup() {
		ob_start();
		CardVisibility::render_control( 100 );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="twbce_cijena_show_in_cards"', $html );
		$this->assertStringContainsString( 'id="sidrena-cijena-show-in-cards"', $html );
		$this->assertStringContainsString( 'name="' . CardVisibility::NONCE . '"', $html );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( 'required', $html );
	}

	/**
	 * Ordinary product save: CardVisibility::save persists preference when authorized.
	 *
	 * @return void
	 */
	public function test_card_visibility_save_persists_checked_state() {
		$product = new \WC_Product( array( 'id' => 100 ) );

		$_POST[ CardVisibility::NONCE ] = 'test-nonce';
		$_POST[ CardVisibility::FIELD ] = '1';

		CardVisibility::save( $product );

		$this->assertSame( '1', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * Ordinary product save: CardVisibility::save persists unchecked state.
	 *
	 * @return void
	 */
	public function test_card_visibility_save_persists_unchecked_state() {
		$product = new \WC_Product( array( 'id' => 100 ) );

		$_POST[ CardVisibility::NONCE ] = 'test-nonce';
		$_POST[ CardVisibility::FIELD ] = '0';

		CardVisibility::save( $product );

		$this->assertSame( '0', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * CardVisibility::save ignores submission if user lacks capability.
	 *
	 * @return void
	 */
	public function test_card_visibility_save_ignores_unauthorized_user() {
		$GLOBALS['twbce_test_can'] = false;
		$product                   = new \WC_Product( array( 'id' => 100 ) );

		$_POST[ CardVisibility::NONCE ] = 'test-nonce';
		$_POST[ CardVisibility::FIELD ] = '1';

		CardVisibility::save( $product );

		$this->assertSame( '', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * CardVisibility::save ignores submission when nonce is absent.
	 *
	 * @return void
	 */
	public function test_card_visibility_save_ignores_missing_nonce() {
		$product = new \WC_Product( array( 'id' => 100 ) );

		$_POST[ CardVisibility::FIELD ] = '1';

		CardVisibility::save( $product );

		$this->assertSame( '', $product->get_meta( CardVisibility::META_KEY ) );
	}

	/**
	 * AnchorMetaBox::render produces clean form markup without nested forms or blocking required fields.
	 *
	 * @return void
	 */
	public function test_anchor_metabox_render_markup_safety() {
		$post     = (object) array(
			'ID'        => 200,
			'post_type' => 'product',
		);
		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_item' )->willReturn(
			new CatalogItem(
				array(
					'id'            => 200,
					'name'          => 'Test Product',
					'sku'           => 'TEST-200',
					'regular_price' => 29.99,
					'current_price' => 29.99,
					'currency'      => 'EUR',
					'first_seen'    => '2026-01-01',
				)
			)
		);
		PluginSwap::swap( 'catalog', $provider );

		ob_start();
		AnchorMetaBox::render( $post );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( '</form>', $html );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]+required/i', $html );
		$this->assertDoesNotMatchRegularExpression( '/<select[^>]+required/i', $html );
		$this->assertStringContainsString( 'name="twbce_cijena_anchor_nonce"', $html );
		$this->assertStringContainsString( 'action=' . AnchorMetaBox::ACTION, $html );
		$this->assertStringContainsString( 'formaction=', $html );
		$this->assertStringContainsString( '<caption class="screen-reader-text">', $html );
	}

	/**
	 * SpecialSaleMetaBox::render produces clean form markup without nested forms or blocking required fields.
	 *
	 * @return void
	 */
	public function test_special_sale_metabox_render_markup_safety() {
		$post     = (object) array(
			'ID'        => 200,
			'post_type' => 'product',
		);
		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_item' )->willReturn(
			new CatalogItem(
				array(
					'id'            => 200,
					'name'          => 'Test Product',
					'sku'           => 'TEST-200',
					'regular_price' => 29.99,
					'current_price' => 29.99,
					'currency'      => 'EUR',
				)
			)
		);
		PluginSwap::swap( 'catalog', $provider );

		ob_start();
		SpecialSaleMetaBox::render( $post );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( '</form>', $html );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]+required/i', $html );
		$this->assertDoesNotMatchRegularExpression( '/<select[^>]+required/i', $html );
		$this->assertStringContainsString( 'name="twbce_cijena_sale_nonce"', $html );
		$this->assertStringContainsString( 'action=' . SpecialSaleMetaBox::ACTION, $html );
		$this->assertStringContainsString( 'placeholder="e.g. Spring Sale"', $html );
	}

	/**
	 * Dedicated action: AnchorMetaBox::handle_record successfully records anchor and redirects back.
	 *
	 * @return void
	 */
	public function test_anchor_record_handler_success() {
		global $wpdb;
		$initial_insert_id = $wpdb->insert_id;

		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$item     = new CatalogItem(
			array(
				'id'            => 300,
				'name'          => 'Widget',
				'sku'           => 'WDG-300',
				'regular_price' => 19.99,
				'current_price' => 19.99,
				'currency'      => 'EUR',
			)
		);
		$provider->method( 'get_item' )->with( 300 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_anchor_nonce'] = 'test-nonce';
		$_POST['product_id']                = '300';
		$_POST['anchor_price']              = '15.50';
		$_POST['reference_date']            = '2026-09-10';

		try {
			AnchorMetaBox::handle_record();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringContainsString( 'post=300', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=success', $e->getMessage() );
		}

		$this->assertGreaterThan( $initial_insert_id, $wpdb->insert_id );
	}

	/**
	 * Dedicated action: AnchorMetaBox::handle_record handles missing catalog item gracefully.
	 *
	 * @return void
	 */
	public function test_anchor_record_handler_missing_item_redirects_safely() {
		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_item' )->willReturn( null );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_anchor_nonce'] = 'test-nonce';
		$_POST['product_id']                = '999';

		try {
			AnchorMetaBox::handle_record();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=error', $e->getMessage() );
		}
	}

	/**
	 * Dedicated action: SpecialSaleMetaBox::handle_save records sale and redirects back.
	 *
	 * @return void
	 */
	public function test_special_sale_save_handler_success() {
		global $wpdb;
		$initial_insert_id = $wpdb->insert_id;

		Settings::update( array( 'sale_types' => array( 'seasonal' ) ) );

		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$item     = new CatalogItem(
			array(
				'id'            => 400,
				'name'          => 'Seasonal Item',
				'sku'           => 'SEAS-400',
				'regular_price' => 50.00,
				'current_price' => 50.00,
				'currency'      => 'EUR',
			)
		);
		$provider->method( 'get_item' )->with( 400 )->willReturn( $item );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_sale_nonce'] = 'test-nonce';
		$_POST['product_id']              = '400';
		$_POST['sale_type']               = 'seasonal';
		$_POST['sale_name']               = 'Autumn Sale';
		$_POST['start_date']              = '2026-10-01';
		$_POST['promo_price']             = '39.99';

		try {
			SpecialSaleMetaBox::handle_save();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringContainsString( 'post=400', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=success', $e->getMessage() );
		}

		$this->assertGreaterThan( $initial_insert_id, $wpdb->insert_id );
	}

	/**
	 * SpecialSaleMetaBox::handle_save handles missing item without generating broken post.php?post=0.
	 *
	 * @return void
	 */
	public function test_special_sale_save_missing_item_redirects_cleanly() {
		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_item' )->willReturn( null );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_sale_nonce'] = 'test-nonce';
		$_POST['product_id']              = '0';

		try {
			SpecialSaleMetaBox::handle_save();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringNotContainsString( 'post=0', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=error', $e->getMessage() );
		}
	}

	/**
	 * Variable product: AnchorMetaBox::render_product_data_panel renders variation-keyed inputs.
	 *
	 * @return void
	 */
	public function test_variable_product_panel_render() {
		$GLOBALS['post']                        = new \WP_Post(
			array(
				'ID'        => 500,
				'post_type' => 'product',
			)
		);
		$GLOBALS['twbce_test_posts'][501]       = new \WP_Post(
			array(
				'ID'        => 501,
				'post_type' => 'product_variation',
			)
		);
		$GLOBALS['twbce_test_wc_products'][500] = array(
			'id'       => 500,
			'type'     => 'variable',
			'children' => array( 501 ),
		);

		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_items' )->willReturn(
			array(
				new CatalogItem(
					array(
						'id'            => 500,
						'variation_id'  => 501,
						'name'          => 'T-shirt - Blue',
						'sku'           => 'TSHIRT-BLU',
						'regular_price' => 20.00,
						'current_price' => 20.00,
						'currency'      => 'EUR',
					)
				),
			)
		);
		$provider->method( 'get_item' )->with( 501 )->willReturn(
			new CatalogItem(
				array(
					'id'            => 500,
					'variation_id'  => 501,
					'name'          => 'T-shirt - Blue',
					'sku'           => 'TSHIRT-BLU',
					'regular_price' => 20.00,
					'current_price' => 20.00,
					'currency'      => 'EUR',
				)
			)
		);
		PluginSwap::swap( 'catalog', $provider );

		ob_start();
		AnchorMetaBox::render_product_data_panel();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'sidrena-cijena-variation-evidence', $html );
		$this->assertStringContainsString( 'name="anchor_price[501]"', $html );
		$this->assertStringContainsString( 'name="reference_date[501]"', $html );
		$this->assertStringContainsString( 'variation_id=501', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]+required/i', $html );
	}

	/**
	 * Variable product: AnchorMetaBox::handle_record processes variation array inputs.
	 *
	 * @return void
	 */
	public function test_variable_product_anchor_record_handler() {
		global $wpdb;
		$initial_insert_id = $wpdb->insert_id;

		$provider  = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$variation = new CatalogItem(
			array(
				'id'            => 500,
				'variation_id'  => 501,
				'name'          => 'T-shirt - Blue',
				'sku'           => 'TSHIRT-BLU',
				'regular_price' => 20.00,
				'current_price' => 20.00,
				'currency'      => 'EUR',
			)
		);
		$provider->method( 'get_variation' )->with( 500, 501 )->willReturn( $variation );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_anchor_nonce'] = 'test-nonce';
		$_POST['product_id']                = '500';
		$_REQUEST['variation_id']           = '501';
		$_POST['anchor_price']              = array( 501 => '18.00' );
		$_POST['reference_date']            = array( 501 => '2026-09-10' );

		try {
			AnchorMetaBox::handle_record();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringContainsString( 'post=500', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=success', $e->getMessage() );
		}

		$this->assertGreaterThan( $initial_insert_id, $wpdb->insert_id );
	}

	/**
	 * AnchorMetaBox::handle_record safely redirects to admin_url when item cannot be loaded and edit link is absent.
	 *
	 * @return void
	 */
	public function test_anchor_record_redirects_safely_when_item_not_found() {
		$provider = $this->createMock( \Titanworks\SidrenaCijena\Catalog\CatalogProvider::class );
		$provider->method( 'get_item' )->willReturn( null );
		PluginSwap::swap( 'catalog', $provider );

		$_POST['twbce_cijena_anchor_nonce'] = 'test-nonce';
		$_POST['product_id']                = '999999';

		try {
			AnchorMetaBox::handle_record();
			$this->fail( 'Expected redirect exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'REDIRECT:', $e->getMessage() );
			$this->assertStringContainsString( 'twbce_cijena_notice=error', $e->getMessage() );
			$this->assertStringContainsString( admin_url(), $e->getMessage() );
		}
	}
}
