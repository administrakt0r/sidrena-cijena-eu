<?php
/**
 * `wp sidrena-cijena import` command.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Imports anchor prices from a CSV file.
 */
final class ImportCommand {

	/**
	 * WP-CLI entry point.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public static function run( $args, $assoc_args ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exit code, not output.
		exit( self::execute( (array) $args, (array) $assoc_args ) );
	}

	/**
	 * Preview or commit an import.
	 *
	 * Exit codes: 0 success, 1 validation errors, 2 operational failure,
	 * 3 invalid arguments or unreadable file.
	 *
	 * @param array<mixed> $args       Positional arguments (<file>).
	 * @param array<mixed> $assoc_args Named arguments (--dry-run, --confirm-overwrite, --format-output).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		try {
			$file = isset( $args[0] ) ? (string) $args[0] : '';

			if ( '' === $file || ! is_readable( $file ) ) {
				Cli::out( 'Usage: wp sidrena-cijena import <file> [--dry-run] [--confirm-overwrite]. The file must exist and be readable.', 'error' );

				return 3;
			}

			$service = Plugin::instance()->import_service();
			$cargs   = array(
				'confirm_overwrite' => isset( $assoc_args['confirm-overwrite'] ),
			);
			$output  = isset( $assoc_args['format-output'] ) ? (string) $assoc_args['format-output'] : 'table';
			$is_dry  = isset( $assoc_args['dry-run'] );
			$result  = $is_dry ? $service->preview( $file, array( 'dry_run' => true ) ) : $service->commit( $file, $cargs );

			if ( is_wp_error( $result ) ) {
				Cli::out( 'Import failed: ' . $result->get_error_message(), 'error' );

				return 'overwrite_confirmation_required' === $result->get_error_code() ? 1 : 2;
			}

			$summary    = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
			$invalid    = isset( $summary['invalid'] ) ? (int) $summary['invalid'] : 0;
			$overwrites = isset( $result['overwritten'] ) ? (int) $result['overwritten'] : ( isset( $summary['overwrite'] ) ? (int) $summary['overwrite'] : 0 );
			$inserted   = isset( $result['inserted'] ) ? (int) $result['inserted'] : 0;

			if ( 'json' === $output ) {
				Cli::out(
					Cli::as_json(
						array(
							'summary'  => $summary,
							'batch_id' => isset( $result['batch_id'] ) ? $result['batch_id'] : null,
						)
					),
					'line'
				);
			} else {
				Cli::out(
					sprintf(
						'%s: %d valid, %d invalid, %d duplicate(s), %d overwrite(s), %d unchanged, %d inserted.',
						$is_dry ? 'Dry run' : 'Import',
						isset( $summary['valid'] ) ? (int) $summary['valid'] : 0,
						$invalid,
						isset( $summary['duplicate'] ) ? (int) $summary['duplicate'] : 0,
						$overwrites,
						isset( $summary['unchanged'] ) ? (int) $summary['unchanged'] : 0,
						$inserted
					)
				);
			}

			if ( $invalid > 0 ) {
				return 1;
			}

			return 0;
		} catch ( \Exception $exception ) {
			Cli::out( 'Import failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}
}
