<?php
/**
 * Compliance check screen tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\CompliancePage;
use SidrenaCijena\Compliance\Finding;

/**
 * @covers \SidrenaCijena\Admin\CompliancePage
 */
class CompliancePageTest extends TestCase {

	/**
	 * Reset global state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET                               = array();
		$GLOBALS['sidrena_test_transients'] = array();
		$GLOBALS['sidrena_test_options']    = array();
	}

	/**
	 * Empty state includes busy form markup.
	 *
	 * @return void
	 */
	public function test_render_empty_state_includes_busy_form(): void {
		ob_start();
		CompliancePage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'sidrena-busy-form', $html );
		$this->assertStringContainsString( 'sidrena-btn-busy', $html );
		$this->assertStringContainsString( 'No report has been generated yet', $html );
	}

	/**
	 * Stored report formats generated_at datetime and renders findings.
	 *
	 * @return void
	 */
	public function test_render_report_formats_datetime_and_displays_findings(): void {
		$user_id = get_current_user_id();
		set_transient(
			'sidrena_cijena_report_' . $user_id,
			array(
				'generated_at' => '2026-09-28T10:30:00+02:00',
				'scanned'      => 10,
				'total'        => 10,
				'counts'       => array(
					Finding::ERROR   => 1,
					Finding::WARNING => 1,
					Finding::INFO    => 0,
				),
				'findings'     => array(
					array(
						'severity'       => Finding::ERROR,
						'code'           => 'missing_anchor_price',
						'entity_type'    => 'item',
						'entity_id'      => 42,
						'message'        => 'Item 42 lacks an anchor price.',
						'rule_reference' => 'FR-007',
					),
					array(
						'severity'       => Finding::WARNING,
						'code'           => 'missing_location_address',
						'entity_type'    => 'location',
						'entity_id'      => 1,
						'message'        => 'Location 1 lacks an address.',
						'rule_reference' => 'FR-090',
					),
				),
			)
		);

		ob_start();
		CompliancePage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '28.09.2026. 10:30', $html );
		$this->assertStringContainsString( 'sidrena-busy-form', $html );
		$this->assertStringContainsString( 'sidrena-statutory-guide', $html );
		$this->assertStringContainsString( '17 November 2026', $html );
		$this->assertStringContainsString( 'Triple Price', $html );
		$this->assertStringContainsString( 'missing_anchor_price', $html );
		$this->assertStringContainsString( 'missing_location_address', $html );
		$this->assertStringContainsString( 'Error', $html );
		$this->assertStringContainsString( 'Warning', $html );
	}

	/**
	 * Severity filter restricts displayed findings.
	 *
	 * @return void
	 */
	public function test_render_filters_by_severity(): void {
		$user_id = get_current_user_id();
		set_transient(
			'sidrena_cijena_report_' . $user_id,
			array(
				'generated_at' => '2026-09-28T10:30:00+02:00',
				'scanned'      => 5,
				'total'        => 5,
				'counts'       => array(
					Finding::ERROR   => 1,
					Finding::WARNING => 1,
					Finding::INFO    => 0,
				),
				'findings'     => array(
					array(
						'severity'       => Finding::ERROR,
						'code'           => 'missing_anchor_price',
						'entity_type'    => 'item',
						'entity_id'      => 42,
						'message'        => 'Item 42 lacks an anchor price.',
						'rule_reference' => 'FR-007',
					),
					array(
						'severity'       => Finding::WARNING,
						'code'           => 'missing_location_address',
						'entity_type'    => 'location',
						'entity_id'      => 1,
						'message'        => 'Location 1 lacks an address.',
						'rule_reference' => 'FR-090',
					),
				),
			)
		);

		$_GET['severity'] = 'error';

		ob_start();
		CompliancePage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'missing_anchor_price', $html );
		$this->assertStringNotContainsString( 'missing_location_address', $html );
	}
}
