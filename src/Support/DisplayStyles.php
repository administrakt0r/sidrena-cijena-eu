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
			'tooltip'   => __( 'Tooltip / Info icon', 'titanworks-sidrena-bazna-cijena' ),
			'plain'     => __( 'Plain', 'titanworks-sidrena-bazna-cijena' ),
			'badge'     => __( 'Badge', 'titanworks-sidrena-bazna-cijena' ),
			'card'      => __( 'Card', 'titanworks-sidrena-bazna-cijena' ),
			'underline' => __( 'Underline', 'titanworks-sidrena-bazna-cijena' ),
			'outline'   => __( 'Outline', 'titanworks-sidrena-bazna-cijena' ),
			'stripe'    => __( 'Side stripe', 'titanworks-sidrena-bazna-cijena' ),
			'soft'      => __( 'Soft background', 'titanworks-sidrena-bazna-cijena' ),
			'mono'      => __( 'Monochrome', 'titanworks-sidrena-bazna-cijena' ),
			'pill'      => __( 'Pill', 'titanworks-sidrena-bazna-cijena' ),
			'minimal'   => __( 'Minimal', 'titanworks-sidrena-bazna-cijena' ),
			'tag'       => __( 'Tag', 'titanworks-sidrena-bazna-cijena' ),
			'accent'    => __( 'Accent stripe', 'titanworks-sidrena-bazna-cijena' ),
			'subtle'    => __( 'Subtle', 'titanworks-sidrena-bazna-cijena' ),
		);
	}
}
