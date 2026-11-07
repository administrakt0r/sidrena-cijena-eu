<?php
/**
 * Style settings page tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\StyleSettingsPage;
use Titanworks\SidrenaCijena\Support\DisplayStyles;
use Titanworks\SidrenaCijena\Support\Settings;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\StyleSettingsPage
 */
class StyleSettingsPageTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['twbce_test_options'] = array();
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['twbce_test_options'] );
		parent::tearDown();
	}

	/**
	 * Render gallery outputs preset radio choices, preview prices and sizes.
	 *
	 * @return void
	 */
	public function test_render_gallery_outputs_presets_and_previews() {
		ob_start();
		StyleSettingsPage::render_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'sidrena-style-gallery', $html );
		foreach ( DisplayStyles::presets() as $style => $label ) {
			$this->assertStringContainsString( 'value="' . $style . '"', $html );
			$this->assertStringContainsString( 'sidrena-cijena--style-' . $style, $html );
		}

		$this->assertStringContainsString( 'id="sidrena-date-mode"', $html );
		$this->assertStringContainsString( 'id="sidrena-tooltip-text"', $html );
		$this->assertStringContainsString( 'id="sidrena-font-weight"', $html );
		$this->assertStringContainsString( 'id="sidrena-display-size"', $html );
	}

	/**
	 * Render gallery respects custom settings values.
	 *
	 * @return void
	 */
	public function test_render_gallery_with_custom_settings() {
		update_option(
			Settings::OPTION,
			array(
				'product_display_style' => 'badge',
				'anchor_date_mode'      => 'inline',
				'anchor_tooltip_text'   => 'Custom tooltip explanation',
				'anchor_font_weight'    => 'bold',
				'display_size'          => 80,
			)
		);

		ob_start();
		StyleSettingsPage::render_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Custom tooltip explanation', $html );
		$this->assertStringContainsString( 'value="80"', $html );
		$this->assertStringContainsString( '>80%</output>', $html );
		$this->assertStringContainsString( "value=\"bold\"  selected='selected'", $html );
	}
}
