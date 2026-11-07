<?php
/**
 * Log viewer screen unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\LogsPage;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\LogsPage
 */
class LogsPageTest extends TestCase {

	/**
	 * Reset global state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET                                  = array();
		$GLOBALS['twbce_test_options']         = array();
		$GLOBALS['twbce_test_transients']      = array();
		$GLOBALS['twbce_test_db_results']      = array();
		$GLOBALS['twbce_test_db_var']          = 0;
		$GLOBALS['twbce_test_users']           = array();
		$GLOBALS['twbce_test_current_user_id'] = 1;
	}

	/**
	 * Tear down globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['twbce_test_db_results'],
			$GLOBALS['twbce_test_db_var'],
			$GLOBALS['twbce_test_users'],
			$GLOBALS['twbce_test_current_user_id']
		);
		parent::tearDown();
	}

	/**
	 * Render displays filter dropdowns and empty state when no logs match.
	 *
	 * @return void
	 */
	public function test_render_empty_state_and_filter_form(): void {
		ob_start();
		LogsPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Sidrena cijena: Logs', $html );
		$this->assertStringContainsString( '<select name="level">', $html );
		$this->assertStringContainsString( '<select name="event">', $html );
		$this->assertStringContainsString( 'No log entries match these filters.', $html );
	}

	/**
	 * Render displays log rows with proper levels, messages, and user labels.
	 *
	 * @return void
	 */
	public function test_render_log_rows_with_user_labels(): void {
		$GLOBALS['twbce_test_users'][7] = array(
			'ID'           => 7,
			'user_login'   => 'ana_manager',
			'display_name' => 'Ana Anić',
		);

		$GLOBALS['twbce_test_db_var']     = 2;
		$GLOBALS['twbce_test_db_results'] = array(
			array(
				'id'         => 101,
				'level'      => 'info',
				'event'      => 'settings_change',
				'message'    => 'Display settings saved.',
				'context'    => '',
				'user_id'    => 7,
				'created_at' => '2026-09-28 14:00:00',
			),
			array(
				'id'         => 102,
				'level'      => 'error',
				'event'      => 'export_failed',
				'message'    => 'Price list generation failed.',
				'context'    => '',
				'user_id'    => null,
				'created_at' => '2026-09-28 14:15:00',
			),
		);

		ob_start();
		LogsPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Display settings saved.', $html );
		$this->assertStringContainsString( 'Price list generation failed.', $html );
		$this->assertStringContainsString( 'Ana Anić', $html );
		$this->assertStringContainsString( 'system', $html );
		$this->assertStringContainsString( 'sidrena-cijena-status--info', $html );
		$this->assertStringContainsString( 'sidrena-cijena-status--error', $html );
	}

	/**
	 * Active GET filters are reflected in selected option attributes.
	 *
	 * @return void
	 */
	public function test_render_with_active_filters(): void {
		$_GET['level'] = 'error';
		$_GET['event'] = 'export_failed';

		ob_start();
		LogsPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="error" selected=\'selected\'', $html );
		$this->assertStringContainsString( 'value="export_failed" selected=\'selected\'', $html );
	}
}
