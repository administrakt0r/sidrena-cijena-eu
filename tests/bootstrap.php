<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests run without WordPress: a small set of WordPress function stubs is
 * provided so that domain classes remain testable in isolation. Integration
 * tests bootstrap the real WordPress test suite when the `integration` suite is
 * requested.
 *
 * @package SidrenaCijena\Tests
 */

$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
} else {
	require_once dirname( __DIR__ ) . '/src/Support/Autoloader.php';
	\SidrenaCijena\Support\Autoloader::register();
}

$sidrena_requested_suite = '';
if ( isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test-runner CLI arguments compared as plain strings.
	$sidrena_requested_suite = implode( ' ', array_map( 'strval', $_SERVER['argv'] ) );
}

if ( false !== strpos( $sidrena_requested_suite, '--testsuite=integration' ) || false !== strpos( $sidrena_requested_suite, '--testsuite integration' ) ) {
	require_once __DIR__ . '/integration/bootstrap.php';
	return;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/sidrena-cijena-wordpress/' );
}

define( 'SIDRENA_CIJENA_DIR', dirname( __DIR__ ) . '/' );
define( 'SIDRENA_CIJENA_VERSION', '1.0.0' );

require_once __DIR__ . '/unit/wp-stubs.php';
