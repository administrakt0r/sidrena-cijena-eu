<?php
/**
 * Compliance report value object.
 *
 * @package Titanworks\SidrenaCijena\Compliance
 */

namespace Titanworks\SidrenaCijena\Compliance;

use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates findings and renders them as JSON or CSV.
 */
final class ComplianceReport {

	/**
	 * Findings.
	 *
	 * @var Finding[]
	 */
	protected $findings;

	/**
	 * Report metadata (`generated_at`, `rule_version`, `scanned`, `total`).
	 *
	 * @var array<mixed>
	 */
	protected $meta;

	/**
	 * Constructor.
	 *
	 * @param Finding[]    $findings Findings.
	 * @param array<mixed> $meta     Metadata.
	 */
	public function __construct( array $findings, array $meta = array() ) {
		$this->findings = array_values( $findings );

		$this->meta = wp_parse_args(
			$meta,
			array(
				'generated_at' => Dates::now_site()->format( 'c' ),
				'rule_version' => '',
				'scanned'      => 0,
				'total'        => 0,
			)
		);
	}

	/**
	 * Findings.
	 *
	 * @return Finding[]
	 */
	public function findings() {
		return $this->findings;
	}

	/**
	 * Metadata.
	 *
	 * @return array<mixed>
	 */
	public function meta() {
		return $this->meta;
	}

	/**
	 * Severity counts.
	 *
	 * @return array<string,int>
	 */
	public function counts() {
		return Validator::counts( $this->findings );
	}

	/**
	 * Counts per finding code.
	 *
	 * @return array<string,int>
	 */
	public function counts_by_code() {
		$counts = array();

		foreach ( $this->findings as $finding ) {
			if ( ! isset( $counts[ $finding->code ] ) ) {
				$counts[ $finding->code ] = 0;
			}

			++$counts[ $finding->code ];
		}

		arsort( $counts );

		return $counts;
	}

	/**
	 * Findings grouped per entity.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function group_by_entity() {
		$entities = array();

		foreach ( $this->findings as $finding ) {
			$key = $finding->entity_type . ':' . $finding->entity_id;

			if ( ! isset( $entities[ $key ] ) ) {
				$entities[ $key ] = array(
					'entity_type' => $finding->entity_type,
					'entity_id'   => $finding->entity_id,
					'counts'      => array(
						Finding::ERROR   => 0,
						Finding::WARNING => 0,
						Finding::INFO    => 0,
					),
					'findings'    => array(),
				);
			}

			if ( isset( $entities[ $key ]['counts'][ $finding->severity ] ) ) {
				++$entities[ $key ]['counts'][ $finding->severity ];
			}

			$entities[ $key ]['findings'][] = $finding->to_array();
		}

		return $entities;
	}

	/**
	 * Array representation.
	 *
	 * @return array<mixed>
	 */
	public function to_array() {
		return array(
			'generated_at'   => $this->meta['generated_at'],
			'rule_version'   => $this->meta['rule_version'],
			'scanned'        => (int) $this->meta['scanned'],
			'total'          => (int) $this->meta['total'],
			'counts'         => $this->counts(),
			'counts_by_code' => $this->counts_by_code(),
			'findings'       => array_map(
				function ( Finding $finding ) {
					return $finding->to_array();
				},
				$this->findings
			),
		);
	}

	/**
	 * JSON representation.
	 *
	 * @return string
	 */
	public function to_json() {
		return (string) wp_json_encode( $this->to_array() );
	}

	/**
	 * CSV representation with a stable header row.
	 *
	 * @return string
	 */
	public function to_csv() {
		$lines = array( 'severity;code;entity_type;entity_id;message;rule_reference' );

		foreach ( $this->findings as $finding ) {
			$lines[] = implode(
				';',
				array(
					self::csv_field( $finding->severity ),
					self::csv_field( $finding->code ),
					self::csv_field( $finding->entity_type ),
					self::csv_field( (string) $finding->entity_id ),
					self::csv_field( $finding->message ),
					self::csv_field( $finding->rule_reference ),
				)
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Build a report from a validator result.
	 *
	 * @param array<mixed> $result Validator result (`findings`, `counts`).
	 * @param array<mixed> $meta   Metadata.
	 * @return ComplianceReport
	 */
	public static function from_result( array $result, array $meta = array() ) {
		$findings = isset( $result['findings'] ) && is_array( $result['findings'] ) ? $result['findings'] : array();

		$meta = array_merge(
			array(
				'rule_version' => RuleConfig::get( 'rule_version' ),
				'scanned'      => isset( $result['scanned'] ) ? (int) $result['scanned'] : count( $findings ),
				'total'        => isset( $result['total'] ) ? (int) $result['total'] : count( $findings ),
			),
			$meta
		);

		return new self( $findings, $meta );
	}

	/**
	 * Quote a CSV field when required.
	 *
	 * @param string $value Field value.
	 * @return string
	 */
	protected static function csv_field( $value ) {
		$value = (string) $value;

		if ( false === strpos( $value, ';' ) && false === strpos( $value, '"' ) && false === strpos( $value, "\n" ) && false === strpos( $value, "\r" ) ) {
			return $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
