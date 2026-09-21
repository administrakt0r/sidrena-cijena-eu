<?php
/**
 * Database schema for the plugin's custom tables.
 *
 * Tables follow `specs/001-sidrena-cijena-compliance/data-model.md` exactly.
 *
 * @package SidrenaCijena\Database
 */

namespace SidrenaCijena\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Declares and creates the five plugin tables through dbDelta().
 */
final class Schema {

	/**
	 * Current schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Table suffixes relative to the WordPress table prefix.
	 *
	 * @return string[]
	 */
	public static function suffixes() {
		return array(
			'price_history' => 'sidrena_price_history',
			'special_sales' => 'sidrena_special_sales',
			'locations'     => 'sidrena_locations',
			'publications'  => 'sidrena_publications',
			'logs'          => 'sidrena_logs',
		);
	}

	/**
	 * Fully qualified table names.
	 *
	 * @return array<string,string>
	 */
	public static function tables() {
		global $wpdb;

		$tables = array();

		foreach ( self::suffixes() as $key => $suffix ) {
			$tables[ $key ] = $wpdb->prefix . $suffix;
		}

		return $tables;
	}

	/**
	 * Fully qualified table name for a single key.
	 *
	 * @param string $key Table key.
	 * @return string
	 */
	public static function table( $key ) {
		$tables = self::tables();

		return isset( $tables[ $key ] ) ? $tables[ $key ] : '';
	}

	/**
	 * Create or update all tables using dbDelta().
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::sql() as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * CREATE TABLE statements consumed by dbDelta().
	 *
	 * @return string[]
	 */
	public static function sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::tables();

		$statements = array();

		$statements[] = "CREATE TABLE {$tables['price_history']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned DEFAULT NULL,
			item_type varchar(20) NOT NULL DEFAULT 'product',
			sku varchar(191) DEFAULT NULL,
			price_type varchar(20) NOT NULL,
			price decimal(20,6) DEFAULT NULL,
			currency char(3) NOT NULL DEFAULT 'EUR',
			tax_inclusive tinyint(1) NOT NULL DEFAULT 1,
			valid_from datetime DEFAULT NULL,
			valid_to datetime DEFAULT NULL,
			reference_date date DEFAULT NULL,
			source varchar(50) NOT NULL,
			special_sale_type varchar(50) DEFAULT NULL,
			correction_of bigint(20) unsigned DEFAULT NULL,
			correction_reason text,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			metadata longtext,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY variation_id (variation_id),
			KEY sku (sku),
			KEY price_type (price_type),
			KEY valid_from (valid_from),
			KEY reference_date (reference_date),
			KEY product_variation_type (product_id,variation_id,price_type),
			KEY product_type_reference (product_id,price_type,reference_date)
		) {$charset_collate};";

		$statements[] = "CREATE TABLE {$tables['special_sales']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned DEFAULT NULL,
			sale_type varchar(50) NOT NULL,
			sale_name varchar(191) DEFAULT NULL,
			start_date datetime NOT NULL,
			end_date datetime DEFAULT NULL,
			promo_price decimal(20,6) DEFAULT NULL,
			lowest_30_day_price decimal(20,6) DEFAULT NULL,
			window_start date DEFAULT NULL,
			window_end date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			metadata longtext,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY product_variation (product_id,variation_id),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY status (status),
			KEY sale_type (sale_type)
		) {$charset_collate};";

		$statements[] = "CREATE TABLE {$tables['locations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			type varchar(50) NOT NULL,
			address varchar(255) DEFAULT NULL,
			city varchar(191) DEFAULT NULL,
			identifier varchar(50) NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			applies_to longtext,
			price_list_config longtext,
			sequence_no int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY identifier (identifier),
			KEY active (active),
			KEY type (type)
		) {$charset_collate};";

		$statements[] = "CREATE TABLE {$tables['publications']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_id bigint(20) unsigned NOT NULL,
			format varchar(10) NOT NULL,
			filename varchar(255) NOT NULL,
			path varchar(500) NOT NULL,
			url varchar(500) NOT NULL,
			checksum char(64) NOT NULL,
			content_fingerprint char(64) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			rule_version varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			sequence_no int(10) unsigned NOT NULL,
			item_count int(10) unsigned NOT NULL DEFAULT 0,
			validation_summary longtext,
			created_at datetime NOT NULL,
			published_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			expired_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY location_id (location_id),
			KEY format (format),
			KEY status (status),
			KEY published_at (published_at),
			KEY expires_at (expires_at),
			KEY checksum (checksum),
			KEY content_fingerprint (content_fingerprint),
			KEY filename (filename)
		) {$charset_collate};";

		$statements[] = "CREATE TABLE {$tables['logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(10) NOT NULL,
			event varchar(50) NOT NULL,
			message text NOT NULL,
			context longtext,
			user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY event (event),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) {$charset_collate};";

		return $statements;
	}
}
