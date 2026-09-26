<?php
/**
 * Unit tests for the compliance report and finding value objects.
 *
 * @package SidrenaCijena\Tests\Unit\Compliance
 */

namespace SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Compliance\ComplianceReport;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \SidrenaCijena\Compliance\ComplianceReport
 * @covers \SidrenaCijena\Compliance\Finding
 */
class ComplianceReportTest extends TestCase {

	/**
	 * Unknown severities fall back to INFO and never crash the report.
	 *
	 * @return void
	 */
	public function test_finding_falls_back_to_info_for_unknown_severity() {
		$finding = new Finding( 'bogus', 'CODE', 'Message' );

		$this->assertSame( Finding::INFO, $finding->severity );
	}

	/**
	 * Findings default to the catalog-item entity scope.
	 *
	 * @return void
	 */
	public function test_finding_defaults() {
		$finding = new Finding( Finding::ERROR, 'CODE', 'Message' );

		$this->assertSame( 'catalog_item', $finding->entity_type );
		$this->assertSame( 0, $finding->entity_id );
		$this->assertSame( '', $finding->rule_reference );
		$this->assertSame( array(), $finding->context );
	}

	/**
	 * Factory helpers set the right severity and round-trip through arrays.
	 *
	 * @return void
	 */
	public function test_finding_factories_and_round_trip() {
		$warning = Finding::warning(
			'CODE',
			'Message',
			array(
				'entity_type'    => 'location',
				'entity_id'      => 3,
				'rule_reference' => 'ref',
			)
		);
		$info    = Finding::info( 'CODE', 'Message' );

		$this->assertSame( Finding::WARNING, $warning->severity );
		$this->assertSame( Finding::INFO, $info->severity );
		$this->assertSame( Finding::ERROR, Finding::error( 'CODE', 'Message' )->severity );

		$this->assertSame(
			array(
				'severity'       => Finding::WARNING,
				'code'           => 'CODE',
				'entity_type'    => 'location',
				'entity_id'      => 3,
				'message'        => 'Message',
				'rule_reference' => 'ref',
				'context'        => array(),
			),
			$warning->to_array()
		);
	}

	/**
	 * Severity counts aggregate the report findings.
	 *
	 * @return void
	 */
	public function test_counts_aggregate_severities() {
		$report = $this->report();

		$this->assertSame(
			array(
				Finding::ERROR   => 1,
				Finding::WARNING => 1,
				Finding::INFO    => 2,
				'total'          => 4,
			),
			$report->counts()
		);
	}

	/**
	 * Code counts lead with the most frequent code.
	 *
	 * @return void
	 */
	public function test_counts_by_code_are_sorted_descending() {
		$counts = $this->report()->counts_by_code();

		$this->assertSame( 'CODE_B', array_keys( $counts )[0] );
		$this->assertSame( array( 2, 1, 1 ), array_values( $counts ) );
		$this->assertSame(
			array(
				'CODE_A' => 1,
				'CODE_C' => 1,
			),
			array_diff_key( $counts, array( 'CODE_B' => 2 ) )
		);
	}

	/**
	 * Findings group per entity with per-severity counts.
	 *
	 * @return void
	 */
	public function test_group_by_entity_groups_and_counts() {
		$report   = $this->report();
		$entities = $report->group_by_entity();

		$this->assertCount( 2, $entities );

		$first = $entities['catalog_item:10'];

		$this->assertSame( 'catalog_item', $first['entity_type'] );
		$this->assertSame( 10, $first['entity_id'] );
		$this->assertSame( 1, $first['counts'][ Finding::ERROR ] );
		$this->assertSame( 1, $first['counts'][ Finding::INFO ] );
		$this->assertCount( 2, $first['findings'] );

		$second = $entities['location:5'];

		$this->assertSame( 1, $second['counts'][ Finding::WARNING ] );
		$this->assertSame( 1, $second['counts'][ Finding::INFO ] );
	}

