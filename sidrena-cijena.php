<?php
/**
 * Plugin Name:       Sidrena Cijena EU
 * Plugin URI:        https://github.com/administrakt0r/sidrena-cijena-eu
 * Description:       Records, displays, validates and publishes the Croatian anchor price ("sidrena cijena"), the daily machine-readable price list and the 30-day lowest price for special forms of sale. A technical compliance aid, not legal advice.
 * Version:           2.0.20
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            titanworks
 * Author URI:        https://github.com/administrakt0r
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidrena-cijena-eu
 * Domain Path:       /languages
 *
 * @package SidrenaCijena
 */

defined( 'ABSPATH' ) || exit;

defined( 'SIDRENA_CIJENA_VERSION' ) || define( 'SIDRENA_CIJENA_VERSION', '2.0.20' );
defined( 'SIDRENA_CIJENA_FILE' ) || define( 'SIDRENA_CIJENA_FILE', __FILE__ );
defined( 'SIDRENA_CIJENA_DIR' ) || define( 'SIDRENA_CIJENA_DIR', plugin_dir_path( __FILE__ ) );
defined( 'SIDRENA_CIJENA_URL' ) || define( 'SIDRENA_CIJENA_URL', plugin_dir_url( __FILE__ ) );
defined( 'SIDRENA_CIJENA_BASENAME' ) || define( 'SIDRENA_CIJENA_BASENAME', plugin_basename( __FILE__ ) );

require_once SIDRENA_CIJENA_DIR . 'src/Support/Autoloader.php';
require_once SIDRENA_CIJENA_DIR . 'src/Api/functions.php';
require_once SIDRENA_CIJENA_DIR . 'src/Api/global-functions.php';
\SidrenaCijena\Support\Autoloader::register();

register_activation_hook( __FILE__, array( 'SidrenaCijena\Support\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SidrenaCijena\Support\Deactivator', 'deactivate' ) );

/**
 * Load the plugin translations.
 *
 * Loads the bundled Croatian translation catalogue for Croatian locales,
 * and falls back to standard WordPress textdomain resolution for other
 * locales. Supports filtering to allow forcing or skipping Croatian catalog.
 *
 * @return void
 */
function sidrena_cijena_load_textdomain() {
	if ( function_exists( 'is_textdomain_loaded' ) && is_textdomain_loaded( 'sidrena-cijena-eu' ) ) {
		return;
	}

	$locale = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'hr_HR' );

	/**
	 * Filters whether to load the bundled Croatian translation catalog.
	 *
	 * Defaults to true for Croatian locales (hr, hr_HR, hr_BA), false otherwise.
	 *
	 * @param bool   $load   Whether to load the Croatian catalog.
	 * @param string $locale Active locale.
	 */
	$load_croatian = apply_filters( 'sidrena_cijena_load_croatian_catalog', 0 === strpos( (string) $locale, 'hr' ), $locale );

	$candidates = array(
		SIDRENA_CIJENA_DIR . 'languages/sidrena-cijena-eu-hr.mo',
		SIDRENA_CIJENA_DIR . 'languages/sidrena-cijena-eu-hr_HR.mo',
	);

	if ( $load_croatian || 0 === strpos( (string) $locale, 'hr' ) ) {
		foreach ( $candidates as $bundled_mo ) {
			if ( file_exists( $bundled_mo ) ) {
				load_textdomain( 'sidrena-cijena-eu', $bundled_mo );
				break;
			}
		}
	}
}
add_action( 'init', 'sidrena_cijena_load_textdomain', 1 );

/**
 * Declare WooCommerce compatibility before WooCommerce loads its features.
 *
 * The plugin keeps its own tables and never reads or writes orders, so High
 * Performance Order Storage changes nothing for it. Declaring it explicitly
 * keeps WooCommerce from flagging the plugin as untested/incompatible.
 *
 * @return void
 */
function sidrena_cijena_declare_woocommerce_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SIDRENA_CIJENA_FILE, true );
}

add_action( 'before_woocommerce_init', 'sidrena_cijena_declare_woocommerce_compatibility' );

add_action( 'plugins_loaded', array( 'SidrenaCijena\Plugin', 'boot' ), 20 );
