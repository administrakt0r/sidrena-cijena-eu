<?php
/**
 * Compliance check screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\ComplianceReport;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\RemediationProposer;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

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
	 * Findings per page in the report table.
	 */
	const FINDINGS_PER_PAGE = 50;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'titanworks-bazna-cijena' ) );
		}

		$report = self::stored_report();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Compliance check', 'titanworks-bazna-cijena' ) . '</h1>';

		self::notice();
		self::render_statutory_guide();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sidrena-busy-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '" />';
		wp_nonce_field( self::ACTION_RUN );
		submit_button( __( 'Run compliance check', 'titanworks-bazna-cijena' ), 'primary sidrena-btn-busy', 'submit', false );
		echo '</form>';

		if ( ! $report ) {
			echo '<p>' . esc_html__( 'No report has been generated yet. Run the compliance check to validate the catalog and the published price lists.', 'titanworks-bazna-cijena' ) . '</p>';
			echo '</div>';

			return;
		}

		$counts   = isset( $report['counts'] ) ? (array) $report['counts'] : array();
		$findings = isset( $report['findings'] ) ? (array) $report['findings'] : array();
		$gen_at   = isset( $report['generated_at'] ) ? Dates::format_datetime_display( (string) $report['generated_at'] ) : '';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: generation timestamp, 2: scanned items, 3: total items. */
					__( 'Report generated %1$s from %2$d of %3$d catalog items.', 'titanworks-bazna-cijena' ),
					$gen_at,
					isset( $report['scanned'] ) ? (int) $report['scanned'] : 0,
					isset( $report['total'] ) ? (int) $report['total'] : 0
				)
			)
		);

		echo '<div class="sidrena-cijena-cards">';

		foreach ( array( Finding::ERROR, Finding::WARNING, Finding::INFO ) as $severity ) {
			echo '<div class="sidrena-cijena-card">';
			echo '<span class="sidrena-cijena-card__value">' . esc_html( (string) ( isset( $counts[ $severity ] ) ? $counts[ $severity ] : 0 ) ) . '</span>';
			echo '<span class="sidrena-cijena-card__label">' . esc_html( Labels::severity( $severity ) ) . '</span>';
			echo '</div>';
		}

		echo '</div>';

		$severity = self::current_severity();
		$filtered = self::filter_findings( $findings, $severity );

		echo '<p>';
		foreach ( array(
			''               => __( 'All', 'titanworks-bazna-cijena' ),
			Finding::ERROR   => Labels::severity( Finding::ERROR ),
			Finding::WARNING => Labels::severity( Finding::WARNING ),
			Finding::INFO    => Labels::severity( Finding::INFO ),
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
			esc_html__( 'Export report (CSV)', 'titanworks-bazna-cijena' )
		);
		printf(
			'<a class="button" href="%1$s">%2$s</a>',
			esc_url( self::export_url( 'json' ) ),
			esc_html__( 'Export report (JSON)', 'titanworks-bazna-cijena' )
		);
		echo '</p>';

		if ( empty( $filtered ) ) {
			echo '<p>' . esc_html__( 'No findings for this filter.', 'titanworks-bazna-cijena' ) . '</p>';
			echo '</div>';

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$total  = count( $filtered );
		$offset = ( $page - 1 ) * self::FINDINGS_PER_PAGE;
		$slice  = array_slice( $filtered, $offset, self::FINDINGS_PER_PAGE );

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Compliance findings', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Severity', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Code', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Entity', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Explanation', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Proposed change', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Rule', 'titanworks-bazna-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $slice as $finding ) {
			$proposal = isset( $finding['proposal'] ) && '' !== $finding['proposal']
				? (string) $finding['proposal']
				: RemediationProposer::propose(
					isset( $finding['code'] ) ? (string) $finding['code'] : '',
					isset( $finding['context'] ) && is_array( $finding['context'] ) ? $finding['context'] : array()
				);

			echo '<tr>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( strtolower( (string) $finding['severity'] ) ) . '">' . esc_html( Labels::severity( (string) $finding['severity'] ) ) . '</span></td>';
			echo '<td><code>' . esc_html( (string) $finding['code'] ) . '</code></td>';
			echo '<td>' . wp_kses_post( AdminLinks::render_entity_link( (string) $finding['entity_type'], (int) $finding['entity_id'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $finding['message'] ) . '</td>';
			echo '<td>' . ( '' !== $proposal ? '<strong>' . esc_html( $proposal ) . '</strong>' : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) $finding['rule_reference'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, $total, self::FINDINGS_PER_PAGE );

		echo '</div>';
	}

	/**
	 * Run the compliance check and store the report for the current user.
	 *
	 * @return void
	 */
	public static function handle_run() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to run the compliance check.', 'titanworks-bazna-cijena' ) );
		}

		check_admin_referer( self::ACTION_RUN );

		$plugin  = Plugin::instance();
		$data    = DashboardPage::aggregate( self::REPORT_LIMIT, true );
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
			wp_die( esc_html__( 'You do not have permission to export the compliance report.', 'titanworks-bazna-cijena' ) );
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

		Notice::render(
			$errors > 0 ? 'error' : 'success',
			sprintf(
				/* translators: 1: error count, 2: warning count. */
				__( 'Compliance check finished: %1$d errors and %2$d warnings.', 'titanworks-bazna-cijena' ),
				$errors,
				$warnings
			)
		);
	}

	/**
	 * Render the Croatian statutory compliance and conditions guide.
	 *
	 * @return void
	 */
	public static function render_statutory_guide() {
		$gen_date = (string) RuleConfig::get( 'general_reference_date', RuleConfig::DEFAULT_GENERAL_DATE );
		$leg_date = (string) RuleConfig::get( 'legacy_reference_date', RuleConfig::DEFAULT_LEGACY_DATE );

		echo '<div class="sidrena-statutory-guide">';
		echo '<div class="sidrena-statutory-guide__header">';
		echo '<h2>' . esc_html__( 'Croatian Statutory Compliance Framework & Conditions', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<span class="sidrena-statutory-badge">' . esc_html__( 'Enforcement: 17 November 2026 (NN 110/2026)', 'titanworks-bazna-cijena' ) . '</span>';
		echo '</div>';
		echo '<p class="sidrena-statutory-intro">' . esc_html__( 'This cross-compliance engine continuously audits your catalog and publications against Croatian statutory decrees (NN 101/2026-1212, NN 101/2026-1213, NN 110/2026, NN 40/25) and the Consumer Protection Act (NN 19/22, 59/26, Art. 19).', 'titanworks-bazna-cijena' ) . '</p>';

		echo '<div class="sidrena-statutory-grid">';

		// Pillar 1: Anchor / Base Price.
		echo '<div class="sidrena-statutory-col">';
		echo '<h3><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html__( '1. Bazna / Sidrena cijena', 'titanworks-bazna-cijena' ) . '</h3>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'General reference date:', 'titanworks-bazna-cijena' ) . '</strong> <code>' . esc_html( $gen_date ) . '</code> (' . esc_html__( 'all B2C products & services', 'titanworks-bazna-cijena' ) . ')</li>';
		echo '<li><strong>' . esc_html__( 'Legacy reference date:', 'titanworks-bazna-cijena' ) . '</strong> <code>' . esc_html( $leg_date ) . '</code> (' . esc_html__( 'food, drinks, cosmetics, household', 'titanworks-bazna-cijena' ) . ')</li>';
		echo '<li><strong>' . esc_html__( 'Permitted labels:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Bazna cijena, Sidrena cijena, Cijena na [datum], Referentna cijena, Dodatna cijena', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Post-reference listings:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Verified first-offer price & date mandatory; blank anchor is prohibited.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Pillar 2: Triple Price Rule for Promotions.
		echo '<div class="sidrena-statutory-col">';
		echo '<h3><span class="dashicons dashicons-tag"></span> ' . esc_html__( '2. Special Sales: "Triple Price" Rule', 'titanworks-bazna-cijena' ) . '</h3>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Three distinct prices:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'When an item is discounted (akcija, sniženje), you must display: (1) Promotional price, (2) Lowest price in preceding 30 days, and (3) Anchor/base price.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Strict legal separation:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'The anchor price must never be used as the 30-day price, and the 30-day price must never be used as the anchor price.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Price condition:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Promotional price must be strictly lower than the lowest price from the preceding 30 days.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Pillar 3: Daily Price List.
		echo '<div class="sidrena-statutory-col">';
		echo '<h3><span class="dashicons dashicons-media-spreadsheet"></span> ' . esc_html__( '3. Daily Machine-Readable Price List', 'titanworks-bazna-cijena' ) . '</h3>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Daily publication:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Every working morning before 08:00 (products) and upon every price change (services).', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Formats:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'CSV (; delimiter, comma decimals, 12 columns) and XML formats.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( '30-day public archive:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Dated publication archive must remain accessible for at least 30 calendar days.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Filename contract:', 'titanworks-bazna-cijena' ) . '</strong> <code>{FORM}_{ADDRESS}_{ID}_{SEQ}_{DATE}_{TIME}.EXT</code></li>';
		echo '</ul>';
		echo '</div>';

		// Pillar 4: Audit & Remediation Proposals.
		echo '<div class="sidrena-statutory-col">';
		echo '<h3><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( '4. Audit & Remediation Proposals', 'titanworks-bazna-cijena' ) . '</h3>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Supervisory body:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'State Inspectorate (Državni inspektorat) enforces compliance and penal sanctions.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Mandatory attributes:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Barcodes (EAN/GTIN) and unit prices (per kg/l) are checked where applicable.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Actionable fixes:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Every detected warning or error includes a concrete proposal ("Prijedlog izmjene") to resolve findings before publication.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		echo '</div>'; // .sidrena-statutory-grid
		echo '</div>'; // .sidrena-statutory-guide
	}
}
