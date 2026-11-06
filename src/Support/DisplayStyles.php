<?php
/**
 * Anchor display presets.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Shared style allowlist and labels.
 */
final class DisplayStyles {
	/**
	 * Available presets.
	 *
	 * @return array<string,string>
	 */
	public static function presets() {
		return array(
			'tooltip'   => __( 'Tooltip / Info icon', 'titanworks-bazna-cijena' ),
			'plain'     => __( 'Plain', 'titanworks-bazna-cijena' ),
			'badge'     => __( 'Badge', 'titanworks-bazna-cijena' ),
			'card'      => __( 'Card', 'titanworks-bazna-cijena' ),
			'underline' => __( 'Underline', 'titanworks-bazna-cijena' ),
			'outline'   => __( 'Outline', 'titanworks-bazna-cijena' ),
			'stripe'    => __( 'Side stripe', 'titanworks-bazna-cijena' ),
			'soft'      => __( 'Soft background', 'titanworks-bazna-cijena' ),
			'mono'      => __( 'Monochrome', 'titanworks-bazna-cijena' ),
			'pill'      => __( 'Pill', 'titanworks-bazna-cijena' ),
			'minimal'   => __( 'Minimal', 'titanworks-bazna-cijena' ),
			'tag'       => __( 'Tag', 'titanworks-bazna-cijena' ),
			'accent'    => __( 'Accent stripe', 'titanworks-bazna-cijena' ),
			'subtle'    => __( 'Subtle', 'titanworks-bazna-cijena' ),
		);
	}

	/**
	 * Card-specific style presets, including dynamic inheritance from single product page.
	 *
	 * @return array<string,string>
	 */
	public static function card_presets() {
		return array_merge(
			array(
				'same_as_product' => __( 'Same as product page', 'titanworks-bazna-cijena' ),
			),
			self::presets()
		);
	}
}
