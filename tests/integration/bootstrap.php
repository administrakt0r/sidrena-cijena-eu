<?php
/**
 * WordPress integration test bootstrap.
 *
 * Runs inside wp-env where the WordPress test library is available at
 * `/wordpress-phpunit` (WP_TESTS_DIR). Locally it can be pointed at any
 * checkout of the WordPress test suite.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library at {$_tests_dir}. Run integration tests inside wp-env (`npm run wp-env start`).\n" );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		if ( '1' === getenv( 'SIDRENA_TEST_WOOCOMMERCE' ) ) {
			$woocommerce_plugin = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

			if ( ! file_exists( $woocommerce_plugin ) ) {
				throw new RuntimeException( 'WooCommerce is not installed in the integration-test environment.' );
			}

			require_once $woocommerce_plugin;
		}

		$plugin_bootstrap = file_exists( dirname( __DIR__, 2 ) . '/titanworks-bazna-cijena.php' )
			? dirname( __DIR__, 2 ) . '/titanworks-bazna-cijena.php'
			: ( file_exists( dirname( __DIR__, 2 ) . '/titanworks-sidrena-bazna-cijena.php' )
				? dirname( __DIR__, 2 ) . '/titanworks-sidrena-bazna-cijena.php'
				: dirname( __DIR__, 2 ) . '/sidrena-cijena.php' );
		require $plugin_bootstrap;
	}
);

require $_tests_dir . '/includes/bootstrap.php';
