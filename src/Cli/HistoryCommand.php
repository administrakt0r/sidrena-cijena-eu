<?php
/**
 * `wp sidrena-cijena history` command.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the append-only price history of one catalog item.
 */
final class HistoryCommand {

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
	 * Print history rows.
	 *
	 * Exit codes: 0 success, 1 no records or invalid arguments, 2 operational failure.
	 *
	 * @param array<mixed> $args       Positional arguments (<product_id>).
	 * @param array<mixed> $assoc_args Named arguments (--variation, --type, --limit, --format-output).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		try {
			$product_id = isset( $args[0] ) ? (int) $args[0] : 0;

			if ( $product_id <= 0 ) {
				Cli::out( 'Usage: wp sidrena-cijena history <product_id> [--variation=<id>] [--type=<type>] [--limit=<n>]', 'error' );

				return 1;
			}

			$qargs = array(
				'product_id' => $product_id,
				'limit'      => isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 50,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			);

			if ( isset( $assoc_args['variation'] ) && '' !== $assoc_args['variation'] ) {
				$qargs['variation_id'] = (int) $assoc_args['variation'];
			}

			if ( isset( $assoc_args['type'] ) && '' !== $assoc_args['type'] ) {
				$qargs['price_type'] = sanitize_key( (string) $assoc_args['type'] );
			}

			$rows   = Plugin::instance()->history()->query( $qargs );
			$output = isset( $assoc_args['format-output'] ) ? (string) $assoc_args['format-output'] : 'table';

			if ( empty( $rows ) ) {
				Cli::out( sprintf( 'No price history for product #%d.', $product_id ), 'warning' );

				return 1;
			}

			if ( 'json' === $output ) {
				Cli::out( Cli::as_json( $rows ), 'line' );

				return 0;
			}

			foreach ( $rows as $row ) {
				$price = isset( $row['price'] ) && '' !== $row['price'] ? Currency::format_decimal( $row['price'] ) : '—';

				Cli::out(
					sprintf(
						'#%d | %s | %s | %s | %s | ref %s | %s',
						(int) $row['id'],
						isset( $row['created_at'] ) ? Dates::from_utc( (string) $row['created_at'] )->format( 'Y-m-d H:i' ) : '?',
						isset( $row['price_type'] ) ? (string) $row['price_type'] : '?',
						$price,
						isset( $row['source'] ) ? (string) $row['source'] : '?',
						isset( $row['reference_date'] ) && '' !== $row['reference_date'] ? (string) $row['reference_date'] : '—',
						isset( $row['correction_of'] ) && $row['correction_of'] ? 'correction of #' . (int) $row['correction_of'] : ( isset( $row['correction_reason'] ) ? (string) $row['correction_reason'] : '' )
					)
				);
			}

			return 0;
		} catch ( \Exception $exception ) {
			Cli::out( 'History lookup failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}
}
