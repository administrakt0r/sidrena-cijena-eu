<?php
namespace SidrenaCijena\Admin;

class SettingsPage {
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			exit;
		}
	}
}
