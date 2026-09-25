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
}
