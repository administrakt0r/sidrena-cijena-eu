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
require_once dirname( __DIR__ ) . '/PluginSwap.php';

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

	/**
	 * Public proxy for protected load_template.
	 *
	 * @param array<mixed> $args Template arguments.
	 * @return string
	 */
	public function call_load_template( array $args ) {
		return $this->load_template( $args );
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

		$this->assertSame( 'Najniža cijena u zadnjih 30 dana', $defaults['thirty_day_label'] );
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

		// 1. With Croatian locale, loads Croatian catalog.
		unload_textdomain( 'sidrena-cijena-eu' );
		$GLOBALS['sidrena_test_locale'] = 'hr_HR';
		sidrena_cijena_load_textdomain();
		$this->assertTrue( is_textdomain_loaded( 'sidrena-cijena-eu' ) );
		$this->assertStringEndsWith( 'sidrena-cijena-eu-hr.mo', $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );

		// 2. Already loaded - calling again does not reload.
		$first_loaded = $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'];
		sidrena_cijena_load_textdomain();
		$this->assertSame( $first_loaded, $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );

		// 3. In en_US locale, Croatian catalog is not loaded (WP core handles translations JIT).
		unload_textdomain( 'sidrena-cijena-eu' );
		$GLOBALS['sidrena_test_locale'] = 'en_US';
		sidrena_cijena_load_textdomain();
		$this->assertFalse( is_textdomain_loaded( 'sidrena-cijena-eu' ) );

		// 4. In en_US locale with filter forcing Croatian -> loads Croatian catalog.
		unload_textdomain( 'sidrena-cijena-eu' );
		add_filter( 'sidrena_cijena_load_croatian_catalog', '__return_true' );
		sidrena_cijena_load_textdomain();
		$this->assertTrue( is_textdomain_loaded( 'sidrena-cijena-eu' ) );
		$this->assertStringEndsWith( 'sidrena-cijena-eu-hr.mo', $GLOBALS['sidrena_test_textdomains']['sidrena-cijena-eu'] );
		remove_filter( 'sidrena_cijena_load_croatian_catalog', '__return_true' );
	}

	/**
	 * Anchor price template renders tooltip popup by default with unobtrusive (?) trigger.
	 *
	 * @return void
	 */
	public function test_anchor_price_template_renders_default_tooltip_style_and_popup() {
		$display = new TestablePriceDisplay();
		$args    = array(
			'context'                => 'archive',
			'label'                  => 'Sidrena cijena',
			'anchor_price'           => 9.99,
			'anchor_display'         => '9,99 €',
			'current_price'          => 12.99,
			'current_display'        => '12,99 €',
			'show_current'           => false,
			'show_anchor'            => true,
			'reference_date'         => '2026-09-15',
			'reference_date_display' => '15. 9. 2026.',
			'anchor_title'           => 'Sidrena cijena (15. 9. 2026.)',
			'disclaimer'             => '',
			'style'                  => 'tooltip',
			'date_mode'              => 'tooltip',
			'tooltip_text'           => 'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.',
			'font_weight'            => 'normal',
			'product_id'             => 10,
			'variation_id'           => 0,
		);

		$html = $display->call_load_template( $args );

		$this->assertStringContainsString( 'sidrena-cijena--style-tooltip', $html );
		$this->assertStringContainsString( 'sidrena-cijena__help-trigger', $html );
		$this->assertStringContainsString( '?', $html );
		$this->assertStringContainsString( 'data-sidrena-cijena-tooltip-trigger', $html );
		$this->assertStringContainsString( 'sidrena-cijena__tooltip-popup', $html );
		$this->assertStringContainsString( 'data-sidrena-cijena-tooltip-popup', $html );
		$this->assertStringContainsString( 'data-sidrena-cijena-tooltip-date', $html );
		$this->assertStringContainsString( '15. 9. 2026.', $html );
		$this->assertStringContainsString( '9,99 €', $html );
		$this->assertStringNotContainsString( 'sidrena-cijena__reference-date', $html );
	}

	/**
	 * Anchor price template respects date_mode (inline, subtext, hidden) and font_weight.
	 *
	 * @return void
	 */
	public function test_anchor_price_template_respects_date_modes_and_font_weights() {
		$display = new TestablePriceDisplay();
		$base    = array(
			'context'                => 'single',
			'label'                  => 'Sidrena cijena',
			'anchor_price'           => 19.99,
			'anchor_display'         => '19,99 €',
			'current_price'          => 24.99,
			'current_display'        => '24,99 €',
			'show_current'           => false,
			'show_anchor'            => true,
			'reference_date'         => '2026-09-01',
			'reference_date_display' => '1. 9. 2026.',
			'anchor_title'           => 'Sidrena cijena (1. 9. 2026.)',
			'disclaimer'             => '',
			'product_id'             => 10,
			'variation_id'           => 0,
		);

		// 1. Inline mode with bold weight.
		$inline_args                = $base;
		$inline_args['style']       = 'badge';
		$inline_args['date_mode']   = 'inline';
		$inline_args['font_weight'] = 'bold';
		$inline_html                = $display->call_load_template( $inline_args );
		$this->assertStringContainsString( 'sidrena-cijena--style-badge', $inline_html );
		$this->assertStringContainsString( 'sidrena-cijena--weight-bold', $inline_html );
		$this->assertStringContainsString( 'sidrena-cijena__reference-date', $inline_html );
		$this->assertStringNotContainsString( 'sidrena-cijena__reference-date--subtext', $inline_html );
		$this->assertStringNotContainsString( 'sidrena-cijena__tooltip-popup', $inline_html );

		// 2. Subtext mode with semi-bold weight.
		$subtext_args                = $base;
		$subtext_args['style']       = 'minimal';
		$subtext_args['date_mode']   = 'subtext';
		$subtext_args['font_weight'] = 'semi-bold';
		$subtext_html                = $display->call_load_template( $subtext_args );
		$this->assertStringContainsString( 'sidrena-cijena--style-minimal', $subtext_html );
		$this->assertStringContainsString( 'sidrena-cijena--weight-semibold', $subtext_html );
		$this->assertStringContainsString( 'sidrena-cijena__reference-date--subtext', $subtext_html );

		// 3. Hidden mode.
		$hidden_args              = $base;
		$hidden_args['style']     = 'tag';
		$hidden_args['date_mode'] = 'hidden';
		$hidden_html              = $display->call_load_template( $hidden_args );
		$this->assertStringContainsString( 'sidrena-cijena--style-tag', $hidden_html );
		$this->assertStringNotContainsString( 'sidrena-cijena__reference-date', $hidden_html );
		$this->assertStringNotContainsString( 'sidrena-cijena__tooltip-popup', $hidden_html );
	}
}
