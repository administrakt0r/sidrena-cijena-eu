<?php
/**
 * Tests for StandalonePostType.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Catalog
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\StandaloneCatalogProvider;
use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Plugin;
use WP_Post;

/**
 * @covers \Titanworks\SidrenaCijena\Catalog\StandalonePostType
 */
class StandalonePostTypeTest extends TestCase {

	/**
	 * Reset global test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_POST                                 = array();
		$GLOBALS['twbce_test_options']         = array();
		$GLOBALS['twbce_test_posts']           = array();
		$GLOBALS['twbce_test_post_meta']       = array();
		$GLOBALS['twbce_test_post_terms']      = array();
		$GLOBALS['twbce_test_post_types']      = array();
		$GLOBALS['twbce_test_taxonomies']      = array();
		$GLOBALS['twbce_test_terms']           = array();
		$GLOBALS['twbce_test_meta_boxes']      = array();
		$GLOBALS['twbce_test_history']         = array();
		$GLOBALS['twbce_test_actions']         = array();
		$GLOBALS['twbce_test_current_user_id'] = 1;

		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );
	}

	/**
	 * Tear down global test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['twbce_test_options'],
			$GLOBALS['twbce_test_posts'],
			$GLOBALS['twbce_test_post_meta'],
			$GLOBALS['twbce_test_post_terms'],
			$GLOBALS['twbce_test_post_types'],
			$GLOBALS['twbce_test_taxonomies'],
			$GLOBALS['twbce_test_terms'],
			$GLOBALS['twbce_test_meta_boxes'],
			$GLOBALS['twbce_test_history'],
			$GLOBALS['twbce_test_actions'],
			$GLOBALS['twbce_test_current_user_id']
		);
		parent::tearDown();
	}

	/**
	 * Registration configures post type, taxonomies, and initial terms.
	 *
	 * @return void
	 */
	public function test_register_and_seed_terms(): void {
		StandalonePostType::register();

		$this->assertContains( StandalonePostType::POST_TYPE, $GLOBALS['twbce_test_post_types'] );
		$this->assertContains( StandalonePostType::TAX_BRAND, $GLOBALS['twbce_test_taxonomies'] );
		$this->assertContains( StandalonePostType::TAX_TYPE, $GLOBALS['twbce_test_taxonomies'] );
		$this->assertSame( 1, get_option( 'twbce_cijena_terms_seeded' ) );
		$this->assertTrue( term_exists( 'product', StandalonePostType::TAX_TYPE ) );
		$this->assertTrue( term_exists( 'service', StandalonePostType::TAX_TYPE ) );
	}

	/**
	 * Meta box fields define proper structure and price types.
	 *
	 * @return void
	 */
	public function test_fields_definition(): void {
		$fields = StandalonePostType::fields();

		$this->assertArrayHasKey( '_twbce_sku', $fields );
		$this->assertArrayHasKey( '_twbce_regular_price', $fields );
		$this->assertArrayHasKey( '_twbce_sale_price', $fields );
		$this->assertArrayHasKey( '_twbce_availability', $fields );
		$this->assertSame( 'price', $fields['_twbce_regular_price']['type'] );
		$this->assertSame( 'price', $fields['_twbce_sale_price']['type'] );
		$this->assertSame( 'select', $fields['_twbce_availability']['type'] );
	}

	/**
	 * Render meta box outputs the nonce and fields.
	 *
	 * @return void
	 */
	public function test_render_meta_box(): void {
		$post = new WP_Post(
			array(
				'ID'          => 10,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Test Product',
			)
		);

		ob_start();
		StandalonePostType::render_meta_box( $post );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="twbce_cijena_item_nonce"', $html );
		$this->assertStringContainsString( 'name="_twbce_sku"', $html );
		$this->assertStringContainsString( 'name="_twbce_regular_price"', $html );
		$this->assertStringContainsString( 'name="_twbce_sale_price"', $html );
		$this->assertStringContainsString( 'name="_twbce_first_seen"', $html );
		$this->assertStringContainsString( 'class="regular-text sidrena-datepicker"', $html );
		$this->assertStringContainsString( 'placeholder="d.m.Y."', $html );
	}

	/**
	 * Save is ignored if nonce is missing.
	 *
	 * @return void
	 */
	public function test_save_ignored_without_nonce(): void {
		$_POST['_twbce_regular_price'] = '20.00';
		StandalonePostType::save( 10 );

		$this->assertEmpty( get_post_meta( 10, '_twbce_regular_price', true ) );
	}

	/**
	 * Save persists post meta and captures price changes into history.
	 *
	 * @return void
	 */
	public function test_save_persists_meta_and_captures_history(): void {
		$post_id = 45;

		$GLOBALS['twbce_test_posts'][ $post_id ] = new WP_Post(
			array(
				'ID'          => $post_id,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Čokolada 100g',
			)
		);

		$_POST['twbce_cijena_item_nonce'] = \wp_create_nonce( 'twbce_cijena_save_item' );
		$_POST['_twbce_sku']              = 'COK-01';
		$_POST['_twbce_regular_price']    = '2.49';
		$_POST['_twbce_sale_price']       = '1.99';
		$_POST['_twbce_availability']     = 'available';
		$_POST['_twbce_currency']         = 'EUR';
		$_POST['_twbce_tax_inclusive']    = '1';
		$_POST['_twbce_item_type']        = 'product';
		$_POST['_twbce_first_seen']       = '10.09.2026.';

		StandalonePostType::save( $post_id );

		$this->assertSame( 'COK-01', get_post_meta( $post_id, '_twbce_sku', true ) );
		$this->assertSame( 2.49, get_post_meta( $post_id, '_twbce_regular_price', true ) );
		$this->assertSame( 1.99, get_post_meta( $post_id, '_twbce_sale_price', true ) );
		$this->assertSame( '2026-09-10', get_post_meta( $post_id, '_twbce_first_seen', true ) );
	}
}
