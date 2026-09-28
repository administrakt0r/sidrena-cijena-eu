<?php
/**
 * Tests for StandalonePostType.
 *
 * @package SidrenaCijena\Tests\Unit\Catalog
 */

namespace SidrenaCijena\Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Plugin;
use WP_Post;

/**
 * @covers \SidrenaCijena\Catalog\StandalonePostType
 */
class StandalonePostTypeTest extends TestCase {

	/**
	 * Reset global test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_POST                                   = array();
		$GLOBALS['sidrena_test_options']         = array();
		$GLOBALS['sidrena_test_posts']           = array();
		$GLOBALS['sidrena_test_post_meta']       = array();
		$GLOBALS['sidrena_test_post_terms']      = array();
		$GLOBALS['sidrena_test_post_types']      = array();
		$GLOBALS['sidrena_test_taxonomies']      = array();
		$GLOBALS['sidrena_test_terms']           = array();
		$GLOBALS['sidrena_test_meta_boxes']      = array();
		$GLOBALS['sidrena_test_history']         = array();
		$GLOBALS['sidrena_test_actions']         = array();
		$GLOBALS['sidrena_test_current_user_id'] = 1;

		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );
	}

	/**
	 * Tear down global test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['sidrena_test_options'],
			$GLOBALS['sidrena_test_posts'],
			$GLOBALS['sidrena_test_post_meta'],
			$GLOBALS['sidrena_test_post_terms'],
			$GLOBALS['sidrena_test_post_types'],
			$GLOBALS['sidrena_test_taxonomies'],
			$GLOBALS['sidrena_test_terms'],
			$GLOBALS['sidrena_test_meta_boxes'],
			$GLOBALS['sidrena_test_history'],
			$GLOBALS['sidrena_test_actions'],
			$GLOBALS['sidrena_test_current_user_id']
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

		$this->assertContains( StandalonePostType::POST_TYPE, $GLOBALS['sidrena_test_post_types'] );
		$this->assertContains( StandalonePostType::TAX_BRAND, $GLOBALS['sidrena_test_taxonomies'] );
		$this->assertContains( StandalonePostType::TAX_TYPE, $GLOBALS['sidrena_test_taxonomies'] );
		$this->assertSame( 1, get_option( 'sidrena_cijena_terms_seeded' ) );
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

		$this->assertArrayHasKey( '_sidrena_sku', $fields );
		$this->assertArrayHasKey( '_sidrena_regular_price', $fields );
		$this->assertArrayHasKey( '_sidrena_sale_price', $fields );
		$this->assertArrayHasKey( '_sidrena_availability', $fields );
		$this->assertSame( 'price', $fields['_sidrena_regular_price']['type'] );
		$this->assertSame( 'price', $fields['_sidrena_sale_price']['type'] );
		$this->assertSame( 'select', $fields['_sidrena_availability']['type'] );
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

		$this->assertStringContainsString( 'name="sidrena_cijena_item_nonce"', $html );
		$this->assertStringContainsString( 'name="_sidrena_sku"', $html );
		$this->assertStringContainsString( 'name="_sidrena_regular_price"', $html );
		$this->assertStringContainsString( 'name="_sidrena_sale_price"', $html );
	}

	/**
	 * Save is ignored if nonce is missing.
	 *
	 * @return void
	 */
	public function test_save_ignored_without_nonce(): void {
		$_POST['_sidrena_regular_price'] = '20.00';
		StandalonePostType::save( 10 );

		$this->assertEmpty( get_post_meta( 10, '_sidrena_regular_price', true ) );
	}

	/**
	 * Save persists post meta and captures price changes into history.
	 *
	 * @return void
	 */
	public function test_save_persists_meta_and_captures_history(): void {
		$post_id = 45;

		$GLOBALS['sidrena_test_posts'][ $post_id ] = new WP_Post(
			array(
				'ID'          => $post_id,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Čokolada 100g',
			)
		);

		$_POST['sidrena_cijena_item_nonce'] = \wp_create_nonce( 'sidrena_cijena_save_item' );
		$_POST['_sidrena_sku']              = 'COK-01';
		$_POST['_sidrena_regular_price']    = '2.49';
		$_POST['_sidrena_sale_price']       = '1.99';
		$_POST['_sidrena_availability']     = 'available';
		$_POST['_sidrena_currency']         = 'EUR';
		$_POST['_sidrena_tax_inclusive']    = '1';
		$_POST['_sidrena_item_type']        = 'product';

		StandalonePostType::save( $post_id );

		$this->assertSame( 'COK-01', get_post_meta( $post_id, '_sidrena_sku', true ) );
		$this->assertSame( 2.49, get_post_meta( $post_id, '_sidrena_regular_price', true ) );
		$this->assertSame( 1.99, get_post_meta( $post_id, '_sidrena_sale_price', true ) );
	}
}
