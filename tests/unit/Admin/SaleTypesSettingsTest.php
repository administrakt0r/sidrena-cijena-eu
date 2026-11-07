<?php
/**
 * Sale types settings tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\SaleTypesSettings;
use Titanworks\SidrenaCijena\Support\Settings;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\SaleTypesSettings
 */
class SaleTypesSettingsTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['twbce_test_settings_sections'] = array();
		$GLOBALS['twbce_test_settings_fields']   = array();
		$GLOBALS['twbce_test_options']           = array();
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['twbce_test_settings_sections'],
			$GLOBALS['twbce_test_settings_fields'],
			$GLOBALS['twbce_test_options']
		);
		parent::tearDown();
	}

	/**
	 * Section intro renders descriptive text.
	 *
	 * @return void
	 */
	public function test_section_intro() {
		ob_start();
		SaleTypesSettings::section_intro();
		$intro = (string) ob_get_clean();

		$this->assertNotEmpty( $intro );
		$this->assertStringContainsString( 'special form of sale', $intro );
	}

	/**
	 * Register settings defines section and sale_types field.
	 *
	 * @return void
	 */
	public function test_register_settings() {
		SaleTypesSettings::register_settings();

		$page = SaleTypesSettings::PAGE;
		$this->assertArrayHasKey( $page, $GLOBALS['twbce_test_settings_sections'] );
		$this->assertArrayHasKey( SaleTypesSettings::SECTION, $GLOBALS['twbce_test_settings_sections'][ $page ] );

		$this->assertArrayHasKey( $page, $GLOBALS['twbce_test_settings_fields'] );
		$fields = $GLOBALS['twbce_test_settings_fields'][ $page ];
		$ids    = array_column( $fields, 'id' );

		$this->assertContains( 'twbce_cijena_sale_types', $ids );
	}

	/**
	 * Render field outputs textarea with configured sale types.
	 *
	 * @return void
	 */
	public function test_render_field() {
		ob_start();
		SaleTypesSettings::render_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'name="' . Settings::OPTION . '[sale_types]"', $html );
		$this->assertStringContainsString( 'akcija', $html );
	}

	/**
	 * Render fields delegates to do_settings_sections without error.
	 *
	 * @return void
	 */
	public function test_render_fields() {
		ob_start();
		SaleTypesSettings::render_fields();
		$out = (string) ob_get_clean();

		$this->assertNotNull( $out );
	}
}
