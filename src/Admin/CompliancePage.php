<?php
/**
 * Compliance check screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\ComplianceReport;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Runs and displays the central validator report.
 */
class CompliancePage {

	/**
	 * Run-check form action.
	 */
	const ACTION_RUN = 'sidrena_cijena_run_compliance';

	/**
	 * Report export action.
	 */
	const ACTION_EXPORT = 'sidrena_cijena_export_report';

	/**
	 * Transient prefix for the stored report.
	 */
	const TRANSIENT_PREFIX = 'sidrena_cijena_report_';

	/**
	 * Maximum items checked in one run.
	 */
	const REPORT_LIMIT = 1000;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		$report = self::stored_report();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Compliance check', 'sidrena-cijena' ) . '</h1>';

		self::notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '" />';
		wp_nonce_field( self::ACTION_RUN );
		submit_button( __( 'Run compliance check', 'sidrena-cijena' ), 'primary', 'submit', false );
		echo '</form>';

		if ( ! $report ) {
			echo '<p>' . esc_html__( 'No report has been generated yet. Run the compliance check to validate the catalog and the published price lists.', 'sidrena-cijena' ) . '</p>';
			echo '</div>';

			return;
		}

		$counts   = isset( $report['counts'] ) ? (array) $report['counts'] : array();
		$findings = isset( $report['findings'] ) ? (array) $report['findings'] : array();

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: generation timestamp, 2: scanned items, 3: total items. */
					__( 'Report generated %1$s from %2$d of %3$d catalog items.', 'sidrena-cijena' ),
					isset( $report['generated_at'] ) ? (string) $report['generated_at'] : '',
					isset( $report['scanned'] ) ? (int) $report['scanned'] : 0,
					isset( $report['total'] ) ? (int) $report['total'] : 0
				)
			)
		);

		echo '<div class="sidrena-cijena-cards">';

		foreach ( array( Finding::ERROR, Finding::WARNING, Finding::INFO ) as $severity ) {
			echo '<div class="sidrena-cijena-card">';
			echo '<span class="sidrena-cijena-card__value">' . esc_html( (string) ( isset( $counts[ $severity ] ) ? $counts[ $severity ] : 0 ) ) . '</span>';
			echo '<span class="sidrena-cijena-card__label">' . esc_html( $severity ) . '</span>';
			echo '</div>';
		}

		echo '</div>';

		$severity = self::current_severity();
		$filtered = self::filter_findings( $findings, $severity );

		echo '<p>';
		foreach ( array(
			''               => __( 'All', 'sidrena-cijena' ),
			Finding::ERROR   => Finding::ERROR,
			Finding::WARNING => Finding::WARNING,
			Finding::INFO    => Finding::INFO,
		) as $value => $label ) {
			$url = AdminMenu::url( 'sidrena-cijena-compliance', '' === $value ? array() : array( 'severity' => $value ) );
			printf(
				'<a class="button %1$s" href="%2$s">%3$s</a> ',
				esc_attr( $severity === (string) $value ? 'button-primary' : '' ),
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</p>';

		echo '<p>';
		printf(
			'<a class="button" href="%1$s">%2$s</a> ',
			esc_url( self::export_url( 'csv' ) ),
			esc_html__( 'Export report (CSV)', 'sidrena-cijena' )
		);
		printf(
			'<a class="button" href="%1$s">%2$s</a>',
			esc_url( self::export_url( 'json' ) ),
			esc_html__( 'Export report (JSON)', 'sidrena-cijena' )
		);
		echo '</p>';

		if ( empty( $filtered ) ) {
			echo '<p>' . esc_html__( 'No findings for this filter.', 'sidrena-cijena' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Severity', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Code', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Entity', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Explanation', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Rule', 'sidrena-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		$shown = 0;

		foreach ( $filtered as $finding ) {
			if ( $shown >= 200 ) {
				break;
			}

			++$shown;

			echo '<tr>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( strtolower( (string) $finding['severity'] ) ) . '">' . esc_html( (string) $finding['severity'] ) . '</span></td>';
			echo '<td><code>' . esc_html( (string) $finding['code'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $finding['entity_type'] . ' #' . (int) $finding['entity_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $finding['message'] ) . '</td>';
			echo '<td>' . esc_html( (string) $finding['rule_reference'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		if ( count( $filtered ) > $shown ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of findings not shown. */
						__( '%d further findings are not shown. Export the report to see all of them.', 'sidrena-cijena' ),
						count( $filtered ) - $shown
					)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Run the compliance check and store the report for the current user.
	 *
	 * @return void
	 */
	public static function handle_run() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to run the compliance check.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION_RUN );

		$plugin  = Plugin::instance();
		$data    = DashboardPage::aggregate( self::REPORT_LIMIT );
		$report  = new ComplianceReport( $data['findings'] );
		$payload = $report->to_array();
		$payload = array_merge(
			$payload,
			array(
				'scanned' => (int) $data['scanned'],
				'total'   => (int) $data['total'],
			)
		);

		set_transient( self::TRANSIENT_PREFIX . get_current_user_id(), $payload, 30 * MINUTE_IN_SECONDS );

		if ( $payload['counts'][ Finding::ERROR ] > 0 ) {
			Logger::warning(
				'validation_failure',
				'Compliance check reported blocking errors.',
				array(
					'errors'   => (int) $payload['counts'][ Finding::ERROR ],
					'warnings' => (int) $payload['counts'][ Finding::WARNING ],
					'scanned'  => (int) $data['scanned'],
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'sidrena-cijena-compliance',
					'sidrena_cijena_notice' => 'report',
					'errors'                => (int) $payload['counts'][ Finding::ERROR ],
					'warnings'              => (int) $payload['counts'][ Finding::WARNING ],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Export the stored report as CSV or JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to export the compliance report.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION_EXPORT );

		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		$format = in_array( $format, array( 'csv', 'json' ), true ) ? $format : 'csv';

		$stored = self::stored_report();

		if ( ! $stored ) {
			$data   = DashboardPage::aggregate( self::REPORT_LIMIT );
			$report = new ComplianceReport( $data['findings'] );
		} else {
			$findings = array();

			foreach ( (array) $stored['findings'] as $row ) {
				$findings[] = new Finding(
					isset( $row['severity'] ) ? (string) $row['severity'] : Finding::INFO,
					isset( $row['code'] ) ? (string) $row['code'] : '',
					isset( $row['message'] ) ? (string) $row['message'] : '',
					array(
						'entity_type'    => isset( $row['entity_type'] ) ? (string) $row['entity_type'] : 'catalog_item',
						'entity_id'      => isset( $row['entity_id'] ) ? (int) $row['entity_id'] : 0,
						'rule_reference' => isset( $row['rule_reference'] ) ? (string) $row['rule_reference'] : '',
						'context'        => isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array(),
					)
				);
			}

			$report = new ComplianceReport(
				$findings,
				array(
					'generated_at' => isset( $stored['generated_at'] ) ? (string) $stored['generated_at'] : Dates::now_site()->format( 'c' ),
					'scanned'      => isset( $stored['scanned'] ) ? (int) $stored['scanned'] : 0,
					'total'        => isset( $stored['total'] ) ? (int) $stored['total'] : 0,
				)
			);
		}

		$filename = 'sidrena-cijena-compliance-report-' . gmdate( 'Ymd-Hi' );

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename . '.json' );
			echo $report->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Machine-readable download.
		} else {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename . '.csv' );
			echo $report->to_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Machine-readable download.
		}

		exit;
	}

	/**
	 * Stored report for the current user.
	 *
	 * @return array<mixed>|null
	 */
	public static function stored_report() {
		$stored = get_transient( self::TRANSIENT_PREFIX . get_current_user_id() );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Current severity filter from the request.
	 *
	 * @return string
	 */
	protected static function current_severity() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$severity = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';

		return in_array( $severity, array( 'error', 'warning', 'info' ), true ) ? strtoupper( $severity ) : '';
	}

	/**
	 * Filter stored finding arrays by severity.
	 *
	 * @param array<mixed> $findings Stored findings.
	 * @param string       $severity Severity filter.
	 * @return array<mixed>
	 */
	protected static function filter_findings( array $findings, $severity ) {
		if ( '' === $severity ) {
			return $findings;
		}

		return array_values(
			array_filter(
				$findings,
				function ( $finding ) use ( $severity ) {
					return isset( $finding['severity'] ) && $severity === $finding['severity'];
				}
			)
		);
	}

	/**
	 * Export URL for a format.
	 *
	 * @param string $format `csv` or `json`.
	 * @return string
	 */
	protected static function export_url( $format ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_EXPORT,
					'format' => $format,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_EXPORT
		);
	}

	/**
	 * Render the notice after a run.
	 *
	 * @return void
	 */
	protected static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice.
		$notice = isset( $_GET['sidrena_cijena_notice'] ) ? sanitize_key( wp_unslash( $_GET['sidrena_cijena_notice'] ) ) : '';

		if ( 'report' !== $notice ) {
			return;
		}

		$errors   = isset( $_GET['errors'] ) ? absint( wp_unslash( $_GET['errors'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$warnings = isset( $_GET['warnings'] ) ? absint( wp_unslash( $_GET['warnings'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s sidrena-cijena-notice is-dismissible"><p>%2$s</p></div>',
			$errors > 0 ? 'error' : 'success',
			esc_html(
				sprintf(
					/* translators: 1: error count, 2: warning count. */
					__( 'Compliance check finished: %1$d errors and %2$d warnings.', 'sidrena-cijena' ),
					$errors,
					$warnings
				)
			)
		);
	}
}
