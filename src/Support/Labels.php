<?php
/**
 * Display labels for stored vocabulary.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Translates the stored status, level, event, severity and type slugs for display.
 *
 * The slugs stay the stored values; only their presentation is localized so
 * admin screens read naturally in Croatian and never show raw machine values.
 */
final class Labels {

	/**
	 * Translated label for a price type slug.
	 *
	 * @param string $type Price type slug (`regular`, `sale`, `anchor`, …).
	 * @return string
	 */
	public static function price_type( $type ) {
		$labels = array(
			'regular'       => __( 'Regular price', 'sidrena-cijena-eu' ),
			'sale'          => __( 'Sale price', 'sidrena-cijena-eu' ),
			'anchor'        => __( 'Anchor price', 'sidrena-cijena-eu' ),
			'lowest_30_day' => __( 'Lowest price in the last 30 days', 'sidrena-cijena-eu' ),
			'historical'    => __( 'Historical price', 'sidrena-cijena-eu' ),
		);

		$type = (string) $type;

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Translated label for a record source slug.
	 *
	 * @param string $source Source slug (`woocommerce`, `manual`, `import`, …).
	 * @return string
	 */
	public static function source( $source ) {
		$labels = array(
			'woocommerce'          => 'WooCommerce',
			'standalone'           => __( 'Standalone catalog', 'sidrena-cijena-eu' ),
			'manual'               => __( 'Manual entry', 'sidrena-cijena-eu' ),
			'bulk'                 => __( 'Bulk entry', 'sidrena-cijena-eu' ),
			'import'               => __( 'CSV import', 'sidrena-cijena-eu' ),
			'cli'                  => 'WP-CLI',
			'migration'            => __( 'Migration', 'sidrena-cijena-eu' ),
			'system'               => __( 'System', 'sidrena-cijena-eu' ),
			'observed_first_offer' => __( 'Observed first offer', 'sidrena-cijena-eu' ),
		);

		$source = (string) $source;

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}

	/**
	 * Translated label for a publication status slug.
	 *
	 * @param string $status Publication status slug.
	 * @return string
	 */
	public static function publication_status( $status ) {
		$labels = array(
			'pending'    => __( 'Pending', 'sidrena-cijena-eu' ),
			'generated'  => __( 'Generated', 'sidrena-cijena-eu' ),
			'validated'  => __( 'Validated', 'sidrena-cijena-eu' ),
			'published'  => __( 'Published', 'sidrena-cijena-eu' ),
			'failed'     => __( 'Failed', 'sidrena-cijena-eu' ),
			'superseded' => __( 'Superseded', 'sidrena-cijena-eu' ),
			'expired'    => __( 'Expired', 'sidrena-cijena-eu' ),
		);

		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Translated label for a special-sale status slug.
	 *
	 * @param string $status Special-sale status slug.
	 * @return string
	 */
	public static function sale_status( $status ) {
		$labels = array(
			'scheduled' => __( 'Scheduled', 'sidrena-cijena-eu' ),
			'active'    => __( 'Active', 'sidrena-cijena-eu' ),
			'ended'     => __( 'Ended', 'sidrena-cijena-eu' ),
			'cancelled' => __( 'Cancelled', 'sidrena-cijena-eu' ),
		);

		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Translated label for a log level slug.
	 *
	 * @param string $level Log level slug (`info`, `warning`, `error`).
	 * @return string
	 */
	public static function log_level( $level ) {
		$labels = array(
			'info'    => __( 'Info', 'sidrena-cijena-eu' ),
			'warning' => __( 'Warning', 'sidrena-cijena-eu' ),
			'error'   => __( 'Error', 'sidrena-cijena-eu' ),
		);

		$level = (string) $level;

		return isset( $labels[ $level ] ) ? $labels[ $level ] : $level;
	}

	/**
	 * Translated label for a log event slug.
	 *
	 * @param string $event Log event slug.
	 * @return string
	 */
	public static function log_event( $event ) {
		$labels = array(
			'export_scheduled'   => __( 'Scheduled generation', 'sidrena-cijena-eu' ),
			'export_manual'      => __( 'Manual generation', 'sidrena-cijena-eu' ),
			'export_failed'      => __( 'Failed generation', 'sidrena-cijena-eu' ),
			'import'             => __( 'Import', 'sidrena-cijena-eu' ),
			'validation_failure' => __( 'Validation failure', 'sidrena-cijena-eu' ),
			'history_correction' => __( 'History correction', 'sidrena-cijena-eu' ),
			'migration'          => __( 'Migration', 'sidrena-cijena-eu' ),
			'settings_change'    => __( 'Settings change', 'sidrena-cijena-eu' ),
			'cleanup'            => __( 'Cleanup', 'sidrena-cijena-eu' ),
		);

		$event = (string) $event;

		return isset( $labels[ $event ] ) ? $labels[ $event ] : $event;
	}

	/**
	 * Translated label for a finding severity slug.
	 *
	 * @param string $severity Severity slug (`ERROR`, `WARNING`, `INFO`).
	 * @return string
	 */
	public static function severity( $severity ) {
		$labels = array(
			'ERROR'   => __( 'Error', 'sidrena-cijena-eu' ),
			'WARNING' => __( 'Warning', 'sidrena-cijena-eu' ),
			'INFO'    => __( 'Info', 'sidrena-cijena-eu' ),
		);

		$severity = (string) $severity;

		return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
	}

	/**
	 * Translated label for a finding entity type slug.
	 *
	 * @param string $type Entity type slug (`catalog_item`, `location`, `publication`).
	 * @return string
	 */
	public static function entity_type( $type ) {
		$labels = array(
			'catalog_item' => __( 'Catalog item', 'sidrena-cijena-eu' ),
			'location'     => __( 'Sales location', 'sidrena-cijena-eu' ),
			'publication'  => __( 'Publication', 'sidrena-cijena-eu' ),
		);

		$type = (string) $type;

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Translated label for a special-sale type slug.
	 *
	 * Sale types are configurable, so unknown slugs pass through unchanged.
	 *
	 * @param string $type Sale type slug.
	 * @return string
	 */
	public static function sale_type( $type ) {
		$labels = array(
			'akcija'            => __( 'Promotion (akcija)', 'sidrena-cijena-eu' ),
			'snizenje'          => __( 'Price reduction (snizenje)', 'sidrena-cijena-eu' ),
			'sezonsko_snizenje' => __( 'Seasonal reduction (sezonsko_snizenje)', 'sidrena-cijena-eu' ),
			'rasprodaja'        => __( 'Clearance (rasprodaja)', 'sidrena-cijena-eu' ),
		);

		$type = (string) $type;

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Translated label for a location type slug.
	 *
	 * @param string $type Location type slug.
	 * @return string
	 */
	public static function location_type( $type ) {
		$labels = array(
			'prodavaonica'   => __( 'Retail store (prodavaonica)', 'sidrena-cijena-eu' ),
			'webshop'        => __( 'Web shop (webshop)', 'sidrena-cijena-eu' ),
			'poslovnica'     => __( 'Branch office (poslovnica)', 'sidrena-cijena-eu' ),
			'usluzno_mjesto' => __( 'Service location (uslužno mjesto)', 'sidrena-cijena-eu' ),
		);

		$type = (string) $type;

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Translated label for a generation run source slug.
	 *
	 * @param string $source Run source slug (`scheduled`, `manual`, `cli`).
	 * @return string
	 */
	public static function run_source( $source ) {
		$labels = array(
			'scheduled' => __( 'Scheduled run', 'sidrena-cijena-eu' ),
			'manual'    => __( 'Manual run', 'sidrena-cijena-eu' ),
			'cli'       => 'WP-CLI',
		);

		$source = (string) $source;

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}

	/**
	 * Translated label for a legal-rule setting key.
	 *
	 * @param string $key Rule setting key.
	 * @return string
	 */
	public static function rule_setting( $key ) {
		$labels = array(
			'general_reference_date' => __( 'General reference date', 'sidrena-cijena-eu' ),
			'legacy_reference_date'  => __( 'Legacy reference date', 'sidrena-cijena-eu' ),
			'legacy_enabled'         => __( 'Enable legacy treatment', 'sidrena-cijena-eu' ),
			'legacy_category_ids'    => __( 'Legacy categories', 'sidrena-cijena-eu' ),
			'legacy_category_slugs'  => __( 'Legacy categories', 'sidrena-cijena-eu' ),
			'rule_version'           => __( 'Legal-rule version', 'sidrena-cijena-eu' ),
		);

		$key = (string) $key;

		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * Translated label for an import row status slug.
	 *
	 * @param string $status Import row status slug.
	 * @return string
	 */
	public static function import_status( $status ) {
		$labels = array(
			'valid'     => __( 'Valid', 'sidrena-cijena-eu' ),
			'invalid'   => __( 'Invalid', 'sidrena-cijena-eu' ),
			'duplicate' => __( 'Duplicate', 'sidrena-cijena-eu' ),
			'overwrite' => __( 'Will overwrite', 'sidrena-cijena-eu' ),
			'unchanged' => __( 'Unchanged', 'sidrena-cijena-eu' ),
		);

		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
