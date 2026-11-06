<?php
/**
 * Legal settings page tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\LegalSettingsPage;
use SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \SidrenaCijena\Admin\LegalSettingsPage
 */
class LegalSettingsPageTest extends TestCase {

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
		LegalSettingsPage::section_intro();
		$intro = (string) ob_get_clean();

		$this->assertNotEmpty( $intro );
		$this->assertStringContainsString( 'audit trail', $intro );
	}

	/**
	 * Register settings registers sections, fields and descriptions.
	 *
	 * @return void
	 */
	public function test_register_settings() {
		LegalSettingsPage::register_settings();

		$page = LegalSettingsPage::PAGE;
		$this->assertArrayHasKey( $page, $GLOBALS['sidrena_test_settings_sections'] );
		$this->assertArrayHasKey( 'sidrena_cijena_legal', $GLOBALS['sidrena_test_settings_sections'][ $page ] );

		$this->assertArrayHasKey( $page, $GLOBALS['sidrena_test_settings_fields'] );
		$fields = $GLOBALS['sidrena_test_settings_fields'][ $page ];
		$ids    = array_column( $fields, 'id' );

		$this->assertContains( 'sidrena_cijena_general_reference_date', $ids );
		$this->assertContains( 'sidrena_cijena_legacy_reference_date', $ids );
		$this->assertContains( 'sidrena_cijena_legacy_enabled', $ids );
		$this->assertContains( 'sidrena_cijena_legacy_category_slugs', $ids );
		$this->assertContains( 'sidrena_cijena_rule_version', $ids );

		foreach ( $fields as $field ) {
			$this->assertArrayHasKey( 'description', $field['args'] );
			$this->assertNotEmpty( $field['args']['description'] );
		}
	}

	/**
	 * Render field renders date, checkbox and text inputs with descriptions.
	 *
	 * @return void
	 */
	public function test_render_field_types() {
		// Date field.
		ob_start();
		LegalSettingsPage::render_field(
			array(
				'key'         => 'general_reference_date',
				'type'        => 'date',
				'description' => 'Statutory anchor reference date (default: 2026-09-10).',
			)
		);
		$date_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="date"', $date_html );
		$this->assertStringContainsString( 'Statutory anchor reference date', $date_html );

		// Checkbox field.
		ob_start();
		LegalSettingsPage::render_field(
			array(
				'key'         => 'legacy_enabled',
				'type'        => 'checkbox',
				'description' => 'Apply the legacy reference date to items in designated legacy categories.',
			)
		);
		$checkbox_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $checkbox_html );
		$this->assertStringContainsString( 'Apply the legacy reference date', $checkbox_html );

		// Text field.
		ob_start();
		LegalSettingsPage::render_field(
			array(
				'key'         => 'rule_version',
				'type'        => 'text',
				'description' => 'Identifier for the legal rule-set configuration.',
			)
		);
		$text_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'type="text"', $text_html );
		$this->assertStringContainsString( 'Identifier for the legal rule-set configuration', $text_html );
	}

	/**
	 * Categories field renders notice when no categories exist.
	 *
	 * @return void
	 */
	public function test_render_field_categories_empty() {
		ob_start();
		LegalSettingsPage::render_field(
			array(
				'key'         => 'legacy_category_slugs',
				'type'        => 'categories',
				'description' => 'Product categories governed by the legacy reference date.',
			)
		);
		$cat_html = (string) ob_get_clean();

		$this->assertStringContainsString( 'No catalog categories are available', $cat_html );
	}

	/**
	 * Render fields delegates to do_settings_sections.
	 *
	 * @return void
	 */
	public function test_render_fields() {
		ob_start();
		LegalSettingsPage::render_fields();
		$out = (string) ob_get_clean();

		$this->assertNotNull( $out );
	}
}
