<?php
/**
 * Settings tests.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Support\Settings;

/**
 * @covers \SidrenaCijena\Support\Settings
 */
class SettingsTest extends TestCase {

	/**
	 * Reset option state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
		Settings::reset_cache();
		Settings::register();
	}

	/**
	 * Defaults include the legally required label.
	 *
	 * @return void
	 */
	public function test_defaults() {
		$defaults = Settings::defaults();

		$this->assertSame( 'Sidrena cijena', $defaults['anchor_label'] );
		$this->assertSame( 'Najniža cijena u zadnjih 30 dana', $defaults['thirty_day_label'] );
		$this->assertSame( 'tooltip', $defaults['product_display_style'] );
		$this->assertSame( 'tooltip', $defaults['anchor_date_mode'] );
		$this->assertSame( array( 'csv', 'xml' ), $defaults['formats'] );
		$this->assertSame( 30, $defaults['retention_days'] );
		$this->assertSame( '08:00', $defaults['deadline'] );
		$this->assertFalse( $defaults['delete_data_on_uninstall'] );
	}

	/**
	 * Stored values merge over defaults.
	 *
	 * @return void
	 */
	public function test_all_merges_stored_values() {
		update_option( Settings::OPTION, array( 'anchor_label' => 'Referentna cijena' ) );

		$this->assertSame( 'Referentna cijena', Settings::get( 'anchor_label' ) );
		$this->assertTrue( Settings::get( 'display_enabled' ) );
	}

	/**
	 * Unknown keys return the provided fallback.
	 *
	 * @return void
	 */
	public function test_get_unknown_key() {
		$this->assertNull( Settings::get( 'does_not_exist' ) );
		$this->assertSame( 'fallback', Settings::get( 'does_not_exist', 'fallback' ) );
	}

	/**
	 * Update only stores known keys.
	 *
	 * @return void
	 */
	public function test_update_ignores_unknown_keys() {
		Settings::update(
			array(
				'anchor_label' => 'Nova oznaka',
				'evil_key'     => 'value',
			)
		);

		$this->assertSame( 'Nova oznaka', Settings::get( 'anchor_label' ) );
		$this->assertNull( Settings::get( 'evil_key' ) );
	}

	/**
	 * Sanitization clamps retention to the legal minimum.
	 *
	 * @return void
	 */
	public function test_sanitize_enforces_minimum_retention() {
		$sanitized = Settings::sanitize( array( 'retention_days' => 5 ) );

		$this->assertSame( 30, $sanitized['retention_days'] );
	}

	/**
	 * Malformed settings payloads keep the last safe values.
	 *
	 * @return void
	 */
	public function test_sanitize_handles_non_array_payload() {
		$this->assertSame( Settings::all(), Settings::sanitize( 'invalid' ) );
	}

	/**
	 * Presets and size remain in their supported ranges.
	 *
	 * @return void
	 */
	public function test_styling_sanitization() {
		$this->assertSame( 40, Settings::defaults()['display_size'] );
		$this->assertSame( 'pill', Settings::sanitize( array( 'product_display_style' => 'pill' ) )['product_display_style'] );
		$this->assertSame( 160, Settings::sanitize( array( 'display_size' => 300 ) )['display_size'] );
		$this->assertSame( 40, Settings::sanitize( array( 'display_size' => 0 ) )['display_size'] );
		$this->assertSame( 'tooltip', Settings::sanitize( array( 'product_display_style' => array( 'evil' ) ) )['product_display_style'] );
	}

	/**
	 * Sanitization rejects invalid deadline values and normalizes 24h/AM-PM values.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_invalid_deadline() {
		$sanitized = Settings::sanitize( array( 'deadline' => '25:99' ) );
		$this->assertSame( '08:00', $sanitized['deadline'] );

		// 8:00 normalizes to 08:00
		$sanitized2 = Settings::sanitize( array( 'deadline' => '8:00' ) );
		$this->assertSame( '08:00', $sanitized2['deadline'] );

		// 2:30 PM normalizes to 14:30
		$sanitized3 = Settings::sanitize( array( 'deadline' => '2:30 PM' ) );
		$this->assertSame( '14:30', $sanitized3['deadline'] );

		// 14:45 stays 14:45
		$sanitized4 = Settings::sanitize( array( 'deadline' => '14:45' ) );
		$this->assertSame( '14:45', $sanitized4['deadline'] );
	}

	/**
	 * Sanitization intersects formats with the supported set.
	 *
	 * @return void
	 */
	public function test_sanitize_formats() {
		$sanitized = Settings::sanitize( array( 'formats' => array( 'xml', 'pdf' ) ) );

		$this->assertSame( array( 'xml' ), $sanitized['formats'] );
	}

	/**
	 * Product presentation presets accept only the built-in styles.
	 *
	 * @return void
	 */
	public function test_product_display_style_accepts_presets_and_rejects_unknown_values() {
		$this->assertSame( 'badge', Settings::sanitize( array( 'product_display_style' => 'badge' ) )['product_display_style'] );
		$this->assertSame( 'tooltip', Settings::sanitize( array( 'product_display_style' => 'custom-css' ) )['product_display_style'] );
	}

	/**
	 * Checkbox-style settings are booleanized when they are submitted.
	 *
	 * @return void
	 */
	public function test_sanitize_booleans() {
		$sanitized = Settings::sanitize(
			array(
				'display_enabled'     => '1',
				'only_when_different' => '0',
			)
		);

		$this->assertTrue( $sanitized['display_enabled'] );
		$this->assertFalse( $sanitized['only_when_different'] );
	}

	/**
	 * Flags belonging to another settings tab keep their stored value when a
	 * partial form is submitted (each tab posts only its own fields).
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_flags_not_in_submission() {
		$sanitized = Settings::sanitize( array( 'anchor_label' => 'Cijena' ) );

		$this->assertTrue( $sanitized['display_enabled'] );
		$this->assertTrue( $sanitized['display_product_pages'] );
		$this->assertTrue( $sanitized['display_archives'] );
		$this->assertTrue( $sanitized['display_variations'] );
		$this->assertFalse( $sanitized['only_when_different'] );
		$this->assertFalse( $sanitized['delete_data_on_uninstall'] );
	}

	/**
	 * Settings are memoized per request and invalidated on change.
	 *
	 * @return void
	 */
	public function test_settings_memoization_and_invalidation() {
		// Prime memoization with default value.
		$first = Settings::all();
		$this->assertSame( 'Sidrena cijena', Settings::get( 'anchor_label' ) );

		// Repeated call returns identical memoized array instance.
		$second = Settings::all();
		$this->assertSame( $first, $second );

		// Invalidation via action clears the memoized cache.
		do_action( 'sidrena_cijena_settings_changed', array(), array() );
		$third = Settings::all();
		$this->assertSame( 'Sidrena cijena', $third['anchor_label'] );

		// Settings::update() automatically invalidates and updates the cache.
		Settings::update( array( 'anchor_label' => 'Updated via API' ) );
		$this->assertSame( 'Updated via API', Settings::get( 'anchor_label' ) );
	}

	/**
	 * Legacy false tooltip text is automatically migrated to the accurate legal definition.
	 *
	 * @return void
	 */
	public function test_legacy_tooltip_text_migration() {
		update_option(
			Settings::OPTION,
			array(
				'anchor_tooltip_text' => 'Sidrena cijena je najniža cijena u zadnjih 30 dana prije početka posebnog oblika prodaje.',
			)
		);
		Settings::reset_cache();

		$migrated = Settings::get( 'anchor_tooltip_text' );
		$this->assertSame(
			'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.',
			$migrated
		);
	}
}
