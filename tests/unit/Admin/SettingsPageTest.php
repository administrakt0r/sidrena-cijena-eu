<?php
/**
 * Settings page registration tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Admin\SettingsPage;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Support\Settings;

/**
 * @covers \SidrenaCijena\Admin\SettingsPage::register_settings
 * @covers \SidrenaCijena\Admin\SettingsPage::tabs
 * @covers \SidrenaCijena\Admin\SettingsPage::current_tab
 */
class SettingsPageTest extends TestCase {

	/**
	 * Reset test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_registered_settings'] = array();
		$GLOBALS['sidrena_test_settings_sections']   = array();
		$GLOBALS['sidrena_test_settings_fields']     = array();
	}

	/**
	 * Settings registration binds capability to manage_sidrena_cijena.
	 *
	 * @return void
	 */
	public function test_register_settings_enforces_capability() {
		SettingsPage::register_settings();

		$this->assertArrayHasKey( SettingsPage::GROUP_SETTINGS, $GLOBALS['sidrena_test_registered_settings'] );
		$this->assertArrayHasKey( Settings::OPTION, $GLOBALS['sidrena_test_registered_settings'][ SettingsPage::GROUP_SETTINGS ] );
		$settings_args = $GLOBALS['sidrena_test_registered_settings'][ SettingsPage::GROUP_SETTINGS ][ Settings::OPTION ];
		$this->assertSame( Capabilities::capability(), $settings_args['capability'] );

		$this->assertArrayHasKey( SettingsPage::GROUP_RULES, $GLOBALS['sidrena_test_registered_settings'] );
		$this->assertArrayHasKey( RuleConfig::OPTION, $GLOBALS['sidrena_test_registered_settings'][ SettingsPage::GROUP_RULES ] );
		$rules_args = $GLOBALS['sidrena_test_registered_settings'][ SettingsPage::GROUP_RULES ][ RuleConfig::OPTION ];
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
}
