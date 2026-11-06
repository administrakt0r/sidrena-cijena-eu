<?php
/**
 * Sale types settings tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\SaleTypesSettings;
use SidrenaCijena\Support\Settings;

/**
 * @covers \SidrenaCijena\Admin\SaleTypesSettings
 */
class SaleTypesSettingsTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sidrena_test_settings_sections'] = array();
		$GLOBALS['sidrena_test_settings_fields']   = array();
		$GLOBALS['sidrena_test_options']           = array();
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['sidrena_test_settings_sections'],
			$GLOBALS['sidrena_test_settings_fields'],
			$GLOBALS['sidrena_test_options']
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
		$this->assertArrayHasKey( $page, $GLOBALS['sidrena_test_settings_sections'] );
		$this->assertArrayHasKey( SaleTypesSettings::SECTION, $GLOBALS['sidrena_test_settings_sections'][ $page ] );

		$this->assertArrayHasKey( $page, $GLOBALS['sidrena_test_settings_fields'] );
		$fields = $GLOBALS['sidrena_test_settings_fields'][ $page ];
		$ids    = array_column( $fields, 'id' );

		$this->assertContains( 'sidrena_cijena_sale_types', $ids );
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
