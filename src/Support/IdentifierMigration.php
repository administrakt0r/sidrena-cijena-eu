<?php
/**
 * Upgrade storage identifiers from the pre-directory development builds.
 *
 * @package Titanworks\SidrenaCijena\Support
 */

namespace Titanworks\SidrenaCijena\Support;

use Titanworks\SidrenaCijena\Admin\Capabilities;
use Titanworks\SidrenaCijena\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves existing site data when adopting the unique TWBCE prefix.
 */
final class IdentifierMigration {

	const OPTION = 'twbce_identifiers_migrated';

	/**
	 * Copy options without overwriting any values already saved under new keys.
	 * Legal history tables are deliberately never renamed or rewritten.
	 *
	 * @return void
	 */
	public static function run() {
		if ( get_option( self::OPTION, false ) ) {
			return;
		}

		if ( null === get_option( 'sidrena_cijena_db_version', null ) && null === get_option( 'sidrena_cijena_settings', null ) ) {
			update_option( self::OPTION, 1, false );
			return;
		}

		foreach ( Uninstaller::options() as $option ) {
			$legacy = preg_replace( '/^twbce_/', 'sidrena_', $option );
			$value  = get_option( $legacy, null );
			if ( null !== $value && null === get_option( $option, null ) ) {
				add_option( $option, $value, '', false );
			}
		}

		global $wpdb;

		// Fixed, plugin-owned metadata keys only; do not change unrelated metadata.
		$keys = array(
			'_twbce_availability',
			'_twbce_barcode',
			'_twbce_cijena_show_in_cards',
			'_twbce_currency',
			'_twbce_first_offer_eligible',
			'_twbce_first_offer_observation',
			'_twbce_first_seen',
			'_twbce_item_type',
			'_twbce_regular_price',
			'_twbce_sale_price',
			'_twbce_sku',
			'_twbce_tax_inclusive',
			'_twbce_unit',
			'_twbce_unit_price',
		);
		foreach ( $keys as $key ) {
			$legacy = str_replace( '_twbce_', '_sidrena_', $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time indexed storage migration.
			$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET meta_key = %s WHERE meta_key = %s', $wpdb->postmeta, $key, $legacy ) );
			if ( false === $result ) {
				return;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time standalone catalog migration.
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET post_type = %s WHERE post_type = %s', $wpdb->posts, 'twbce_item', 'sidrena_item' ) );
		if ( false === $result ) {
			return;
		}
		foreach ( array( 'brand', 'item_type' ) as $taxonomy ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time taxonomy migration.
			$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET taxonomy = %s WHERE taxonomy = %s', $wpdb->term_taxonomy, 'twbce_' . $taxonomy, 'sidrena_' . $taxonomy ) );
			if ( false === $result ) {
				return;
			}
		}

		// Preserve custom role grants and user-specific grants as well as administrators.
		foreach ( wp_roles()->role_objects as $role ) {
			if ( array_key_exists( 'manage_sidrena_cijena', $role->capabilities ) ) {
				$role->add_cap( Capabilities::CAPABILITY, (bool) $role->capabilities['manage_sidrena_cijena'] );
			}
		}
		// Direct grants and denials must retain their original boolean value.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time user capability migration.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value LIKE %s',
				$wpdb->usermeta,
				$wpdb->get_blog_prefix() . 'capabilities',
				'%' . $wpdb->esc_like( '"manage_sidrena_cijena";' ) . '%'
			)
		);
		foreach ( $user_ids as $user_id ) {
			$user = new \WP_User( $user_id );
			if ( array_key_exists( 'manage_sidrena_cijena', $user->caps ) ) {
				$user->add_cap( Capabilities::CAPABILITY, (bool) $user->caps['manage_sidrena_cijena'] );
			}
		}

		Capabilities::grant();

		foreach ( array( 'daily_generation', 'service_regeneration', 'daily_cleanup', 'catchup_generation' ) as $event ) {
			wp_clear_scheduled_hook( 'sidrena_cijena_' . $event );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'sidrena_cijena_' . $event, array(), 'sidrena-cijena' );
			}
		}

		Settings::reset_cache();
		Scheduler::schedule_all();
		// Rebuild with the new query variables on init, after all rules register.
		add_action( 'init', 'flush_rewrite_rules', 99 );
		wp_cache_flush();
		update_option( self::OPTION, 1, false );
	}
}
