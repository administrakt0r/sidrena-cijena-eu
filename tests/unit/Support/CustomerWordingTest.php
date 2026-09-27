<?php
/**
 * Regression tests for customer-visible Croatian wording and English fallback.
 *
 * @package SidrenaCijena\Tests\Unit\Support
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\DisplaySettingsPage;
use SidrenaCijena\Api\TemplateFunctions;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Support\Labels;
use SidrenaCijena\Support\Settings;
use SidrenaCijena\Tests\Support\PluginSwap;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;

require_once dirname( __DIR__ ) . '/wc-stubs.php';

/**
 * Double for PriceDisplay that exposes render_thirty_day for unit testing.
 */
class TestablePriceDisplay extends PriceDisplay {
	/**
	 * Constructor without dependencies.
	 */
	public function __construct() {}

	/**
	 * Public proxy for protected render_thirty_day.
	 *
	 * @param CatalogItem  $item    Catalog item.
	 * @param string       $context Rendering context.
	 * @param array<mixed> $args    Rendering overrides.
	 * @return string
	 */
	public function call_render_thirty_day( CatalogItem $item, $context = 'single', array $args = array() ) {
		return $this->render_thirty_day( $item, $context, $args );
	}
}

/**
 * In-memory special sales fake.
 */
class WordingSaleFake extends SpecialSaleRepository {
	/**
	 * Active sale rows keyed by product:variation.
	 *
	 * @var array<string,array<mixed>>
	 */
	public $map = array();

	/**
	 * Active sales map.
	 *
	 * @param string $at Reference time.
	 * @return array<string,array<mixed>>
	 */
	public function active_map( $at = '' ) {
		unset( $at );

		return $this->map;
	}
}

/**
 * @covers \SidrenaCijena\Support\Settings
 * @covers \SidrenaCijena\Admin\DisplaySettingsPage
 * @covers \SidrenaCijena\WooCommerce\Display\PriceDisplay
 * @covers \SidrenaCijena\Api\TemplateFunctions
 */
class CustomerWordingTest extends TestCase {

