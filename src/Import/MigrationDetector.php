<?php
/**
 * Detection of existing price history data sources.
 *
 * @package Titanworks\SidrenaCijena\Import
 */

namespace Titanworks\SidrenaCijena\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Detects third-party or legacy pricing history that could be migrated.
 */
final class MigrationDetector {

	/**
	 * Known meta keys that may hold historical price data.
	 *
	 * @return array<string,string>
	 */
	public static function known_meta_keys() {
		return array(
			'_price_history'    => 'Price history meta',
			'_wc_price_history' => 'WooCommerce price history meta',
			'_wc_price_changes' => 'WooCommerce price change log meta',
		);
	}

	/**
	 * Known option names that indicate a price history plugin.
	 *
	 * @return array<string,string>
	 */
	public static function known_options() {
		return array(
			'woocommerce_price_history_version' => 'WooCommerce Price History plugin',
			'wc_price_history_version'          => 'WC Price History plugin',
			'price_history_db_version'          => 'Price History plugin',
		);
	}

	/**
	 * Detect available sources.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function detect() {
		$sources = array();
		$wpdb    = $GLOBALS['wpdb'];

		$own = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'sidrena_price_history' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table.

		$sources[] = array(
			'id'          => 'sidrena_history',
			'label'       => __( 'Sidrena cijena price history', 'sidrena-cijena-eu' ),
			'description' => __( 'Anchor and price records already stored by this plugin.', 'sidrena-cijena-eu' ),
			'rows'        => (int) $own,
			'migration'   => false,
		);

		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection for migration detection.

		foreach ( (array) $tables as $table ) {
			$table = (string) $table;

			if ( 0 === strpos( $table, $wpdb->prefix . 'sidrena_' ) ) {
				continue;
			}

			if ( false === stripos( $table, 'price_history' ) && false === stripos( $table, 'price_log' ) ) {
				continue;
			}

			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows for migration detection.

			$sources[] = array(
				'id'          => 'table:' . $table,
				'label'       => $table,
				'description' => __( 'A database table that appears to store price history.', 'sidrena-cijena-eu' ),
				'rows'        => $count,
				'migration'   => true,
			);
		}

		foreach ( self::known_options() as $option => $label ) {
			if ( false === get_option( $option, false ) ) {
				continue;
			}

			$sources[] = array(
				'id'          => 'option:' . $option,
				'label'       => $label,
				'description' => __( 'A plugin option indicates an existing price history source.', 'sidrena-cijena-eu' ),
				'rows'        => 0,
				'migration'   => true,
			);
		}

		foreach ( self::known_meta_keys() as $meta_key => $label ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key = %s', $wpdb->postmeta, $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows for migration detection.

			if ( $count > 0 ) {
				$sources[] = array(
					'id'          => 'meta:' . $meta_key,
					'label'       => $label,
					'description' => __( 'Product meta that may contain historical prices.', 'sidrena-cijena-eu' ),
					'rows'        => $count,
					'migration'   => true,
				);
			}
		}

		return $sources;
	}

	/**
	 * Plain-language migration guidance.
	 *
	 * @return array<int,string>
	 */
	public static function guidance() {
		return array(
			__( 'Export the historical prices from the existing source (most plugins offer a CSV export).', 'sidrena-cijena-eu' ),
			__( 'Map its columns to sku or product_id, reference_date, anchor_price and optionally variation_id.', 'sidrena-cijena-eu' ),
			__( 'Upload the exported file here, review the preview and confirm the import.', 'sidrena-cijena-eu' ),
			__( 'Do not delete the original data source until the imported anchors have been verified.', 'sidrena-cijena-eu' ),
		);
	}
}
