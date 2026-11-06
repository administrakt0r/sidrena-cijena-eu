<?php
namespace SidrenaCijena\Admin;

class SettingsPage {
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}
	}
}
