<?php
namespace SidrenaCijena\Admin;

class CompliancePage {
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'Access denied' );
		}
	}
}
