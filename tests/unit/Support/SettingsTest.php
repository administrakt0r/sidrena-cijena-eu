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
	}

	/**
	 * Defaults include the legally required label.
	 *
	 * @return void
	 */
	public function test_defaults() {
		$defaults = Settings::defaults();

		$this->assertSame( 'Sidrena cijena', $defaults['anchor_label'] );
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
	 * Sanitization rejects invalid deadline values.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_invalid_deadline() {
		$sanitized = Settings::sanitize( array( 'deadline' => '25:99' ) );

		$this->assertSame( '08:00', $sanitized['deadline'] );
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
}
