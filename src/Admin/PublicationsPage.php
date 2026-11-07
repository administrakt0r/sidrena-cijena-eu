<?php
/**
 * Published price-list archive screen.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

use Titanworks\SidrenaCijena\Cron\DailyGeneration;
use Titanworks\SidrenaCijena\Cron\Scheduler;
use Titanworks\SidrenaCijena\Export\FilenameGenerator;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Dates;
use Titanworks\SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Lists generated price-list publications per location and format.
 */
class PublicationsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-publications';

	/**
	 * Generate-now action.
	 *
	 * @var string
	 */
	const ACTION = 'twbce_cijena_generate_now';

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$per_page = 20;

		$publications = Plugin::instance()->publications();
		$result       = $publications->index(
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		$locations = array();

		foreach ( Plugin::instance()->locations()->all() as $location ) {
			$locations[ (int) $location['id'] ] = $location;
		}

		$last = $publications->last_successful();
		$next = Scheduler::next_run();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Price lists', 'sidrena-cijena-eu' ) . '</h1>';

		self::notice();

		echo '<p>';
		echo esc_html__( 'Filenames follow the legally structured pattern:', 'sidrena-cijena-eu' ) . ' ';
		echo '<code>' . esc_html( FilenameGenerator::pattern() ) . '</code>';
		echo '</p>';

		echo '<p>';
		printf(
			/* translators: 1: last publication time, 2: next scheduled run. */
			esc_html__( 'Last published: %1$s. Next scheduled generation: %2$s.', 'sidrena-cijena-eu' ),
			esc_html( $last && $last['published_at'] ? self::site_time( $last['published_at'] ) : __( 'never', 'sidrena-cijena-eu' ) ),
			esc_html( $next ? self::site_time( gmdate( 'Y-m-d H:i:s', (int) $next ) ) : __( 'not scheduled', 'sidrena-cijena-eu' ) )
		);
		echo '</p>';

		$last_gen = get_option( DailyGeneration::RESULT_OPTION, null );

		if ( $last_gen && is_array( $last_gen ) && ! empty( $last_gen['summary']['failed'] ) ) {
			$gen_time = ! empty( $last_gen['timestamp'] ) ? self::site_time( (int) $last_gen['timestamp'] ) : '';
			$source   = isset( $last_gen['source'] ) ? (string) $last_gen['source'] : 'scheduled';

			echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Last generation failed:', 'sidrena-cijena-eu' ) . '</strong> ';
			printf(
				/* translators: 1: generation time, 2: run source, 3: error details */
				esc_html__( '%1$s (%2$s) — %3$s', 'sidrena-cijena-eu' ),
				esc_html( $gen_time ),
				esc_html( Labels::run_source( $source ) ),
				esc_html( ! empty( $last_gen['errors'] ) ? implode( '; ', (array) $last_gen['errors'] ) : __( 'unknown error', 'sidrena-cijena-eu' ) )
			);
			echo '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sidrena-busy-form">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		submit_button( __( 'Generate now', 'sidrena-cijena-eu' ), 'primary sidrena-btn-busy', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Published price lists', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Filename', 'sidrena-cijena-eu' ),
			__( 'Format', 'sidrena-cijena-eu' ),
			__( 'Location', 'sidrena-cijena-eu' ),
			__( 'Published', 'sidrena-cijena-eu' ),
			__( 'Expires', 'sidrena-cijena-eu' ),
			__( 'Items', 'sidrena-cijena-eu' ),
			__( 'Checksum', 'sidrena-cijena-eu' ),
			__( 'Status', 'sidrena-cijena-eu' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No price lists have been published yet.', 'sidrena-cijena-eu' ) . '</td></tr>';
		}

		foreach ( $result['items'] as $row ) {
			$location_name = isset( $locations[ (int) $row['location_id'] ] ) ? (string) $locations[ (int) $row['location_id'] ]['name'] : '#' . (int) $row['location_id'];

			echo '<tr>';
			echo '<td>';
			if ( ! empty( $row['url'] ) ) {
				echo '<a href="' . esc_url( (string) $row['url'] ) . '">' . esc_html( (string) $row['filename'] ) . '</a>';
			} else {
				echo esc_html( (string) $row['filename'] );
			}
			echo '</td>';
			echo '<td>' . esc_html( strtoupper( (string) $row['format'] ) ) . '</td>';
			echo '<td>' . esc_html( $location_name ) . '</td>';
			echo '<td>' . esc_html( self::site_time( $row['published_at'] ) ) . '</td>';
			echo '<td>' . esc_html( self::site_time( $row['expires_at'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['item_count'] ) . '</td>';
			echo '<td><code>' . esc_html( substr( (string) $row['checksum'], 0, 12 ) ) . '</code></td>';
			$summary   = isset( $row['validation_summary'] ) && is_array( $row['validation_summary'] ) ? $row['validation_summary'] : array();
			$err_count = ! empty( $summary['errors'] ) ? (int) $summary['errors'] : ( ! empty( $summary['findings'] ) ? count( $summary['findings'] ) : 0 );

			echo '<td>';
			echo '<span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['status'] ) . '">' . esc_html( Labels::publication_status( (string) $row['status'] ) ) . '</span>';
			if ( $err_count > 0 && 'published' === (string) $row['status'] ) {
				$warning_title = sprintf(
					/* translators: %d: number of compliance findings */
					__( '%d compliance findings', 'sidrena-cijena-eu' ),
					$err_count
				);
				$warning_label = sprintf(
					/* translators: %d: number of compliance warnings */
					__( '%d warnings', 'sidrena-cijena-eu' ),
					$err_count
				);
				echo ' <span class="sidrena-cijena-status sidrena-cijena-status--warning" title="' . esc_attr( $warning_title ) . '">' . esc_html( $warning_label ) . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, (int) $result['total'], $per_page );

		echo '</div>';
	}

	/**
	 * Handle the "Generate now" action.
	 *
	 * @return void
	 */
	public static function handle_generate() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION );

		$results = Plugin::instance()->generation()->generate_all(
			array(
				'source' => 'manual',
				'force'  => true,
			)
		);

		$published     = 0;
		$failed        = 0;
		$with_findings = 0;

		foreach ( $results as $location ) {
			if ( is_wp_error( $location ) ) {
				++$failed;
				continue;
			}

			foreach ( (array) $location as $result ) {
				if ( is_wp_error( $result ) ) {
					++$failed;
				} else {
					++$published;
					if ( ! empty( $result['has_errors'] ) ) {
						++$with_findings;
					}
				}
			}
		}

		$url = add_query_arg(
			array(
				'page'                => self::SLUG,
				'twbce_cijena_notice' => $failed ? 'error' : ( $with_findings ? 'warning' : 'success' ),
				'generated'           => $published,
				'failed'              => $failed,
				'with_findings'       => $with_findings,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the generation notice.
	 *
	 * @return void
	 */
	protected static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice flags.
		$notice = isset( $_GET['twbce_cijena_notice'] ) ? sanitize_key( wp_unslash( $_GET['twbce_cijena_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		$generated     = isset( $_GET['generated'] ) ? absint( wp_unslash( $_GET['generated'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed        = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$with_findings = isset( $_GET['with_findings'] ) ? absint( wp_unslash( $_GET['with_findings'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'error' === $notice ) {
			Notice::render(
				'error',
				sprintf(
					/* translators: 1: number of generated price lists, 2: number of failures. */
					__( 'Generation finished: %1$d price lists generated, %2$d failed.', 'sidrena-cijena-eu' ),
					(int) $generated,
					(int) $failed
				)
			);
		} elseif ( 'warning' === $notice || $with_findings > 0 ) {
			$compliance_url = AdminMenu::url( 'sidrena-cijena-compliance' );
			Notice::render(
				'warning',
				sprintf(
					/* translators: 1: number of generated price lists, 2: number of price lists with findings, 3: compliance check URL. */
					__( 'Price lists published successfully: %1$d files created (%2$d with compliance warnings). <a href="%3$s">Review the Compliance report</a> for proposed fixes.', 'sidrena-cijena-eu' ),
					(int) $generated,
					(int) $with_findings,
					esc_url( $compliance_url )
				)
			);
		} else {
			Notice::render(
				'success',
				sprintf(
					/* translators: 1: number of generated price lists. */
					__( 'Generation finished: %1$d price lists generated.', 'sidrena-cijena-eu' ),
					(int) $generated
				)
			);
		}
	}

	/**
	 * Convert a stored UTC datetime to the site time zone.
	 *
	 * @param mixed $datetime UTC datetime.
	 * @return string
	 */
	protected static function site_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '—';
		}

		$formatted = Dates::format_datetime_display( $datetime );

		return '' !== $formatted ? $formatted : '—';
	}
}
