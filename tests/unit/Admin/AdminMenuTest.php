<?php
/**
 * Admin menu unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\AdminMenu;
use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Admin\CompliancePage;
use SidrenaCijena\Admin\DashboardPage;
use SidrenaCijena\Admin\DocumentationPage;
use SidrenaCijena\Admin\SettingsPage;
use SidrenaCijena\Admin\SetupGuidePage;

/**
 * @covers \SidrenaCijena\Admin\AdminMenu
 */
class AdminMenuTest extends TestCase {

	/**
	 * Reset global state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sidrena_test_menu_pages']       = array();
		$GLOBALS['sidrena_test_submenu_pages']    = array();
		$GLOBALS['sidrena_test_enqueued_styles']  = array();
		$GLOBALS['sidrena_test_enqueued_scripts'] = array();
		$GLOBALS['current_screen']                = null;
		$_GET                                     = array();
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['sidrena_test_menu_pages']       = array();
		$GLOBALS['sidrena_test_submenu_pages']    = array();
		$GLOBALS['sidrena_test_enqueued_styles']  = array();
		$GLOBALS['sidrena_test_enqueued_scripts'] = array();
		$GLOBALS['current_screen']                = null;
		$_GET                                     = array();
		parent::tearDown();
	}

	/**
	 * Test pages returns valid tuples of class-string and label.
	 *
	 * @return void
	 */
	public function test_pages_returns_all_expected_pages() {
		$pages = AdminMenu::pages();

		$this->assertIsArray( $pages );
		$this->assertArrayHasKey( AdminMenu::PARENT, $pages );
		$this->assertSame( DashboardPage::class, $pages[ AdminMenu::PARENT ][0] );

		$this->assertArrayHasKey( SetupGuidePage::SLUG, $pages );
		$this->assertSame( SetupGuidePage::class, $pages[ SetupGuidePage::SLUG ][0] );

		$this->assertArrayHasKey( 'sidrena-cijena-compliance', $pages );
		$this->assertSame( CompliancePage::class, $pages['sidrena-cijena-compliance'][0] );

		$this->assertArrayHasKey( 'sidrena-cijena-settings', $pages );
		$this->assertSame( SettingsPage::class, $pages['sidrena-cijena-settings'][0] );

		$this->assertArrayHasKey( DocumentationPage::SLUG, $pages );
		$this->assertSame( DocumentationPage::class, $pages[ DocumentationPage::SLUG ][0] );
	}

	/**
	 * Test register adds main menu and submenu pages.
	 *
	 * @return void
	 */
	public function test_register_adds_menu_and_submenus() {
		AdminMenu::register();

		$this->assertArrayHasKey( AdminMenu::PARENT, $GLOBALS['sidrena_test_menu_pages'] );
		$main = $GLOBALS['sidrena_test_menu_pages'][ AdminMenu::PARENT ];
		$this->assertSame( Capabilities::capability(), $main['capability'] );
		$this->assertSame( 56, $main['position'] );

		$this->assertNotEmpty( $GLOBALS['sidrena_test_submenu_pages'] );
		$sub_slugs = array_column( $GLOBALS['sidrena_test_submenu_pages'], 'menu_slug' );
		$this->assertContains( AdminMenu::PARENT, $sub_slugs );
		$this->assertContains( SetupGuidePage::SLUG, $sub_slugs );
		$this->assertContains( 'sidrena-cijena-compliance', $sub_slugs );
		$this->assertContains( 'sidrena-cijena-settings', $sub_slugs );
		$this->assertContains( DocumentationPage::SLUG, $sub_slugs );
	}

	/**
	 * Test url generation with and without query arguments.
	 *
	 * @return void
	 */
	public function test_url_builds_admin_url_with_parameters() {
		$url = AdminMenu::url( 'sidrena-cijena-settings' );
		$this->assertStringContainsString( 'page=sidrena-cijena-settings', $url );

		$url_with_args = AdminMenu::url(
			'sidrena-cijena-settings',
			array(
				'tab' => 'legal',
				'foo' => 'bar',
			)
		);
		$this->assertStringContainsString( 'page=sidrena-cijena-settings', $url_with_args );
		$this->assertStringContainsString( 'tab=legal', $url_with_args );
		$this->assertStringContainsString( 'foo=bar', $url_with_args );
	}

	/**
	 * Test is_plugin_screen with query parameters and screens.
	 *
	 * @return void
	 */
	public function test_is_plugin_screen_detects_pages_and_screens() {
		$_GET = array();
		$this->assertFalse( AdminMenu::is_plugin_screen() );

		$_GET['page'] = 'sidrena-cijena-compliance';
		$this->assertTrue( AdminMenu::is_plugin_screen() );

		$_GET['page'] = 'sidrena_item_list';
		$this->assertTrue( AdminMenu::is_plugin_screen() );

		$_GET['page'] = 'unrelated-plugin';
		$this->assertFalse( AdminMenu::is_plugin_screen() );

		$_GET                      = array();
		$screen                    = new \stdClass();
		$screen->id                = 'toplevel_page_sidrena-cijena';
		$screen->base              = 'toplevel_page_sidrena-cijena';
		$screen->post_type         = '';
		$GLOBALS['current_screen'] = $screen;

		$this->assertTrue( AdminMenu::is_plugin_screen() );

		// Product edit screen when include_product is false vs true.
		$product_screen            = new \stdClass();
		$product_screen->id        = 'product';
		$product_screen->base      = 'post';
		$product_screen->post_type = 'product';
		$GLOBALS['current_screen'] = $product_screen;

		$this->assertFalse( AdminMenu::is_plugin_screen( false ) );
		$this->assertTrue( AdminMenu::is_plugin_screen( true ) );
	}

	/**
	 * Test enqueue_assets only enqueues scripts on plugin and product screens.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_enqueues_expected_assets() {
		// Non-matching hook.
		AdminMenu::enqueue_assets( 'options-general.php' );
		$this->assertArrayNotHasKey( 'sidrena-cijena-admin', $GLOBALS['sidrena_test_enqueued_styles'] );

		// Plugin screen hook.
		AdminMenu::enqueue_assets( 'toplevel_page_sidrena-cijena' );
		$this->assertArrayHasKey( 'sidrena-cijena-admin', $GLOBALS['sidrena_test_enqueued_styles'] );
		$this->assertArrayHasKey( 'sidrena-cijena-admin', $GLOBALS['sidrena_test_enqueued_scripts'] );
		$this->assertArrayNotHasKey( 'sidrena-cijena-frontend', $GLOBALS['sidrena_test_enqueued_styles'] );

		// Styling tab includes frontend css.
		$_GET['tab'] = 'styling';
		AdminMenu::enqueue_assets( 'toplevel_page_sidrena-cijena' );
		$this->assertArrayHasKey( 'sidrena-cijena-frontend', $GLOBALS['sidrena_test_enqueued_styles'] );
	}

	/**
	 * Test menu_icon returns data URI with SVG icon.
	 *
	 * @return void
	 */
	public function test_menu_icon_returns_valid_data_uri() {
		$icon = AdminMenu::menu_icon();
		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $icon );

		$decoded = base64_decode( substr( $icon, strlen( 'data:image/svg+xml;base64,' ) ) );
		$this->assertStringContainsString( '<svg', $decoded );
		$this->assertStringContainsString( '</svg>', $decoded );
	}
}
