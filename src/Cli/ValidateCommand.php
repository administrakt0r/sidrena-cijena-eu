<?php
/**
 * `wp sidrena-cijena validate` command.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the compliance check from the command line.
 */
final class ValidateCommand {

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
	 * Run validation.
	 *
	 * Exit codes: 0 clean, 1 compliance errors, 2 operational failure.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments (--format, --location, --severity, --format-output).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		unset( $args );

		try {
			$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'both';

			if ( ! in_array( $format, array( 'csv', 'xml', 'both' ), true ) ) {
				Cli::out( 'Invalid --format. Use csv, xml or both.', 'error' );

				return 3;
			}

			$vargs = array(
				'scope' => 'both' === $format ? 'all' : 'publications',
			);

			if ( isset( $assoc_args['location'] ) && '' !== $assoc_args['location'] ) {
				$vargs['location_id'] = absint( $assoc_args['location'] );
			}

			if ( isset( $assoc_args['severity'] ) && in_array( strtoupper( (string) $assoc_args['severity'] ), array( 'ERROR', 'WARNING', 'INFO' ), true ) ) {
				$vargs['severity'] = strtoupper( (string) $assoc_args['severity'] );
			}

			$result   = Plugin::instance()->validator()->validate( $vargs );
			$counts   = Validator::counts( $result['findings'] );
			$output   = isset( $assoc_args['format-output'] ) ? (string) $assoc_args['format-output'] : 'table';
			$errors   = isset( $counts['ERROR'] ) ? (int) $counts['ERROR'] : 0;
			$warnings = isset( $counts['WARNING'] ) ? (int) $counts['WARNING'] : 0;

			if ( 'json' === $output ) {
				$rows = array();

				foreach ( $result['findings'] as $finding ) {
					$rows[] = $finding->to_array();
				}

				Cli::out(
					Cli::as_json(
						array(
							'counts'   => $counts,
							'findings' => $rows,
						)
					),
					'line'
				);
			} else {
				Cli::out( sprintf( 'Findings: %d error(s), %d warning(s).', $errors, $warnings ) );

				foreach ( $result['findings'] as $finding ) {
					$row = $finding->to_array();
					Cli::out( sprintf( '[%s] %s: %s', isset( $row['severity'] ) ? $row['severity'] : '?', isset( $row['code'] ) ? $row['code'] : '?', isset( $row['message'] ) ? $row['message'] : '' ) );
				}
			}

			return $errors > 0 ? 1 : 0;
		} catch ( \Throwable $exception ) {
			Cli::out( 'Validation failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}
}
