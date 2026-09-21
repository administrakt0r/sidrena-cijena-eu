<?php
/**
 * Capability model.
 *
 * One capability tier covers every compliance operation (clarification Q2):
 * administrators hold it by default and it can be granted to other roles.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Capability helpers.
 */
final class Capabilities {

	/**
	 * Base capability name.
	 */
	const CAPABILITY = 'manage_sidrena_cijena';

	/**
	 * Effective capability, filterable by site owners.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required for compliance operations.
		 *
		 * @param string $capability Capability name.
		 */
		return (string) apply_filters( 'sidrena_cijena_capability', self::CAPABILITY );
	}

	/**
	 * Whether the current user may perform compliance operations.
	 *
	 * @return bool
	 */
	public static function current_user_can() {
		return current_user_can( self::capability() );
	}

	/**
	 * Grant the capability to administrators.
	 *
	 * @return void
	 */
	public static function grant() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( self::CAPABILITY );
		}
	}

	/**
	 * Remove the capability from administrators.
	 *
	 * @return void
	 */
	public static function revoke() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->remove_cap( self::CAPABILITY );
		}
	}
}
