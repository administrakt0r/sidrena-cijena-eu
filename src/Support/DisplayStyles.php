<?php
/**
 * Anchor display presets.
 *
 * @package Titanworks\SidrenaCijena\Support
 */

namespace Titanworks\SidrenaCijena\Support;

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
			'tooltip'   => __( 'Tooltip / Info icon', 'sidrena-cijena-eu' ),
			'plain'     => __( 'Plain', 'sidrena-cijena-eu' ),
			'badge'     => __( 'Badge', 'sidrena-cijena-eu' ),
			'card'      => __( 'Card', 'sidrena-cijena-eu' ),
			'underline' => __( 'Underline', 'sidrena-cijena-eu' ),
			'outline'   => __( 'Outline', 'sidrena-cijena-eu' ),
			'stripe'    => __( 'Side stripe', 'sidrena-cijena-eu' ),
			'soft'      => __( 'Soft background', 'sidrena-cijena-eu' ),
			'mono'      => __( 'Monochrome', 'sidrena-cijena-eu' ),
			'pill'      => __( 'Pill', 'sidrena-cijena-eu' ),
			'minimal'   => __( 'Minimal', 'sidrena-cijena-eu' ),
			'tag'       => __( 'Tag', 'sidrena-cijena-eu' ),
			'accent'    => __( 'Accent stripe', 'sidrena-cijena-eu' ),
			'subtle'    => __( 'Subtle', 'sidrena-cijena-eu' ),
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
				'same_as_product' => __( 'Same as product page', 'sidrena-cijena-eu' ),
			),
			self::presets()
		);
	}
}
