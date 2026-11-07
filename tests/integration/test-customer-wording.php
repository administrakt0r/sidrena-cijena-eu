<?php
/**
 * Integration tests for Croatian customer wording, translation loading, and configurable labels.
 *
 * @package Titanworks\SidrenaCijena\Tests\Integration
 */

use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay;

/**
 * Customer wording and translation loading integration tests.
 */
class Test_Customer_Wording extends WP_UnitTestCase {

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// Ensure textdomain is in a known loaded state.
		titanworks_bazna_cijena_load_textdomain();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		// Restore default textdomain loading.
		titanworks_bazna_cijena_load_textdomain();

		parent::tear_down();
	}

	/**
	 * Verified Croatian customer-facing strings match legal requirements and catalog.
	 *
	 * @return void
	 */
	public function test_rendered_croatian_customer_strings() {
		$lowest_label = __( 'Lowest price in the last 30 days', 'sidrena-cijena-eu' );
		$this->assertSame( 'Najniža cijena u zadnjih 30 dana', $lowest_label );

		$anchor_label = __( 'Anchor price', 'sidrena-cijena-eu' );
		$this->assertSame( 'Sidrena cijena', $anchor_label );

		/* translators: %s: reference date */
		$date_template = __( 'Cijena na dan %s', 'sidrena-cijena-eu' );
		$this->assertSame( 'Cijena na dan %s', $date_template );

		$cart_label = __( 'Reference prices per item:', 'sidrena-cijena-eu' );
		$this->assertSame( 'Referentne cijene po komadu:', $cart_label );

		$setting_label = __( 'Lowest 30-day price label', 'sidrena-cijena-eu' );
		$this->assertSame( 'Oznaka najniže cijene u zadnjih 30 dana', $setting_label );
	}

	/**
	 * Reference price is unmistakably the lowest price in preceding 30 days and never normal/recommended.
	 *
	 * @return void
	 */
	public function test_reference_is_lowest_price_not_normal_or_recommended() {
		$lowest_label = __( 'Lowest price in the last 30 days', 'sidrena-cijena-eu' );

		$this->assertStringContainsString( 'Najniža cijena', $lowest_label );
		$this->assertStringContainsString( '30 dana', $lowest_label );

		// Forbidden misleading labels under Article 19 ZZP.
		$this->assertStringNotContainsStringIgnoringCase( 'redovna', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'preporučena', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'originalna', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'uobičajena', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'sidrena', $lowest_label );
	}

	/**
	 * Configurable labels in settings and filters update rendered storefront output.
	 *
	 * @return void
	 */
	public function test_configurable_labels_and_filters_affect_storefront_render() {
		$plugin = Plugin::instance();

		$post_id = $this->factory->post->create(
			array(
				'post_title' => 'Test Wording Proizvod',
				'post_type'  => 'product',
			)
		);

		$item = new CatalogItem(
			array(
				'id'            => $post_id,
				'variation_id'  => 0,
				'type'          => 'product',
				'name'          => 'Test Wording Proizvod',
				'currency'      => 'EUR',
				'current_price' => 20.00,
			)
		);

		$plugin->anchors()->record(
			$item,
			25.00,
			array(
				'source'         => 'manual',
				'reference_date' => '2026-09-10',
			)
		);

		$plugin->special_sales()->insert(
			array(
				'product_id'          => $post_id,
				'variation_id'        => 0,
				'sale_type'           => 'akcija',
				'sale_name'           => 'Jesenska akcija',
				'start_date'          => '2026-09-20',
				'promo_price'         => 18.00,
				'lowest_30_day_price' => 15.00,
				'status'              => 'active',
			)
		);

		$display = new PriceDisplay( $plugin->anchors() );

		// 1. Default render contains Croatian default labels.
		$html = $display->render_item( $item );
		$this->assertStringContainsString( 'Najniža cijena u zadnjih 30 dana', $html );
		$this->assertStringContainsString( 'Sidrena cijena', $html );
		$this->assertStringContainsString( '15,00', $html );
		$this->assertStringContainsString( '25,00', $html );

		// 2. Configured thirty_day_label in settings changes the reference label.
		Settings::update( array( 'thirty_day_label' => 'Najniža cijena u prethodnih 30 dana' ) );
		$html_custom = $display->render_item( $item );
		$this->assertStringContainsString( 'Najniža cijena u prethodnih 30 dana', $html_custom );

		// 3. Filter twbce_cijena_thirty_day_label applies.
		add_filter(
			'twbce_cijena_thirty_day_label',
			function ( $label ) {
				return 'Filtrirana referentna: ' . $label;
			}
		);

		$html_filtered = $display->render_item( $item );
		$this->assertStringContainsString( 'Filtrirana referentna: Najniža cijena u prethodnih 30 dana', $html_filtered );
	}

	/**
	 * English fallback occurs when translation is not loaded or filtered off.
	 *
	 * @return void
	 */
	public function test_english_fallback_behavior() {
		unload_textdomain( 'sidrena-cijena-eu' );

		// With Croatian default filtered to false, on an en_US site it should use English fallback.
		add_filter( 'twbce_cijena_load_croatian_catalog', '__return_false' );
		titanworks_bazna_cijena_load_textdomain();

		// In English fallback, strings return the original English source text.
		$this->assertSame( 'Lowest price in the last 30 days', __( 'Lowest price in the last 30 days', 'sidrena-cijena-eu' ) );
		$this->assertSame( 'Anchor price', __( 'Anchor price', 'sidrena-cijena-eu' ) );
		$this->assertSame( 'Reference prices per item:', __( 'Reference prices per item:', 'sidrena-cijena-eu' ) );
		$this->assertSame( 'Lowest 30-day price label', __( 'Lowest 30-day price label', 'sidrena-cijena-eu' ) );

		remove_filter( 'twbce_cijena_load_croatian_catalog', '__return_false' );
	}
}
