<?php
/**
 * Settings page registration tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\Capabilities;
use Titanworks\SidrenaCijena\Admin\SettingsPage;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Support\Settings;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\SettingsPage::register_settings
 * @covers \Titanworks\SidrenaCijena\Admin\SettingsPage::tabs
 * @covers \Titanworks\SidrenaCijena\Admin\SettingsPage::current_tab
 */
class SettingsPageTest extends TestCase {

	/**
	 * Reset test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_registered_settings'] = array();
		$GLOBALS['twbce_test_settings_sections']   = array();
		$GLOBALS['twbce_test_settings_fields']     = array();
	}

	/**
	 * Settings registration binds capability to twbce_manage_prices.
	 *
	 * @return void
	 */
	public function test_register_settings_enforces_capability() {
		SettingsPage::register_settings();

		$this->assertArrayHasKey( SettingsPage::GROUP_SETTINGS, $GLOBALS['twbce_test_registered_settings'] );
		$this->assertArrayHasKey( Settings::OPTION, $GLOBALS['twbce_test_registered_settings'][ SettingsPage::GROUP_SETTINGS ] );
		$settings_args = $GLOBALS['twbce_test_registered_settings'][ SettingsPage::GROUP_SETTINGS ][ Settings::OPTION ];
		$this->assertSame( Capabilities::capability(), $settings_args['capability'] );

		$this->assertArrayHasKey( SettingsPage::GROUP_RULES, $GLOBALS['twbce_test_registered_settings'] );
		$this->assertArrayHasKey( RuleConfig::OPTION, $GLOBALS['twbce_test_registered_settings'][ SettingsPage::GROUP_RULES ] );
		$rules_args = $GLOBALS['twbce_test_registered_settings'][ SettingsPage::GROUP_RULES ][ RuleConfig::OPTION ];
		$this->assertSame( Capabilities::capability(), $rules_args['capability'] );
	}

	/**
	 * Default tab is display.
	 *
	 * @return void
	 */
	public function test_current_tab_defaults_to_display() {
		$_GET = array();
		$this->assertSame( 'display', SettingsPage::current_tab() );

		$_GET['tab'] = 'legal';
		$this->assertSame( 'legal', SettingsPage::current_tab() );

		$_GET['tab'] = 'non_existent_tab';
		$this->assertSame( 'display', SettingsPage::current_tab() );
	}

	/**
	 * Settings page form markup includes busy state class on form.
	 *
	 * @return void
	 */
	public function test_render_includes_busy_state_affordance() {
		$_GET = array();
		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'class="sidrena-busy-form"', $html );
		$this->assertStringContainsString( 'type="submit"', $html );
	}
}
