<?php
/**
 * Price history import screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * CSV import with preview, validation and dry run.
 */
class ImportPage {

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
		echo '<h1>' . esc_html__( 'Sidrena cijena: Import', 'sidrena-cijena' ) . '</h1>';
		echo '</div>';
	}
}
