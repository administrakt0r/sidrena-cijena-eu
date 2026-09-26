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
			'plain'     => __( 'Plain', 'sidrena-cijena-eu' ),
			'badge'     => __( 'Badge', 'sidrena-cijena-eu' ),
			'card'      => __( 'Card', 'sidrena-cijena-eu' ),
			'underline' => __( 'Underline', 'sidrena-cijena-eu' ),
			'outline'   => __( 'Outline', 'sidrena-cijena-eu' ),
			'stripe'    => __( 'Side stripe', 'sidrena-cijena-eu' ),
			'soft'      => __( 'Soft background', 'sidrena-cijena-eu' ),
			'mono'      => __( 'Monochrome', 'sidrena-cijena-eu' ),
			'pill'      => __( 'Pill', 'sidrena-cijena-eu' ),
		);
	}
}
