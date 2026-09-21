<?php
/**
 * Published price-list archive screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Lists generated price-list publications per location and format.
 */
class PublicationsPage {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Price lists', 'sidrena-cijena' ) . '</h1>';
		echo '</div>';
	}
}
