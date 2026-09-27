<?php
/**
 * Welcome banner and setup guide tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\SetupGuidePage;
use SidrenaCijena\Admin\WelcomeBanner;

/**
 * @covers \SidrenaCijena\Admin\WelcomeBanner
 * @covers \SidrenaCijena\Admin\SetupGuidePage
 */
class WelcomeTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/wc-stubs.php';

		$GLOBALS['sidrena_test_options'] = array();
	}

	/**
	 * Banner promotes the author projects.
	 *
	 * @return void
	 */
	public function test_links_advertise_free_projects() {
		$urls = array_column( WelcomeBanner::links(), 'url' );

		$this->assertContains( 'https://wpineu.com', $urls );
		$this->assertContains( 'https://llm.kiwi', $urls );
	}

	/**
	 * Setup guide exposes five ordered steps.
	 *
	 * @return void
	 */
	public function test_setup_steps_cover_locations_rules_anchors_lists_check() {
		$keys = array_column( SetupGuidePage::steps(), 'key' );

		$this->assertSame( array( 'locations', 'rules', 'anchors', 'lists', 'check' ), $keys );
	}

	/**
	 * Banner shows until dismissed.
	 *
	 * @return void
	 */
	public function test_banner_visible_until_dismissed() {
		$this->assertTrue( WelcomeBanner::should_show() );

		update_option( WelcomeBanner::DISMISSED_OPTION, 1, false );

		$this->assertFalse( WelcomeBanner::should_show() );
	}

	/**
	 * Clean up globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();

		unset(
			$GLOBALS['sidrena_test_can'],
			$GLOBALS['sidrena_test_doing_ajax'],
			$GLOBALS['sidrena_test_doing_cron'],
			$GLOBALS['sidrena_test_is_network_admin']
		);
		unset( $_GET['activate-multi'] );
	}

	/**
	 * Bulk activation suppresses redirect and clears the redirect flag.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_handles_bulk_activation() {
		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );
		$_GET['activate-multi'] = '1';

		WelcomeBanner::maybe_redirect();

		$this->assertFalse( get_option( WelcomeBanner::REDIRECT_OPTION, false ) );
	}

	/**
	 * Missing capability suppresses redirect and preserves the flag.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_guards_against_missing_capability() {
		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );
		$GLOBALS['sidrena_test_can'] = false;

		WelcomeBanner::maybe_redirect();

		$this->assertSame( 1, get_option( WelcomeBanner::REDIRECT_OPTION ) );
	}

	/**
	 * Cron execution suppresses redirect and preserves the flag.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_guards_against_cron() {
		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );
		$GLOBALS['sidrena_test_doing_cron'] = true;

		WelcomeBanner::maybe_redirect();

		$this->assertSame( 1, get_option( WelcomeBanner::REDIRECT_OPTION ) );
	}

	/**
	 * AJAX requests suppress redirect and preserve the flag.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_guards_against_ajax() {
		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );
		$GLOBALS['sidrena_test_doing_ajax'] = true;

		WelcomeBanner::maybe_redirect();

		$this->assertSame( 1, get_option( WelcomeBanner::REDIRECT_OPTION ) );
	}

	/**
	 * Network admin suppresses redirect and preserves the flag.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_guards_against_network_admin() {
		update_option( WelcomeBanner::REDIRECT_OPTION, 1, false );
		$GLOBALS['sidrena_test_is_network_admin'] = true;

		WelcomeBanner::maybe_redirect();

		$this->assertSame( 1, get_option( WelcomeBanner::REDIRECT_OPTION ) );
	}

	/**
	 * Setup guide renders steps and author projects.
	 *
	 * @return void
	 */
	public function test_setup_guide_renders_checklist() {
		ob_start();
		SetupGuidePage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '1. Add sales locations', $html );
		$this->assertStringContainsString( '2. Check legal rules', $html );
		$this->assertStringContainsString( '3. Record anchor prices', $html );
		$this->assertStringContainsString( '4. Generate the price list', $html );
		$this->assertStringContainsString( '5. Run the compliance check', $html );
		$this->assertStringContainsString( 'WPinEU.com', $html );
		$this->assertStringContainsString( 'LLM.kiwi', $html );
	}
}