	/**
	 * Clean stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']           = array();
		$GLOBALS['sidrena_test_transients']        = array();
		$GLOBALS['sidrena_test_textdomains']       = array();
		$GLOBALS['sidrena_test_translations']      = array();
		$GLOBALS['sidrena_test_settings_fields']   = array();
		$GLOBALS['sidrena_test_settings_sections'] = array();
		$GLOBALS['sidrena_test_locale']            = 'en_US';
		$GLOBALS['wp_filter']                      = array();
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'special_sales', null );
		$GLOBALS['wp_filter'] = array();

		parent::tearDown();
	}

	/**
	 * Default 30-day reference label in Croatian catalog is unmistakably the lowest price in preceding 30 days.
	 *
	 * @return void
	 */
	public function test_croatian_catalog_reference_label_is_lowest_price_not_normal_or_recommended() {
		$dictionary = require dirname( __DIR__, 3 ) . '/tools/i18n/hr_HR.php';

		$lowest_label = isset( $dictionary['Lowest price in the last 30 days'] )
			? $dictionary['Lowest price in the last 30 days']
			: '';

		$this->assertSame( 'Najniža cijena u zadnjih 30 dana', $lowest_label );

		// Negative checks: the statutory reference must never be called normal, regular, or recommended price.
		$this->assertStringNotContainsStringIgnoringCase( 'redovna', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'preporučena', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'originalna', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'uobičajena', $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( 'sidrena', $lowest_label );
	}

	/**
	 * The anchor price label in Croatian is distinct ("Sidrena cijena") and never substituted for 30-day lowest price.
	 *
	 * @return void
	 */
	public function test_anchor_price_label_is_distinct_from_30_day_lowest_price() {
		$dictionary = require dirname( __DIR__, 3 ) . '/tools/i18n/hr_HR.php';

		$anchor_label = isset( $dictionary['Anchor price'] ) ? $dictionary['Anchor price'] : '';
		$lowest_label = isset( $dictionary['Lowest price in the last 30 days'] ) ? $dictionary['Lowest price in the last 30 days'] : '';

		$this->assertSame( 'Sidrena cijena', $anchor_label );
		$this->assertNotSame( $anchor_label, $lowest_label );
		$this->assertStringNotContainsStringIgnoringCase( '30', $anchor_label );
		$this->assertStringNotContainsStringIgnoringCase( 'najniža', $anchor_label );
	}

	/**
	 * DisplaySettingsPage registers thirty_day_label field so merchants can configure the reference label.
	 *
	 * @return void
	 */
	public function test_display_settings_page_registers_thirty_day_label_field() {
		DisplaySettingsPage::register_settings();

		$keys = array();
		if ( isset( $GLOBALS['sidrena_test_settings_fields'][ DisplaySettingsPage::PAGE ] ) ) {
			foreach ( $GLOBALS['sidrena_test_settings_fields'][ DisplaySettingsPage::PAGE ] as $field ) {
				$keys[] = $field['id'];
			}
		}

		$this->assertContains( 'sidrena_cijena_anchor_label', $keys );
		$this->assertContains( 'sidrena_cijena_thirty_day_label', $keys );
	}

	/**
	 * Settings correctly sanitizes and persists thirty_day_label.
	 *
	 * @return void
	 */
	public function test_settings_sanitizes_and_updates_thirty_day_label() {
		$sanitized = Settings::sanitize(
			array(
				'thirty_day_label' => '<b>Najniža cijena (prethodnih 30 dana)</b>',
			)
		);

		$this->assertSame( 'Najniža cijena (prethodnih 30 dana)', $sanitized['thirty_day_label'] );

		Settings::update(
			array(
				'thirty_day_label' => 'Prilagođena najniža cijena',
			)
		);

		$this->assertSame( 'Prilagođena najniža cijena', Settings::get( 'thirty_day_label' ) );
	}

	/**
	 * PriceDisplay::render_thirty_day uses the configured label, supports args override, and applies filter.
	 *
	 * @return void
	 */
	public function test_render_thirty_day_supports_settings_args_and_filter() {
		$fake              = new WordingSaleFake();
		$fake->map['10:0'] = array(
			'sale_type'           => 'akcija',
			'sale_name'           => 'Test akcija',
			'start_date'          => '2026-09-20',
			'promo_price'         => 15.00,
			'lowest_30_day_price' => 12.00,
		);
		PluginSwap::swap( 'special_sales', $fake );

		$item = new CatalogItem(
			array(
				'id'            => 10,
				'variation_id'  => 0,
				'type'          => 'product',
				'name'          => 'Test Proizvod',
				'currency'      => 'EUR',
				'current_price' => 15.00,
			)
		);

		$display = new TestablePriceDisplay();

		// 1. Default label from settings.
		Settings::update( array( 'thirty_day_label' => 'Najniža cijena u zadnjih 30 dana' ) );
		$html = $display->call_render_thirty_day( $item );
		$this->assertStringContainsString( 'Najniža cijena u zadnjih 30 dana', $html );
		$this->assertStringContainsString( '12,00', $html );

		// 2. Custom override via $args.
		$html_args = $display->call_render_thirty_day( $item, 'single', array( 'thirty_day_label' => 'Posebna referentna cijena' ) );
		$this->assertStringContainsString( 'Posebna referentna cijena', $html_args );

		// 3. Filter hook sidrena_cijena_thirty_day_label.
		add_filter(
			'sidrena_cijena_thirty_day_label',
			function ( $label, $filter_item, $context ) {
				return $label . ' (filtrirano ' . $context . ')';
			},
			10,
			3
		);

		$html_filtered = $display->call_render_thirty_day( $item, 'archive' );
		$this->assertStringContainsString( 'Najniža cijena u zadnjih 30 dana (filtrirano archive)', $html_filtered );
	}

	/**
	 * English fallback behaviour: when translation is not loaded, English strings render cleanly.
	 *
	 * @return void
	 */
	public function test_english_fallback_when_translations_not_loaded() {
		// No translations loaded in $GLOBALS['sidrena_test_translations'].
		$defaults = Settings::defaults();

		$this->assertSame( 'Lowest price in the last 30 days', $defaults['thirty_day_label'] );
		$this->assertSame( 'Lowest price in the last 30 days', Labels::price_type( 'lowest_30_day' ) );
		$this->assertSame( 'Anchor price', Labels::price_type( 'anchor' ) );
		$this->assertSame( 'Regular price', Labels::price_type( 'regular' ) );
		$this->assertSame( 'Sale price', Labels::price_type( 'sale' ) );
	}

	/**
	 * Textdomain loading respects existing loaded domain and allows filtering Croatian default.
	 *
	 * @return void
	 */
	public function test_textdomain_loading_and_filter() {
		if ( ! function_exists( 'sidrena_cijena_load_textdomain' ) ) {
			require_once dirname( __DIR__, 3 ) . '/sidrena-cijena.php';
		}

		// 1. By default, loads Croatian catalog.
		unload_textdomain( 'sidrena-cijena-eu' );
		sidrena_cijena_load_textdomain();
		$this->assertTrue( is_textdomain_loaded( 'sidrena-cijena-eu' ) );
		$this->assertStringEndsWith( 'sidrena-cijena-eu-hr.mo', $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );

		// 2. Already loaded - calling again does not reload.
		$first_loaded = $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'];
		sidrena_cijena_load_textdomain();
		$this->assertSame( $first_loaded, $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );

		// 3. Filter out Croatian default in en_US locale -> loads plugin textdomain for fallback.
		unload_textdomain( 'sidrena-cijena-eu' );
		add_filter( 'sidrena_cijena_load_croatian_catalog', '__return_false' );
		sidrena_cijena_load_textdomain();

		$this->assertTrue( is_textdomain_loaded( 'sidrena-cijena-eu' ) );
		$this->assertStringNotContainsString( 'sidrena-cijena-eu-hr.mo', $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );
	}
}
