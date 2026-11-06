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
			'regular'       => __( 'Regular price', 'titanworks-sidrena-bazna-cijena' ),
			'sale'          => __( 'Sale price', 'titanworks-sidrena-bazna-cijena' ),
			'anchor'        => __( 'Anchor price', 'titanworks-sidrena-bazna-cijena' ),
			'lowest_30_day' => __( 'Lowest price in the last 30 days', 'titanworks-sidrena-bazna-cijena' ),
			'historical'    => __( 'Historical price', 'titanworks-sidrena-bazna-cijena' ),
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
			'standalone'           => __( 'Standalone catalog', 'titanworks-sidrena-bazna-cijena' ),
			'manual'               => __( 'Manual entry', 'titanworks-sidrena-bazna-cijena' ),
			'bulk'                 => __( 'Bulk entry', 'titanworks-sidrena-bazna-cijena' ),
			'import'               => __( 'CSV import', 'titanworks-sidrena-bazna-cijena' ),
			'cli'                  => 'WP-CLI',
			'migration'            => __( 'Migration', 'titanworks-sidrena-bazna-cijena' ),
			'system'               => __( 'System', 'titanworks-sidrena-bazna-cijena' ),
			'observed_first_offer' => __( 'Observed first offer', 'titanworks-sidrena-bazna-cijena' ),
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
			'pending'    => __( 'Pending', 'titanworks-sidrena-bazna-cijena' ),
			'generated'  => __( 'Generated', 'titanworks-sidrena-bazna-cijena' ),
			'validated'  => __( 'Validated', 'titanworks-sidrena-bazna-cijena' ),
			'published'  => __( 'Published', 'titanworks-sidrena-bazna-cijena' ),
			'failed'     => __( 'Failed', 'titanworks-sidrena-bazna-cijena' ),
			'superseded' => __( 'Superseded', 'titanworks-sidrena-bazna-cijena' ),
			'expired'    => __( 'Expired', 'titanworks-sidrena-bazna-cijena' ),
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
			'scheduled' => __( 'Scheduled', 'titanworks-sidrena-bazna-cijena' ),
			'active'    => __( 'Active', 'titanworks-sidrena-bazna-cijena' ),
			'ended'     => __( 'Ended', 'titanworks-sidrena-bazna-cijena' ),
			'cancelled' => __( 'Cancelled', 'titanworks-sidrena-bazna-cijena' ),
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
			'info'    => __( 'Info', 'titanworks-sidrena-bazna-cijena' ),
			'warning' => __( 'Warning', 'titanworks-sidrena-bazna-cijena' ),
			'error'   => __( 'Error', 'titanworks-sidrena-bazna-cijena' ),
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
			'export_scheduled'   => __( 'Scheduled generation', 'titanworks-sidrena-bazna-cijena' ),
			'export_manual'      => __( 'Manual generation', 'titanworks-sidrena-bazna-cijena' ),
			'export_failed'      => __( 'Failed generation', 'titanworks-sidrena-bazna-cijena' ),
			'import'             => __( 'Import', 'titanworks-sidrena-bazna-cijena' ),
			'validation_failure' => __( 'Validation failure', 'titanworks-sidrena-bazna-cijena' ),
			'history_correction' => __( 'History correction', 'titanworks-sidrena-bazna-cijena' ),
			'migration'          => __( 'Migration', 'titanworks-sidrena-bazna-cijena' ),
			'settings_change'    => __( 'Settings change', 'titanworks-sidrena-bazna-cijena' ),
			'cleanup'            => __( 'Cleanup', 'titanworks-sidrena-bazna-cijena' ),
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
			'ERROR'   => __( 'Error', 'titanworks-sidrena-bazna-cijena' ),
			'WARNING' => __( 'Warning', 'titanworks-sidrena-bazna-cijena' ),
			'INFO'    => __( 'Info', 'titanworks-sidrena-bazna-cijena' ),
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
			'catalog_item' => __( 'Catalog item', 'titanworks-sidrena-bazna-cijena' ),
			'location'     => __( 'Sales location', 'titanworks-sidrena-bazna-cijena' ),
			'publication'  => __( 'Publication', 'titanworks-sidrena-bazna-cijena' ),
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
			'akcija'            => __( 'Promotion (akcija)', 'titanworks-sidrena-bazna-cijena' ),
			'snizenje'          => __( 'Price reduction (snizenje)', 'titanworks-sidrena-bazna-cijena' ),
			'sezonsko_snizenje' => __( 'Seasonal reduction (sezonsko_snizenje)', 'titanworks-sidrena-bazna-cijena' ),
			'rasprodaja'        => __( 'Clearance (rasprodaja)', 'titanworks-sidrena-bazna-cijena' ),
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
			'prodavaonica'   => __( 'Retail store (prodavaonica)', 'titanworks-sidrena-bazna-cijena' ),
			'webshop'        => __( 'Web shop (webshop)', 'titanworks-sidrena-bazna-cijena' ),
			'poslovnica'     => __( 'Branch office (poslovnica)', 'titanworks-sidrena-bazna-cijena' ),
			'usluzno_mjesto' => __( 'Service location (uslužno mjesto)', 'titanworks-sidrena-bazna-cijena' ),
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
			'scheduled' => __( 'Scheduled run', 'titanworks-sidrena-bazna-cijena' ),
			'manual'    => __( 'Manual run', 'titanworks-sidrena-bazna-cijena' ),
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
			'general_reference_date' => __( 'General reference date', 'titanworks-sidrena-bazna-cijena' ),
			'legacy_reference_date'  => __( 'Legacy reference date', 'titanworks-sidrena-bazna-cijena' ),
			'legacy_enabled'         => __( 'Enable legacy treatment', 'titanworks-sidrena-bazna-cijena' ),
			'legacy_category_ids'    => __( 'Legacy categories', 'titanworks-sidrena-bazna-cijena' ),
			'legacy_category_slugs'  => __( 'Legacy categories', 'titanworks-sidrena-bazna-cijena' ),
			'rule_version'           => __( 'Legal-rule version', 'titanworks-sidrena-bazna-cijena' ),
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
			'valid'     => __( 'Valid', 'titanworks-sidrena-bazna-cijena' ),
			'invalid'   => __( 'Invalid', 'titanworks-sidrena-bazna-cijena' ),
			'duplicate' => __( 'Duplicate', 'titanworks-sidrena-bazna-cijena' ),
			'overwrite' => __( 'Will overwrite', 'titanworks-sidrena-bazna-cijena' ),
			'unchanged' => __( 'Unchanged', 'titanworks-sidrena-bazna-cijena' ),
		);

		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
