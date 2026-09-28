<?php
/**
 * Audit trail screen unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\AuditPage;
use SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \SidrenaCijena\Admin\AuditPage
 */
class AuditPageTest extends TestCase {

	/**
	 * Reset global state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET                                    = array();
		$GLOBALS['sidrena_test_options']         = array();
		$GLOBALS['sidrena_test_transients']      = array();
		$GLOBALS['sidrena_test_db_results']      = array();
		$GLOBALS['sidrena_test_db_var']          = 0;
		$GLOBALS['sidrena_test_users']           = array();
		$GLOBALS['sidrena_test_current_user_id'] = 1;
	}

	/**
	 * Tear down globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['sidrena_test_db_results'],
			$GLOBALS['sidrena_test_db_var'],
			$GLOBALS['sidrena_test_users'],
			$GLOBALS['sidrena_test_current_user_id']
		);
		parent::tearDown();
	}

	/**
	 * Render displays empty state messages when no rule changes or logs exist.
	 *
	 * @return void
	 */
	public function test_render_empty_states(): void {
		ob_start();
		AuditPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Sidrena cijena: Audit trail', $html );
		$this->assertStringContainsString( 'No legal-rule changes have been recorded.', $html );
		$this->assertStringContainsString( 'No log entries match the filter.', $html );
	}

	/**
	 * Render displays recorded legal rule changes with formatted values and user labels.
	 *
	 * @return void
	 */
	public function test_render_rule_changes(): void {
		$GLOBALS['sidrena_test_users'][2] = array(
			'ID'           => 2,
			'user_login'   => 'marko_compliance',
			'display_name' => 'Marko Compliance',
		);

		update_option(
			RuleConfig::HISTORY_OPTION,
			array(
				array(
					'timestamp' => '2026-09-28 10:00:00',
					'key'       => 'legacy_enabled',
					'old'       => false,
					'new'       => true,
					'user_id'   => 2,
					'reason'    => 'Legal mandate update for legacy categories',
				),
				array(
					'timestamp' => '2026-09-28 11:30:00',
					'key'       => 'legacy_category_slugs',
					'old'       => array( 'cat-a' ),
					'new'       => array( 'cat-a', 'cat-b' ),
					'user_id'   => 0,
					'reason'    => 'Automated sync',
				),
			)
		);

		ob_start();
		AuditPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Legal rule changes', $html );
		$this->assertStringContainsString( 'Marko Compliance', $html );
		$this->assertStringContainsString( 'Legal mandate update for legacy categories', $html );
		$this->assertStringContainsString( 'system', $html );
		$this->assertStringContainsString( 'cat-a, cat-b', $html );
	}

	/**
	 * Render displays log entries using row user_id and context fallback.
	 *
	 * @return void
	 */
	public function test_render_logs_with_user_and_context(): void {
		$GLOBALS['sidrena_test_users'][5] = array(
			'ID'           => 5,
			'user_login'   => 'iva_admin',
			'display_name' => 'Iva Horvat',
		);

		$GLOBALS['sidrena_test_db_var']     = 2;
		$GLOBALS['sidrena_test_db_results'] = array(
			array(
				'id'         => 1,
				'level'      => 'info',
				'event'      => 'export_manual',
				'message'    => 'Manual export triggered.',
				'context'    => '',
				'user_id'    => 5,
				'created_at' => '2026-09-28 12:00:00',
			),
			array(
				'id'         => 2,
				'level'      => 'warning',
				'event'      => 'validation_failure',
				'message'    => 'Product missing anchor price.',
				'context'    => json_encode( array( 'user_id' => 5 ) ),
				'user_id'    => null,
				'created_at' => '2026-09-28 12:05:00',
			),
		);

		ob_start();
		AuditPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Operational log', $html );
		$this->assertStringContainsString( 'Manual export triggered.', $html );
		$this->assertStringContainsString( 'Product missing anchor price.', $html );
		$this->assertStringContainsString( 'Iva Horvat', $html );
		$this->assertStringContainsString( 'sidrena-cijena-status--info', $html );
		$this->assertStringContainsString( 'sidrena-cijena-status--warning', $html );
	}
}
