<?php
/**
 * Shared notice helper tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\Notice;

/**
 * @covers \SidrenaCijena\Admin\Notice
 */
class NoticeTest extends TestCase {

	/**
	 * Reset the request state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$_GET = array();
	}

	/**
	 * Reset the request state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_GET = array();
	}

	/**
	 * Notices are dismissible and can receive focus.
	 *
	 * @return void
	 */
	public function test_render_prints_a_dismissible_notice_with_a_focus_hook() {
		ob_start();
		Notice::render( 'success', 'Saved.' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice notice-success sidrena-cijena-notice is-dismissible', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'data-sidrena-notice-focus="1"', $html );
		$this->assertStringContainsString( '<p>Saved.</p>', $html );
	}

	/**
	 * Unknown types render as error notices.
	 *
	 * @return void
	 */
	public function test_render_defaults_to_error_for_unknown_types() {
		ob_start();
		Notice::render( 'dry-run', 'Check the data.' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice notice-error', $html );
	}

	/**
	 * Empty messages render nothing.
	 *
	 * @return void
	 */
	public function test_render_skips_empty_messages() {
		ob_start();
		Notice::render( 'success', '' );
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}

	/**
	 * Query notices map unknown types to success.
	 *
	 * @return void
	 */
	public function test_from_request_maps_query_types() {
		$cases = array(
			'error'   => 'notice-error',
			'info'    => 'notice-info',
			'warning' => 'notice-warning',
			'success' => 'notice-success',
			'preview' => 'notice-success',
		);

		foreach ( $cases as $type => $class ) {
			$_GET = array(
				'sidrena_cijena_notice'      => $type,
				'sidrena_cijena_notice_text' => 'Notice text.',
			);

			ob_start();
			Notice::from_request();
			$html = (string) ob_get_clean();

			$this->assertStringContainsString( $class, $html, $type );
			$this->assertStringContainsString( 'Notice text.', $html, $type );
			$this->assertStringContainsString( 'is-dismissible', $html, $type );
		}
	}

	/**
	 * Without a query notice nothing is printed.
	 *
	 * @return void
	 */
	public function test_from_request_without_query_prints_nothing() {
		ob_start();
		Notice::from_request();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}
}
