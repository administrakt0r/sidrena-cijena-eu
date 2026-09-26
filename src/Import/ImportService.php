<?php
/**
 * Import orchestration.
 *
 * @package SidrenaCijena\Import
 */

namespace SidrenaCijena\Import;

use SidrenaCijena\Logging\Logger;
use SidrenaCijena\Pricing\AnchorPriceService;

defined( 'ABSPATH' ) || exit;

/**
 * Runs previews and transactional imports.
 */
final class ImportService {

	/**
	 * CSV parser.
	 *
	 * @var CsvParser
	 */
	private $parser;

	/**
	 * Row validator.
	 *
	 * @var RowValidator
	 */
	private $validator;

	/**
	 * Anchor price service.
	 *
	 * @var AnchorPriceService
	 */
	private $anchors;

	/**
	 * Constructor.
	 *
	 * @param CsvParser          $parser    CSV parser.
	 * @param RowValidator       $validator Row validator.
	 * @param AnchorPriceService $anchors  Anchor price service.
	 */
	public function __construct( CsvParser $parser, RowValidator $validator, AnchorPriceService $anchors ) {
		$this->parser    = $parser;
		$this->validator = $validator;
		$this->anchors   = $anchors;
	}

	/**
	 * Parse and validate a file without writing anything.
	 *
	 * @param string       $path File path.
	 * @param array<mixed> $args Arguments.
	 * @return array<mixed>|\WP_Error
	 */
	public function preview( $path, array $args = array() ) {
		$parsed = $this->parser->parse_file( $path );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$validated               = $this->validator->validate_rows( $parsed['rows'] );
		$summary                 = $validated['summary'];
		$summary['parse_errors'] = count( $parsed['errors'] );

		return array(
			'file'      => basename( (string) $path ),
			'rows'      => $validated['rows'],
			'summary'   => $summary,
			'errors'    => $parsed['errors'],
			'headers'   => $parsed['headers'],
			'delimiter' => $parsed['delimiter'],
			'dry_run'   => ! empty( $args['dry_run'] ),
			'batch_id'  => isset( $args['batch_id'] ) && '' !== $args['batch_id'] ? (string) $args['batch_id'] : $this->batch_id( $path ),
		);
	}

