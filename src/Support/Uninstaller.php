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

		$summary['deleted'] = true;
		$summary['tables']  = self::drop_tables();
		$summary['options'] = self::delete_options();
		$summary['posts']   = self::delete_standalone_items();
		$summary['files']   = self::delete_publication_files();

		return $summary;
	}

	/**
	 * Drop the plugin's custom tables.
	 *
	 * @return string[] Table names dropped.
	 */
	private static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'sidrena_price_history',
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
