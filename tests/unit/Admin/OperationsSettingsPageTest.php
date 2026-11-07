<?php
/**
 * Operations settings page tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\OperationsSettingsPage;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\OperationsSettingsPage
 */
class OperationsSettingsPageTest extends TestCase {

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
		OperationsSettingsPage::section_intro();
		$intro = (string) ob_get_clean();

		$this->assertNotEmpty( $intro );
		$this->assertStringContainsString( 'daily price lists are generated', $intro );
	}

	/**
	 * Register settings defines expected sections and fields.
	 *
	 * @return void
	 */
	public function test_register_settings() {
		OperationsSettingsPage::register_settings();

		$page = OperationsSettingsPage::PAGE;
		$this->assertArrayHasKey( $page, $GLOBALS['twbce_test_settings_sections'] );
		$this->assertArrayHasKey( 'twbce_cijena_operations', $GLOBALS['twbce_test_settings_sections'][ $page ] );

		$this->assertArrayHasKey( $page, $GLOBALS['twbce_test_settings_fields'] );
		$fields = $GLOBALS['twbce_test_settings_fields'][ $page ];
		$ids    = array_column( $fields, 'id' );

		$this->assertContains( 'twbce_cijena_public_archive_enabled', $ids );
		$this->assertContains( 'twbce_cijena_archive_footer_link', $ids );
		$this->assertContains( 'twbce_cijena_archive_sitemap_link', $ids );
		$this->assertContains( 'twbce_cijena_deadline', $ids );
		$this->assertContains( 'twbce_cijena_retention_days', $ids );
		$this->assertContains( 'twbce_cijena_log_retention_days', $ids );
		$this->assertContains( 'twbce_cijena_formats', $ids );
		$this->assertContains( 'twbce_cijena_allow_generation_with_errors', $ids );
		$this->assertContains( 'twbce_cijena_delete_data_on_uninstall', $ids );
	}

	/**
	 * Render field outputs appropriate HTML for time, number, formats, and checkbox fields.
	 *
	 * @return void
	 */
	public function test_render_field_types() {
		// Time field.
		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'deadline',
				'type'  => 'time',
				'label' => 'Daily publication deadline',
			)
		);
		$time_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'sidrena-time-input', $time_html );
		$this->assertStringContainsString( '08:00', $time_html );

		// Number fields (retention_days and log_retention_days).
		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'retention_days',
				'type'  => 'number',
				'label' => 'Price-list retention',
			)
		);
		$retention_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="number"', $retention_html );
		$this->assertStringContainsString( 'min="30"', $retention_html );

		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'log_retention_days',
				'type'  => 'number',
				'label' => 'Log retention',
			)
		);
		$log_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="number"', $log_html );
		$this->assertStringContainsString( 'min="1"', $log_html );

		// Formats checkboxes.
		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'formats',
				'type'  => 'formats',
				'label' => 'Export formats',
			)
		);
		$formats_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'value="csv"', $formats_html );
		$this->assertStringContainsString( 'value="xml"', $formats_html );

		// Checkboxes with special labels & descriptions.
		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'allow_generation_with_errors',
				'type'  => 'checkbox',
				'label' => 'Mandatory price-list generation',
			)
		);
		$gen_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $gen_html );
		$this->assertStringContainsString( 'Mandatory under Croatian law', $gen_html );

		ob_start();
		OperationsSettingsPage::render_field(
			array(
				'key'   => 'delete_data_on_uninstall',
				'type'  => 'checkbox',
				'label' => 'Delete data on uninstall',
			)
		);
		$uninstall_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $uninstall_html );
		$this->assertStringContainsString( 'Danger: with this enabled', $uninstall_html );
	}

	/**
	 * Render fields delegates to do_settings_sections without error.
	 *
	 * @return void
	 */
	public function test_render_fields() {
		ob_start();
		OperationsSettingsPage::render_fields();
		$out = (string) ob_get_clean();

		$this->assertNotNull( $out );
	}
}
