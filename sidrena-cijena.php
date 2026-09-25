<?php
/**
 * Plugin Name:       Sidrena cijena - Croatian Anchor Price Compliance
 * Plugin URI:        https://example.com/sidrena-cijena
 * Description:       Records, displays, validates and publishes the Croatian anchor price ("sidrena cijena"), the daily machine-readable price list and the 30-day lowest price for special forms of sale. A technical compliance aid, not legal advice.
 * Version:           1.0.2
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            Sidrena cijena contributors
 * Author URI:        https://example.com/sidrena-cijena
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidrena-cijena
 * Domain Path:       /languages
 *
 * @package SidrenaCijena
 */

defined( 'ABSPATH' ) || exit;

define( 'SIDRENA_CIJENA_VERSION', '1.0.2' );
define( 'SIDRENA_CIJENA_FILE', __FILE__ );
define( 'SIDRENA_CIJENA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIDRENA_CIJENA_URL', plugin_dir_url( __FILE__ ) );
define( 'SIDRENA_CIJENA_BASENAME', plugin_basename( __FILE__ ) );

require_once SIDRENA_CIJENA_DIR . 'src/Support/Autoloader.php';
require_once SIDRENA_CIJENA_DIR . 'src/Api/functions.php';
require_once SIDRENA_CIJENA_DIR . 'src/Api/global-functions.php';
\SidrenaCijena\Support\Autoloader::register();

register_activation_hook( __FILE__, array( 'SidrenaCijena\Support\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SidrenaCijena\Support\Deactivator', 'deactivate' ) );

/**
 * Load the plugin translations.
 *
 * WordPress loads plugin translations automatically since 4.6; the explicit
 * call keeps behaviour predictable when the plugin directory is symlinked.
 *
 * @return void
 */
function sidrena_cijena_load_textdomain() {
	load_plugin_textdomain( 'sidrena-cijena', false, dirname( SIDRENA_CIJENA_BASENAME ) . '/languages' );
}
add_action( 'init', 'sidrena_cijena_load_textdomain' );

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
