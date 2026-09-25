<?php
/**
 * Opt-in uninstall cleanup.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes plugin-owned data when the administrator explicitly opted in.
 */
final class Uninstaller {

	/**
	 * Option that gates data deletion on uninstall.
	 */
	const DELETE_OPTION = 'sidrena_cijena_delete_data_on_uninstall';

	/**
	 * Price-history types that are legal evidence and survive the deletion.
	 *
	 * The anchor price is the proof of the price applied on the reference date and the
	 * 30-day lowest price is the proof of what the shop displayed before a promotion.
	 * Both are kept when everything else is deleted, the way a merchant keeps the
	 * printed price lists after closing the shop.
	 *
	 * @var string[]
	 */
	const EVIDENCE_TYPES = array( 'anchor', 'lowest_30_day' );

	/**
	 * Plugin options that belong to the plugin and nothing else.
	 *
	 * @var string[]
	 */
	private static $options = array(
		'sidrena_cijena_settings',
		'sidrena_cijena_rule_config',
		'sidrena_cijena_rule_config_history',
		'sidrena_cijena_db_version',
		'sidrena_cijena_last_generation',
		'sidrena_cijena_cache_version',
		'sidrena_cijena_activation_redirect',
		'sidrena_cijena_welcome_dismissed',
		'sidrena_cijena_setup_complete',
		'sidrena_cijena_delete_data_on_uninstall',
	);

	/**
	 * Whether deletion is enabled.
	 *
	 * @return bool
	 */
	public static function should_delete() {
		return (bool) get_option( self::DELETE_OPTION, false );
	}

	/**
	 * Run the opt-in cleanup.
	 *
	 * @return array Summary of what was removed.
	 */
	public static function run() {
		$summary = array(
			'deleted' => false,
			'tables'  => array(),
			'options' => array(),
			'posts'   => 0,
			'files'   => 0,
		);

		if ( ! self::should_delete() ) {
			return $summary;
		}

		$summary['deleted']   = true;
		$summary['pruned']    = self::prune_price_history();
		$summary['tables']    = self::drop_tables();
		$summary['preserved'] = array( self::history_table() );
		$summary['options']   = self::delete_options();
		$summary['posts']     = self::delete_standalone_items();
		$summary['files']     = self::delete_publication_files();

		return $summary;
	}

	/**
	 * Plugin table that holds the price history.
	 *
	 * @return string
	 */
	private static function history_table() {
		global $wpdb;

		return $wpdb->prefix . 'sidrena_price_history';
	}

	/**
	 * Delete ordinary price history, keep the legal evidence.
	 *
	 * @return int Number of rows deleted.
	 */
	private static function prune_price_history() {
		global $wpdb;

		$table        = self::history_table();
		$placeholders = implode( ', ', array_fill( 0, count( self::EVIDENCE_TYPES ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared -- The table name is built from the trusted WordPress prefix and the placeholder list from EVIDENCE_TYPES.
		$sql = $wpdb->prepare( "DELETE FROM `{$table}` WHERE price_type NOT IN ({$placeholders})", self::EVIDENCE_TYPES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $sql );
	}

	/**
	 * Drop the plugin's custom tables, except the price-history evidence.
	 *
	 * @return string[] Table names dropped.
	 */
	private static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'sidrena_special_sales',
			$wpdb->prefix . 'sidrena_locations',
			$wpdb->prefix . 'sidrena_publications',
			$wpdb->prefix . 'sidrena_logs',
		);

		$dropped = array();
		foreach ( $tables as $table ) {
			// Table names are built from the trusted WordPress prefix.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

			$dropped[] = $table;
		}

		return $dropped;
	}

	/**
	 * Delete plugin options.
	 *
	 * @return string[] Option names deleted.
	 */
	private static function delete_options() {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}

		return self::$options;
	}

	/**
	 * Delete standalone catalog items and their terms.
	 *
	 * @return int Number of posts deleted.
	 */
	private static function delete_standalone_items() {
		$deleted = 0;

		$posts = get_posts(
			array(
				'post_type'        => 'sidrena_item',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post_id ) {
			if ( wp_delete_post( (int) $post_id, true ) ) {
				++$deleted;
			}
		}

		foreach ( array( 'sidrena_brand', 'sidrena_item_type' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}

		return $deleted;
	}

	/**
	 * Delete generated publication files and their directory.
	 *
	 * @return int Number of files deleted.
	 */
	private static function delete_publication_files() {
		$uploads = wp_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return 0;
		}

		$base = trailingslashit( $uploads['basedir'] ) . 'sidrena-cijena';

		return self::delete_directory( $base );
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * Only paths inside the plugin's own uploads directory are ever passed.
	 *
	 * @param string $directory Absolute directory path.
	 * @return int Number of files deleted.
	 */
	private static function delete_directory( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$deleted = 0;
		$items   = scandir( $directory );

		if ( false === $items ) {
			return 0;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . '/' . $item;

			if ( is_dir( $path ) ) {
				$deleted += self::delete_directory( $path );
			} elseif ( is_file( $path ) ) {
				wp_delete_file( $path );
				++$deleted;
			}
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( $directory );

		return $deleted;
	}
}
