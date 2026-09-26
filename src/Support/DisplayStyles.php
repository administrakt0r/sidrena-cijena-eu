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
			'plain'     => __( 'Plain', 'sidrena-cijena' ),
			'badge'     => __( 'Badge', 'sidrena-cijena' ),
			'card'      => __( 'Card', 'sidrena-cijena' ),
			'underline' => __( 'Underline', 'sidrena-cijena' ),
			'outline'   => __( 'Outline', 'sidrena-cijena' ),
			'stripe'    => __( 'Side stripe', 'sidrena-cijena' ),
			'soft'      => __( 'Soft background', 'sidrena-cijena' ),
			'mono'      => __( 'Monochrome', 'sidrena-cijena' ),
			'pill'      => __( 'Pill', 'sidrena-cijena' ),
		);
	}
}
