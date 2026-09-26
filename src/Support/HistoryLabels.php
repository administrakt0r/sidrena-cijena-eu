<?php
/**
 * Display labels for price history vocabulary.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Translates the price history type and source slugs for human display.
 *
 * The slugs stay the stored values; only their presentation is localized so
 * admin tables and filters read naturally in Croatian.
 */
final class HistoryLabels {

	/**
	 * Translated label for a price type slug.
	 *
	 * @param string $type Price type slug (`regular`, `sale`, `anchor`, …).
	 * @return string
	 */
	public static function type( $type ) {
		$labels = array(
			'regular'       => __( 'Regular price', 'sidrena-cijena' ),
			'sale'          => __( 'Sale price', 'sidrena-cijena' ),
			'anchor'        => __( 'Anchor price', 'sidrena-cijena' ),
			'lowest_30_day' => __( 'Lowest price in the last 30 days', 'sidrena-cijena' ),
			'historical'    => __( 'Historical price', 'sidrena-cijena' ),
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
			'standalone'           => __( 'Standalone catalog', 'sidrena-cijena' ),
			'manual'               => __( 'Manual entry', 'sidrena-cijena' ),
			'bulk'                 => __( 'Bulk entry', 'sidrena-cijena' ),
			'import'               => __( 'CSV import', 'sidrena-cijena' ),
			'cli'                  => 'WP-CLI',
			'migration'            => __( 'Migration', 'sidrena-cijena' ),
			'system'               => __( 'System', 'sidrena-cijena' ),
			'observed_first_offer' => __( 'Observed first offer', 'sidrena-cijena' ),
		);

		$source = (string) $source;

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}
}
