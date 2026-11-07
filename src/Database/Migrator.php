<?php
/**
 * Versioned schema migrations.
 *
 * @package Titanworks\SidrenaCijena\Database
 */

namespace Titanworks\SidrenaCijena\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Runs dbDelta() migrations when the stored schema version is behind.
 */
final class Migrator {

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION = 'twbce_cijena_db_version';

	/**
	 * Run the upgrade check on admin requests.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::installed_version() !== Schema::DB_VERSION ) {
			self::migrate();
		}
	}

	/**
	 * Installed schema version.
	 *
	 * @return string
	 */
	public static function installed_version() {
		return (string) get_option( self::OPTION, '' );
	}

	/**
	 * Run migrations up to the current schema version.
	 *
	 * @return string The version now installed.
	 */
	public static function migrate() {
		$from = self::installed_version();
		$to   = Schema::DB_VERSION;

		if ( $from === $to ) {
			return $to;
		}

		Schema::create_tables();
		update_option( self::OPTION, $to, false );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		/**
		 * Fires after the schema has been migrated.
		 *
		 * @param string $from Previous version.
		 * @param string $to   New version.
		 */
		do_action( 'twbce_cijena_migrated', $from, $to );

		return $to;
	}
}
