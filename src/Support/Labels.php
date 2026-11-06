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
			'regular'       => __( 'Regular price', 'titanworks-bazna-cijena' ),
			'sale'          => __( 'Sale price', 'titanworks-bazna-cijena' ),
			'anchor'        => __( 'Anchor price', 'titanworks-bazna-cijena' ),
			'lowest_30_day' => __( 'Lowest price in the last 30 days', 'titanworks-bazna-cijena' ),
			'historical'    => __( 'Historical price', 'titanworks-bazna-cijena' ),
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
			'standalone'           => __( 'Standalone catalog', 'titanworks-bazna-cijena' ),
			'manual'               => __( 'Manual entry', 'titanworks-bazna-cijena' ),
			'bulk'                 => __( 'Bulk entry', 'titanworks-bazna-cijena' ),
			'import'               => __( 'CSV import', 'titanworks-bazna-cijena' ),
			'cli'                  => 'WP-CLI',
			'migration'            => __( 'Migration', 'titanworks-bazna-cijena' ),
			'system'               => __( 'System', 'titanworks-bazna-cijena' ),
			'observed_first_offer' => __( 'Observed first offer', 'titanworks-bazna-cijena' ),
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
			'pending'    => __( 'Pending', 'titanworks-bazna-cijena' ),
			'generated'  => __( 'Generated', 'titanworks-bazna-cijena' ),
			'validated'  => __( 'Validated', 'titanworks-bazna-cijena' ),
			'published'  => __( 'Published', 'titanworks-bazna-cijena' ),
			'failed'     => __( 'Failed', 'titanworks-bazna-cijena' ),
			'superseded' => __( 'Superseded', 'titanworks-bazna-cijena' ),
			'expired'    => __( 'Expired', 'titanworks-bazna-cijena' ),
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
			'scheduled' => __( 'Scheduled', 'titanworks-bazna-cijena' ),
			'active'    => __( 'Active', 'titanworks-bazna-cijena' ),
			'ended'     => __( 'Ended', 'titanworks-bazna-cijena' ),
			'cancelled' => __( 'Cancelled', 'titanworks-bazna-cijena' ),
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
			'info'    => __( 'Info', 'titanworks-bazna-cijena' ),
			'warning' => __( 'Warning', 'titanworks-bazna-cijena' ),
			'error'   => __( 'Error', 'titanworks-bazna-cijena' ),
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
			'export_scheduled'   => __( 'Scheduled generation', 'titanworks-bazna-cijena' ),
			'export_manual'      => __( 'Manual generation', 'titanworks-bazna-cijena' ),
			'export_failed'      => __( 'Failed generation', 'titanworks-bazna-cijena' ),
			'import'             => __( 'Import', 'titanworks-bazna-cijena' ),
			'validation_failure' => __( 'Validation failure', 'titanworks-bazna-cijena' ),
			'history_correction' => __( 'History correction', 'titanworks-bazna-cijena' ),
			'migration'          => __( 'Migration', 'titanworks-bazna-cijena' ),
			'settings_change'    => __( 'Settings change', 'titanworks-bazna-cijena' ),
			'cleanup'            => __( 'Cleanup', 'titanworks-bazna-cijena' ),
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
			'ERROR'   => __( 'Error', 'titanworks-bazna-cijena' ),
			'WARNING' => __( 'Warning', 'titanworks-bazna-cijena' ),
			'INFO'    => __( 'Info', 'titanworks-bazna-cijena' ),
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
			'catalog_item' => __( 'Catalog item', 'titanworks-bazna-cijena' ),
			'location'     => __( 'Sales location', 'titanworks-bazna-cijena' ),
			'publication'  => __( 'Publication', 'titanworks-bazna-cijena' ),
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
			'akcija'            => __( 'Promotion (akcija)', 'titanworks-bazna-cijena' ),
			'snizenje'          => __( 'Price reduction (snizenje)', 'titanworks-bazna-cijena' ),
			'sezonsko_snizenje' => __( 'Seasonal reduction (sezonsko_snizenje)', 'titanworks-bazna-cijena' ),
			'rasprodaja'        => __( 'Clearance (rasprodaja)', 'titanworks-bazna-cijena' ),
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
			'prodavaonica'   => __( 'Retail store (prodavaonica)', 'titanworks-bazna-cijena' ),
			'webshop'        => __( 'Web shop (webshop)', 'titanworks-bazna-cijena' ),
			'poslovnica'     => __( 'Branch office (poslovnica)', 'titanworks-bazna-cijena' ),
			'usluzno_mjesto' => __( 'Service location (uslužno mjesto)', 'titanworks-bazna-cijena' ),
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
			'scheduled' => __( 'Scheduled run', 'titanworks-bazna-cijena' ),
			'manual'    => __( 'Manual run', 'titanworks-bazna-cijena' ),
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
			'general_reference_date' => __( 'General reference date', 'titanworks-bazna-cijena' ),
			'legacy_reference_date'  => __( 'Legacy reference date', 'titanworks-bazna-cijena' ),
			'legacy_enabled'         => __( 'Enable legacy treatment', 'titanworks-bazna-cijena' ),
			'legacy_category_ids'    => __( 'Legacy categories', 'titanworks-bazna-cijena' ),
			'legacy_category_slugs'  => __( 'Legacy categories', 'titanworks-bazna-cijena' ),
			'rule_version'           => __( 'Legal-rule version', 'titanworks-bazna-cijena' ),
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
			'valid'     => __( 'Valid', 'titanworks-bazna-cijena' ),
			'invalid'   => __( 'Invalid', 'titanworks-bazna-cijena' ),
			'duplicate' => __( 'Duplicate', 'titanworks-bazna-cijena' ),
			'overwrite' => __( 'Will overwrite', 'titanworks-bazna-cijena' ),
			'unchanged' => __( 'Unchanged', 'titanworks-bazna-cijena' ),
		);

		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
