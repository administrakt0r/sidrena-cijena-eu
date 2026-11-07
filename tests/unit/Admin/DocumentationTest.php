<?php
/**
 * Documentation page tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\DocumentationPage;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\DocumentationPage
 */
class DocumentationTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/wc-stubs.php';

		$GLOBALS['twbce_test_options'] = array();
	}

	/**
	 * Constant check.
	 *
	 * @return void
	 */
	public function test_slug() {
		$this->assertSame( 'sidrena-cijena-docs', DocumentationPage::SLUG );
	}

	/**
	 * Documentation renders major reference sections in Croatian.
	 *
	 * @return void
	 */
	public function test_render_contains_all_sections() {
		ob_start();
		DocumentationPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'sidrena-docs-wrap', $html );
		$this->assertStringContainsString( '1. Zakonski okvir', $html );
		$this->assertStringContainsString( '2. Skupno postavljanje', $html );
		$this->assertStringContainsString( '3. Prikaz na trgovini', $html );
		$this->assertStringContainsString( '4. Dnevni cjenik', $html );
		$this->assertStringContainsString( '5. Cron poslovi', $html );
		$this->assertStringContainsString( '6. WP-CLI', $html );
		$this->assertStringContainsString( '7. Kuke za programere', $html );
		$this->assertStringContainsString( 'wp sidrena-cijena generate', $html );
		$this->assertStringContainsString( 'twbce_cijena_daily_event', $html );
	}
}