	/**
	 * Commit a validated import transactionally.
	 *
	 * @param string       $path File path.
	 * @param array<mixed> $args Arguments (`dry_run`, `confirm_overwrite`, `batch_id`).
	 * @return array<mixed>|\WP_Error
	 */
	public function commit( $path, array $args = array() ) {
		$preview = $this->preview( $path, $args );

		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$batch_id  = isset( $args['batch_id'] ) && '' !== $args['batch_id'] ? (string) $args['batch_id'] : $preview['batch_id'];
		$overwrite = (int) $preview['summary']['overwrite'];

		if ( $overwrite > 0 && empty( $args['confirm_overwrite'] ) ) {
			return new \WP_Error(
				'overwrite_confirmation_required',
				sprintf(
					/* translators: %d: number of rows that would overwrite existing anchors. */
					__( '%d row(s) would overwrite existing anchor records. Confirm overwriting to continue.', 'sidrena-cijena' ),
					$overwrite
				)
			);
		}

		$report = array(
			'batch_id'    => $batch_id,
			'file'        => $preview['file'],
			'dry_run'     => ! empty( $args['dry_run'] ),
			'inserted'    => 0,
			'overwritten' => 0,
			'skipped'     => 0,
			'failed'      => 0,
			'errors'      => array(),
			'summary'     => $preview['summary'],
		);

		if ( $report['dry_run'] ) {
			$summary = $preview['summary'];

			$report['inserted'] = (int) $summary['valid'] + $overwrite;
			$report['skipped']  = (int) $summary['invalid'] + (int) $summary['duplicate'] + (int) $summary['unchanged'];
			$report['failed']   = (int) $summary['parse_errors'];

			return $report;
		}

		$wpdb = $GLOBALS['wpdb'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control for the atomic import batch.
		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $preview['rows'] as $row ) {
				$status = $row['status'];

				if ( 'invalid' === $status || 'duplicate' === $status ) {
					++$report['skipped'];
					$report['errors'] = array_merge( $report['errors'], $this->row_errors( $row, $batch_id ) );
					continue;
				}

				if ( 'unchanged' === $status ) {
					++$report['skipped'];
					continue;
				}

				$item = $row['item'];

				if ( ! $item ) {
					++$report['failed'];
					continue;
				}

				$record = array(
					'source'         => 'import',
					'reference_date' => $row['values']['reference_date'],
				);

				if ( 'overwrite' === $status ) {
					$record['correction_reason'] = sprintf(
						/* translators: %s: import batch identifier. */
						__( 'Imported overwrite (batch %s).', 'sidrena-cijena' ),
						$batch_id
					);
				}

				$result = $this->anchors->record( $item, $row['values']['price'], $record );

				if ( is_wp_error( $result ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollback of the atomic import batch.
					$wpdb->query( 'ROLLBACK' );

					Logger::error(
						'import',
						'Import rolled back.',
						array(
							'batch_id' => $batch_id,
							'code'     => $result->get_error_code(),
						)
					);

					return new \WP_Error(
						'import_rolled_back',
						sprintf(
							/* translators: %s: error message. */
							__( 'The import failed and was rolled back: %s', 'sidrena-cijena' ),
							$result->get_error_message()
						),
						array( 'report' => $report )
					);
				}

				if ( 'overwrite' === $status ) {
					++$report['overwritten'];
				} else {
					++$report['inserted'];
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit of the atomic import batch.
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollback of the atomic import batch.
			$wpdb->query( 'ROLLBACK' );

			Logger::error( 'import', 'Import rolled back after an exception.', array( 'batch_id' => $batch_id ) );

			return new \WP_Error( 'import_rolled_back', __( 'The import failed and was rolled back.', 'sidrena-cijena' ) );
		}

		$report['failed'] = max( (int) $report['failed'], count( $report['errors'] ) );

		Logger::info(
			'import',
			'Anchor import completed.',
			array(
				'batch_id'    => $batch_id,
				'inserted'    => $report['inserted'],
				'overwritten' => $report['overwritten'],
				'skipped'     => $report['skipped'],
			)
		);

		do_action( 'sidrena_cijena_import_completed', $batch_id, $report['inserted'], $report['skipped'], $report['errors'] );

		return $report;
	}

	/**
	 * Build an error report CSV.
	 *
	 * @param array<mixed> $report Import report.
	 * @return string
	 */
	public function report_csv( array $report ) {
		$lines = array( 'row;code;message;sku;product_id;reference_date;price' );

		foreach ( isset( $report['errors'] ) ? $report['errors'] : array() as $error ) {
			$lines[] = implode(
				';',
				array(
					isset( $error['row'] ) ? (int) $error['row'] : '',
					isset( $error['code'] ) ? (string) $error['code'] : '',
					$this->quote( isset( $error['message'] ) ? (string) $error['message'] : '' ),
					$this->quote( isset( $error['sku'] ) ? (string) $error['sku'] : '' ),
					isset( $error['product_id'] ) ? (int) $error['product_id'] : '',
					isset( $error['reference_date'] ) ? (string) $error['reference_date'] : '',
					isset( $error['price'] ) ? (string) $error['price'] : '',
				)
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Convert a row's problems into report errors.
	 *
	 * @param array<mixed> $row      Validated row.
	 * @param string       $batch_id Batch identifier.
	 * @return array<mixed>
	 */
	protected function row_errors( array $row, $batch_id ) {
		$errors = array();

		foreach ( $row['errors'] as $error ) {
			$errors[] = array(
				'row'            => $row['index'] + 2,
				'code'           => $error['code'],
				'message'        => $error['message'],
				'sku'            => $row['values']['sku'],
				'product_id'     => $row['values']['product_id'],
				'reference_date' => $row['values']['reference_date'],
				'price'          => $row['values']['price'],
				'batch_id'       => $batch_id,
			);
		}

		return $errors;
	}

	/**
	 * Quote a CSV field.
	 *
	 * @param string $value Field value.
	 * @return string
	 */
	protected function quote( $value ) {
		if ( false === strpos( $value, ';' ) && false === strpos( $value, '"' ) && false === strpos( $value, "\n" ) ) {
			return $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * Generate a batch identifier.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	protected function batch_id( $path ) {
		return 'imp-' . gmdate( 'YmdHis' ) . '-' . substr( md5( (string) $path . microtime() ), 0, 6 );
	}
}