	/**
	 * The array and JSON representations carry meta and findings.
	 *
	 * @return void
	 */
	public function test_to_array_and_to_json() {
		$report = $this->report();
		$array  = $report->to_array();

		$this->assertSame( '1.0.0', $array['rule_version'] );
		$this->assertSame( 7, $array['scanned'] );
		$this->assertSame( 4, $array['total'] );
		$this->assertCount( 4, $array['findings'] );

		$this->assertSame( $array, json_decode( $report->to_json(), true ) );
	}

	/**
	 * CSV output quotes and escapes fields containing separators.
	 *
	 * @return void
	 */
	public function test_to_csv_quotes_special_fields() {
		$report = new ComplianceReport(
			array(
				new Finding( Finding::ERROR, 'CODE_A', 'Plain message', array( 'entity_id' => 10 ) ),
				new Finding( Finding::INFO, 'CODE_B', "Quote \" and; newline\nend", array( 'entity_id' => 11 ) ),
			)
		);

		$csv = $report->to_csv();
		$this->assertStringStartsWith( 'severity;code;entity_type;entity_id;message;rule_reference', $csv );

		$lines = explode( "\n", $csv );
		$this->assertCount( 5, $lines );
		$this->assertSame( 'ERROR;CODE_A;catalog_item;10;Plain message;', $lines[1] );
		$this->assertSame( 'INFO;CODE_B;catalog_item;11;"Quote "" and; newline', $lines[2] );
		$this->assertSame( 'end";', $lines[3] );
		$this->assertSame( '', $lines[4] );
	}

	/**
	 * Reports built from validator results derive their meta.
	 *
	 * @return void
	 */
	public function test_from_result_derives_meta() {
		$findings = array(
			Finding::error( 'CODE_A', 'Message' ),
			Finding::info( 'CODE_B', 'Message' ),
		);

		$report = ComplianceReport::from_result(
			array(
				'findings' => $findings,
				'counts'   => array(),
			)
		);

		$meta = $report->meta();

		$this->assertSame( RuleConfig::get( 'rule_version' ), $meta['rule_version'] );
		$this->assertSame( 2, $meta['scanned'] );
		$this->assertSame( 2, $meta['total'] );
		$this->assertSame( 2, $report->to_array()['total'] );

		$explicit = ComplianceReport::from_result(
			array( 'findings' => $findings ),
			array(
				'scanned' => 9,
				'total'   => 12,
			)
		);

		$this->assertSame( 9, $explicit->meta()['scanned'] );
		$this->assertSame( 12, $explicit->meta()['total'] );
	}

	/**
	 * Reports work without findings at all.
	 *
	 * @return void
	 */
	public function test_empty_report() {
		$report = ComplianceReport::from_result( array() );

		$this->assertSame( array(), $report->findings() );
		$this->assertSame( array(), $report->counts_by_code() );
		$this->assertSame( 0, $report->counts()['total'] );
		$this->assertStringEndsWith( "\n", $report->to_csv() );
	}

	/**
	 * Build a report with four findings across two entities.
	 *
	 * @return ComplianceReport
	 */
	protected function report() {
		return new ComplianceReport(
			array(
				Finding::error(
					'CODE_A',
					'Message',
					array(
						'entity_type' => 'catalog_item',
						'entity_id'   => 10,
					)
				),
				Finding::info(
					'CODE_B',
					'Message',
					array(
						'entity_type' => 'catalog_item',
						'entity_id'   => 10,
					)
				),
				Finding::warning(
					'CODE_C',
					'Message',
					array(
						'entity_type' => 'location',
						'entity_id'   => 5,
					)
				),
				Finding::info(
					'CODE_B',
					'Message',
					array(
						'entity_type' => 'location',
						'entity_id'   => 5,
					)
				),
			),
			array(
				'rule_version' => '1.0.0',
				'scanned'      => 7,
				'total'        => 4,
			)
		);
	}
}
