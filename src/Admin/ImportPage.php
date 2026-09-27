<?php
/**
 * Price history import screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Import\ImportService;
use SidrenaCijena\Import\MigrationDetector;
use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * CSV import with preview, validation, dry run and transactional commit.
 */
class ImportPage {

	const SLUG = 'sidrena-cijena-import';

	const ACTION_PREVIEW = 'sidrena_cijena_import_preview';

	const ACTION_COMMIT = 'sidrena_cijena_import_commit';

	const ACTION_REPORT = 'sidrena_cijena_import_report';

	const TRANSIENT_PREFIX = 'sidrena_cijena_import_';

	const TRANSIENT_TTL = 1800;

	const PREVIEW_PER_PAGE = 50;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Import', 'sidrena-cijena-eu' ) . '</h1>';

		self::render_notice();
		self::render_guidance();
		self::render_migration_sources();
		self::render_upload_form();

		$preview = self::current_preview();

		if ( $preview ) {
			self::render_preview( $preview );
		}

		echo '</div>';
	}

	/**
	 * Handle the preview step.
	 *
	 * @return void
	 */
	public static function handle_preview() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to import anchor data.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION_PREVIEW, 'sidrena_cijena_import_nonce' );

		$file = self::uploaded_file();

		if ( is_wp_error( $file ) ) {
			self::redirect( 'error', $file->get_error_message() );

			return;
		}

		$args    = self::posted_args();
		$service = self::service();
		$preview = $service->preview( $file, $args );

		if ( is_wp_error( $preview ) ) {
			self::redirect( 'error', $preview->get_error_message() );

			return;
		}

		$key = self::transient_key( $preview['batch_id'] );

		set_transient(
			$key,
			array(
				'path' => $file,
				'args' => $args,
			),
			self::TRANSIENT_TTL
		);

		self::redirect( 'preview', __( 'Preview ready. Review the rows before committing.', 'sidrena-cijena-eu' ), $preview['batch_id'] );
	}

	/**
	 * Handle the commit step.
	 *
	 * @return void
	 */
	public static function handle_commit() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to import anchor data.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION_COMMIT, 'sidrena_cijena_import_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$stored   = get_transient( self::transient_key( $batch_id ) );

		if ( ! is_array( $stored ) || empty( $stored['path'] ) ) {
			self::redirect( 'error', __( 'The import session expired. Upload the file again.', 'sidrena-cijena-eu' ) );

			return;
		}

		$args = $stored['args'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$args['confirm_overwrite'] = isset( $_POST['confirm_overwrite'] ) && '1' === $_POST['confirm_overwrite'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$args['dry_run']  = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];
		$args['batch_id'] = $batch_id;

		$report = self::service()->commit( $stored['path'], $args );

		if ( is_wp_error( $report ) ) {
			self::redirect( 'error', $report->get_error_message(), $batch_id );

			return;
		}

		set_transient( self::transient_key( $batch_id ) . 'report', $report, self::TRANSIENT_TTL );

		if ( ! empty( $report['dry_run'] ) ) {
			self::redirect(
				'dry-run',
				sprintf(
					/* translators: %d: number of rows. */
					__( 'Dry run finished. %d row(s) would be imported; nothing was written.', 'sidrena-cijena-eu' ),
					(int) $report['inserted']
				),
				$batch_id
			);
		}

		self::redirect(
			'success',
			sprintf(
				/* translators: 1: inserted rows, 2: overwritten rows, 3: skipped rows. */
				__( 'Import finished: %1$d inserted, %2$d overwritten, %3$d skipped.', 'sidrena-cijena-eu' ),
				(int) $report['inserted'],
				(int) $report['overwritten'],
				(int) $report['skipped']
			),
			$batch_id
		);
	}

	/**
	 * Download the error report of the last import.
	 *
	 * @return void
	 */
	public static function handle_report() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to download the report.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION_REPORT, 'sidrena_cijena_import_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		$batch_id = isset( $_GET['batch_id'] ) ? sanitize_text_field( wp_unslash( $_GET['batch_id'] ) ) : '';
		$report   = get_transient( self::transient_key( $batch_id ) . 'report' );

		if ( ! is_array( $report ) ) {
			self::redirect( 'error', __( 'No report is available for this import.', 'sidrena-cijena-eu' ) );

			return;
		}

		$csv = self::service()->report_csv( $report );

		Logger::info( 'import', 'Import error report downloaded.', array( 'batch_id' => $batch_id ) );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sidrena-cijena-import-' . sanitize_file_name( $batch_id ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	/**
	 * Import service instance.
	 *
	 * @return ImportService
	 */
	protected static function service() {
		return Plugin::instance()->import_service();
	}

	/**
	 * Read the posted options.
	 *
	 * @return array<mixed>
	 */
	protected static function posted_args() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked by callers.
		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];

		return array(
			'dry_run' => $dry_run,
		);
	}

	/**
	 * Handle the uploaded file.
	 *
	 * @return string|\WP_Error
	 */
	protected static function uploaded_file() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce checked by the caller; the upload is validated by wp_handle_upload.
		$files = $_FILES;

		if ( empty( $files['sidrena_import_file'] ) || ! isset( $files['sidrena_import_file']['tmp_name'] ) || '' === $files['sidrena_import_file']['tmp_name'] ) {
			return new \WP_Error( 'import_file_missing', __( 'Choose a CSV file to import.', 'sidrena-cijena-eu' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$files['sidrena_import_file'],
			array(
				'test_form' => false,
				'mimes'     => array(
					'csv' => 'text/csv',
					'txt' => 'text/plain',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new \WP_Error( 'import_upload_failed', (string) $upload['error'] );
		}

		return (string) $upload['file'];
	}

	/**
	 * Stored preview for the current request, if any.
	 *
	 * @return array<mixed>|null
	 */
	protected static function current_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a stored preview.
		$batch_id = isset( $_GET['batch_id'] ) ? sanitize_text_field( wp_unslash( $_GET['batch_id'] ) ) : '';

		if ( '' === $batch_id ) {
			return null;
		}

		$stored = get_transient( self::transient_key( $batch_id ) );

		if ( ! is_array( $stored ) || empty( $stored['path'] ) ) {
			return null;
		}

		// Reuse the batch identifier from the URL: a fresh one would point at a
		// transient that was never written, so the commit form would post a
		// session the next request cannot find.
		$args             = $stored['args'];
		$args['batch_id'] = $batch_id;
		$preview          = self::service()->preview( $stored['path'], $args );
		if ( is_wp_error( $preview ) ) {
			return null;
		}

		return $preview;
	}

	/**
	 * Transient key for a batch.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return string
	 */
	protected static function transient_key( $batch_id ) {
		return self::TRANSIENT_PREFIX . get_current_user_id() . '-' . sanitize_key( $batch_id );
	}

	/**
	 * Redirect back to the screen with a notice.
	 *
	 * @param string $type     Notice type.
	 * @param string $message  Message.
	 * @param string $batch_id Batch identifier.
	 * @return void
	 */
	protected static function redirect( $type, $message, $batch_id = '' ) {
		$args = array(
			'page'                       => self::SLUG,
			'sidrena_cijena_notice'      => sanitize_key( $type ),
			'sidrena_cijena_notice_text' => rawurlencode( $message ),
		);

		if ( '' !== $batch_id ) {
			$args['batch_id'] = $batch_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	protected static function render_notice() {
		Notice::from_request();
	}

	/**
	 * Render the plain-language onboarding guidance.
	 *
	 * @return void
	 */
	protected static function render_guidance() {
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Before you import', 'sidrena-cijena-eu' ) . '</strong></p>';
		echo '<ul class="sidrena-guidance-list">';
		echo '<li>' . esc_html__( 'Today\'s price is not automatically the historical anchor price. Only import prices that were actually charged on the reference date.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li>' . esc_html__( 'For items first offered after the reference date, record the price applied when first offered and the date it was first applied; verify it against sales records.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li>' . esc_html__( 'Existing anchor records are never overwritten silently: rows that would replace them require explicit confirmation and are stored as corrections with a reason.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li>' . esc_html__( 'Accepted columns: sku or product_id, variation_id, reference_date, anchor_price and optionally category and name. Dates are Y-m-d or d.m.Y; decimal commas are accepted.', 'sidrena-cijena-eu' ) . '</li>';
		echo '</ul></div>';
	}

	/**
	 * Render detected migration sources.
	 *
	 * @return void
	 */
	protected static function render_migration_sources() {
		$sources = MigrationDetector::detect();

		echo '<h2>' . esc_html__( 'Existing price history sources', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<table class="widefat striped sidrena-sources-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Existing price history sources', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Source', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Description', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Records', 'sidrena-cijena-eu' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $sources as $source ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $source['label'] ) . '</td>';
			echo '<td>' . esc_html( (string) $source['description'] ) . '</td>';
			echo '<td>' . esc_html( (string) $source['rows'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Migration path', 'sidrena-cijena-eu' ) . '</h3><ol class="sidrena-migration-steps">';

		foreach ( MigrationDetector::guidance() as $step ) {
			echo '<li>' . esc_html( $step ) . '</li>';
		}

		echo '</ol>';
	}

	/**
	 * Render the upload form.
	 *
	 * @return void
	 */
	protected static function render_upload_form() {
		echo '<h2>' . esc_html__( 'Upload an import file', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PREVIEW ) . '" />';
		wp_nonce_field( self::ACTION_PREVIEW, 'sidrena_cijena_import_nonce' );
		echo '<p><label for="sidrena_import_file">' . esc_html__( 'Upload an import file', 'sidrena-cijena-eu' ) . '</label> <input type="file" id="sidrena_import_file" name="sidrena_import_file" accept=".csv,.txt,text/csv" required /></p>';
		echo '<p><label><input type="checkbox" name="dry_run" value="1" /> ' . esc_html__( 'Dry run (validate only, write nothing)', 'sidrena-cijena-eu' ) . '</label></p>';
		submit_button( __( 'Upload and preview', 'sidrena-cijena-eu' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render the preview table and the commit form.
	 *
	 * @param array<mixed> $preview Preview data.
	 * @return void
	 */
	protected static function render_preview( array $preview ) {
		$summary = $preview['summary'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$rows   = isset( $preview['rows'] ) ? (array) $preview['rows'] : array();
		$offset = ( $page - 1 ) * self::PREVIEW_PER_PAGE;

		echo '<h2>' . esc_html__( 'Preview', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: 1: total rows, 2: valid, 3: invalid, 4: duplicates, 5: overwrites, 6: unchanged. */
				__( '%1$d rows: %2$d valid, %3$d invalid, %4$d duplicate, %5$d would overwrite, %6$d unchanged.', 'sidrena-cijena-eu' ),
				(int) $summary['total'],
				(int) $summary['valid'],
				(int) $summary['invalid'],
				(int) $summary['duplicate'],
				(int) $summary['overwrite'],
				(int) $summary['unchanged']
			)
		);
		echo '</p>';

		if ( (int) $summary['overwrite'] > 0 ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some rows would replace existing anchor records. Tick the confirmation box below to allow that; replacements are stored as corrections with a reason.', 'sidrena-cijena-eu' ) . '</p></div>';
		}

		echo '<table class="widefat striped sidrena-cijena-import-preview">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Import preview', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Row', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Item', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'SKU', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Reference date', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Anchor price', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Notes', 'sidrena-cijena-eu' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( $rows, $offset, self::PREVIEW_PER_PAGE ) as $row ) {
			$item = $row['item'];
			$name = $item ? $item->name : '—';

			if ( $item && $item->is_variation() ) {
				$name .= ' (' . __( 'variation', 'sidrena-cijena-eu' ) . ' #' . (int) $item->variation_id . ')';
			}

			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $row['index'] + 2 ) ) . '</td>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( $row['status'] ) . '">' . esc_html( Labels::import_status( (string) $row['status'] ) ) . '</span></td>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( (string) $row['values']['sku'] ) . '</td>';
			echo '<td>' . esc_html( Dates::format_date_display( (string) $row['values']['reference_date'] ) ) . '</td>';
			echo '<td>' . esc_html( null === $row['values']['price'] ? '' : Currency::format_decimal( $row['values']['price'] ) ) . '</td>';
			echo '<td>';

			foreach ( array_merge( $row['errors'], $row['warnings'] ) as $problem ) {
				echo '<div>' . esc_html( (string) $problem['message'] ) . '</div>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, count( $rows ), self::PREVIEW_PER_PAGE );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sidrena-import-commit-form sidrena-busy-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_COMMIT ) . '" />';
		echo '<input type="hidden" name="batch_id" value="' . esc_attr( (string) $preview['batch_id'] ) . '" />';
		wp_nonce_field( self::ACTION_COMMIT, 'sidrena_cijena_import_nonce' );
		echo '<p><label><input type="checkbox" name="confirm_overwrite" value="1" /> ' . esc_html__( 'I confirm replacing existing anchor records listed above.', 'sidrena-cijena-eu' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="dry_run" value="1" /> ' . esc_html__( 'Dry run (validate only, write nothing)', 'sidrena-cijena-eu' ) . '</label></p>';
		submit_button( __( 'Commit import', 'sidrena-cijena-eu' ), 'primary sidrena-btn-busy', 'submit', false );
		echo '</form>';

		$report = get_transient( self::transient_key( (string) $preview['batch_id'] ) . 'report' );

		if ( is_array( $report ) ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => self::ACTION_REPORT,
						'batch_id' => (string) $preview['batch_id'],
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION_REPORT,
				'sidrena_cijena_import_nonce'
			);

			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Download error report (CSV)', 'sidrena-cijena-eu' ) . '</a></p>';
		}
	}
}
