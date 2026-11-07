<?php
/**
 * Display settings page tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\DisplaySettingsPage;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\DisplaySettingsPage
 */
class DisplaySettingsPageTest extends TestCase {

	/**
	 * Reset test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_settings_sections'] = array();
		$GLOBALS['twbce_test_settings_fields']   = array();
	}

	/**
	 * Register settings registers expected section and fields with label_for.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_expected_fields_with_label_for() {
		DisplaySettingsPage::register_settings();

		$this->assertArrayHasKey( DisplaySettingsPage::PAGE, $GLOBALS['twbce_test_settings_sections'] );
		$this->assertArrayHasKey( DisplaySettingsPage::PAGE, $GLOBALS['twbce_test_settings_fields'] );

		$fields             = $GLOBALS['twbce_test_settings_fields'][ DisplaySettingsPage::PAGE ];
		$anchor_label_field = null;

		foreach ( $fields as $field ) {
			if ( 'twbce_cijena_anchor_label' === $field['id'] ) {
				$anchor_label_field = $field;
				break;
			}
		}

		$this->assertNotNull( $anchor_label_field, 'Anchor label field must be registered.' );
		$this->assertSame( 'anchor_label', $anchor_label_field['args']['label_for'] );
		$this->assertNotEmpty( $anchor_label_field['args']['description'] );
	}

	/**
	 * Render anchor label field produces clean HTML without inline scripts or inline styles.
	 *
	 * @return void
	 */
	public function test_render_anchor_label_field_outputs_clean_accessible_markup() {
		ob_start();
		DisplaySettingsPage::render_anchor_label_field( 'anchor_label', 'Sidrena cijena' );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<script', $html, 'Inline <script> must be centralized in admin.js.' );
		$this->assertStringNotContainsString( 'style="', $html, 'Inline styles must be centralized in admin.css.' );
		$this->assertStringContainsString( 'id="anchor_label"', $html, 'Input id must match label_for attribute.' );
		$this->assertStringContainsString( 'class="button sidrena-preset-btn is-active"', $html, 'Active preset must have is-active class.' );
		$this->assertStringContainsString( 'data-token="{datum}"', $html, 'Token button must provide {datum}.' );
		$this->assertStringContainsString( 'id="sidrena-preview-food"', $html, 'Food preview span must be present.' );
		$this->assertStringContainsString( 'id="sidrena-preview-general"', $html, 'General preview span must be present.' );
	}

	/**
	 * Render anchor label field matches custom preset when active.
	 *
	 * @return void
	 */
	public function test_render_anchor_label_field_matches_active_preset() {
		ob_start();
		DisplaySettingsPage::render_anchor_label_field( 'anchor_label', 'Cijena na dan {datum}' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="Cijena na dan {datum}"', $html );
		$this->assertStringContainsString( 'data-preset="Cijena na dan {datum}"', $html );
		$this->assertStringContainsString( 'class="button sidrena-preset-btn is-active" data-preset="Cijena na dan {datum}"', $html );
	}

	/**
	 * Section intro renders description text without error.
	 *
	 * @return void
	 */
	public function test_section_intro_and_render_fields() {
		ob_start();
		DisplaySettingsPage::section_intro();
		$intro = ob_get_clean();

		$this->assertNotEmpty( $intro );

		ob_start();
		DisplaySettingsPage::render_fields();
		$rendered = ob_get_clean();

		$this->assertIsString( $rendered );
	}
}
