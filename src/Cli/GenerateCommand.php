<?php
/**
 * `wp sidrena-cijena generate` command.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

use SidrenaCijena\Export\PriceListBuilder;
use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Generates price-list publications from the command line.
 */
final class GenerateCommand {

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
	 * Generate price lists.
	 *
	 * Exit codes: 0 success, 1 validation/compliance errors, 2 operational failure.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments (--format, --location, --force, --dry-run).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		unset( $args );

		try {
			$plugin   = Plugin::instance();
			$format   = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'both';
			$formats  = 'both' === $format ? array( 'csv', 'xml' ) : array( $format );
			$location = isset( $assoc_args['location'] ) && '' !== $assoc_args['location'] ? (int) $assoc_args['location'] : 0;
			$force    = isset( $assoc_args['force'] );
			$dry_run  = isset( $assoc_args['dry-run'] );
			$gen_args = array(
				'source'  => 'cli',
				'force'   => $force,
				'formats' => $formats,
			);

			if ( $dry_run ) {
				return self::dry_run( $plugin, $location, $formats );
			}

			$failed = 0;

			if ( $location > 0 ) {
				foreach ( $formats as $one ) {
					$failed += self::report_one( $plugin->generation()->generate( $location, $one, $gen_args ) );
				}
			} else {
				foreach ( $plugin->generation()->generate_all( $gen_args ) as $location_id => $results ) {
					if ( is_wp_error( $results ) ) {
						Cli::out( sprintf( 'Location #%d: %s', (int) $location_id, $results->get_error_message() ), 'error' );
						++$failed;
						continue;
					}

					foreach ( (array) $results as $one => $result ) {
						if ( ! in_array( (string) $one, $formats, true ) ) {
							continue;
						}

						$failed += self::report_one( $result );
					}
				}
			}

			Cli::out( $failed > 0 ? sprintf( 'Generation finished with %d failure(s).', $failed ) : 'Generation finished successfully.', $failed > 0 ? 'warning' : 'success' );

			return $failed > 0 ? 1 : 0;
		} catch ( \Exception $exception ) {
			Cli::out( 'Generation failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}

	/**
	 * Preview item counts without writing files.
	 *
	 * @param Plugin   $plugin   Plugin instance.
	 * @param int      $location Location ID (0 for all active).
	 * @param string[] $formats  Formats.
	 * @return int Exit code.
	 */
	protected static function dry_run( $plugin, $location, $formats ) {
		$builder   = new PriceListBuilder( $plugin->catalog(), $plugin->history(), $plugin->special_sales() );
		$locations = $location > 0 ? array( $plugin->locations()->get( $location ) ) : $plugin->locations()->all( true );
		$failed    = 0;

		foreach ( $locations as $row ) {
			if ( ! $row ) {
				continue;
			}

			$report = $plugin->validator()->validate(
				array(
					'scope'       => 'catalog',
					'location_id' => (int) $row['id'],
					'severity'    => 'ERROR',
				)
			);

			if ( ! empty( $report['findings'] ) ) {
				++$failed;

				Cli::out(
					sprintf(
						/* translators: 1: location ID, 2: location name, 3: number of validation errors. */
						__( 'Location #%1$d %2$s: %3$d compliance error(s); nothing would be published.', 'sidrena-cijena-eu' ),
						(int) $row['id'],
						(string) $row['name'],
						count( $report['findings'] )
					),
					'error'
				);

				continue;
			}

			$build = $builder->build( $row );

			foreach ( $formats as $one ) {
				Cli::out( sprintf( 'Location #%d %s (%s): %d item(s) would be published.', (int) $row['id'], (string) $row['name'], strtoupper( $one ), count( $build['items'] ) ) );
			}
		}

		return $failed > 0 ? 1 : 0;
	}

	/**
	 * Report one generation result.
	 *
	 * @param mixed $result Result array or WP_Error.
	 * @return int 1 when the result is a failure, 0 otherwise.
	 */
	protected static function report_one( $result ) {
		if ( is_wp_error( $result ) ) {
			Cli::out( 'Generation error: ' . $result->get_error_message(), 'error' );

			return 1;
		}

		if ( ! is_array( $result ) ) {
			return 1;
		}

		$publication = isset( $result['publication'] ) && is_array( $result['publication'] ) ? $result['publication'] : array();

		Cli::out(
			sprintf(
				'Location #%d %s: %s (%d item(s), checksum %s, %s)%s',
				isset( $result['location_id'] ) ? (int) $result['location_id'] : 0,
				isset( $result['format'] ) ? strtoupper( (string) $result['format'] ) : '',
				isset( $result['filename'] ) ? (string) $result['filename'] : '',
				isset( $result['item_count'] ) ? (int) $result['item_count'] : 0,
				isset( $publication['checksum'] ) ? substr( (string) $publication['checksum'], 0, 12 ) : '—',
				isset( $publication['url'] ) ? (string) $publication['url'] : '—',
				! empty( $result['skipped'] ) ? ' [unchanged, skipped]' : ''
			)
		);

		return 0;
	}
}
