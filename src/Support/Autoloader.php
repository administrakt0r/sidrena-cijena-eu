<?php
/**
 * Minimal PSR-4 autoloader for the SidrenaCijena namespace.
 *
 * The plugin ships no runtime Composer dependencies, so this autoloader is the
 * only class-loading mechanism in production.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Maps `SidrenaCijena\` to the plugin `src/` directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 */
	const PREFIX = 'SidrenaCijena\\';

	/**
	 * Directory (relative to the plugin root) that mirrors the namespace.
	 */
	const BASE_DIR = 'src/';

	/**
	 * Whether the autoloader has already been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register the autoloader with the SPL stack.
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		self::$registered = true;
	}

	/**
	 * Load a class file when it belongs to the plugin namespace.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', '/', $relative );

		$base = defined( 'SIDRENA_CIJENA_DIR' ) ? SIDRENA_CIJENA_DIR : dirname( __DIR__, 2 ) . '/';
		$file = $base . self::BASE_DIR . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
