/**
 * Prepares the wp-env test site before the specs run.
 *
 * The suite creates and deletes products, prices and locations, so it runs
 * against the test environment (port 8889). This brings that site to the same
 * baseline `./local.sh` gives the demo shop, so a spec never fails because of
 * an unconfigured permalink, currency or a shop hidden behind "coming soon".
 */
const { wpCli, getContainerPluginPath } = require( './helpers' );

/**
 * Run a WP-CLI command, tolerating a failure that does not matter.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {void}
 */
function tryWpCli( args ) {
	try {
		wpCli( args );
	} catch ( error ) {
		// Already active, already set, or a theme that is not installed: the spec
		// asserts the outcome it needs, so setup must not stop the whole run.
	}
}

module.exports = async () => {
	wpCli( [ 'rewrite', 'structure', '/%postname%/' ] );
	wpCli( [ 'option', 'update', 'timezone_string', 'Europe/Zagreb' ] );
	wpCli( [ 'option', 'update', 'woocommerce_currency', 'EUR' ] );
	wpCli( [ 'option', 'update', 'woocommerce_price_num_decimals', '2' ] );
	wpCli( [ 'option', 'update', 'woocommerce_coming_soon', 'no' ] );
	wpCli( [ 'option', 'update', 'woocommerce_store_pages_only', 'no' ] );
	tryWpCli( [ 'plugin', 'activate', 'sidrena-cijena-eu', 'woocommerce' ] );
	tryWpCli( [ 'theme', 'activate', 'twentytwentyfive' ] );

	// WooCommerce registers its product permalinks while it boots, so the rules
	// have to be rebuilt after the plugins are active: without this a product
	// URL such as /product/kava/ returns 404 and every storefront spec fails.
	wpCli( [ 'rewrite', 'flush', '--hard' ] );

	// A price-list filename needs a street and a city (FR-040); the default
	// webshop is created without one, which would make every generation fail.
	// This lives in a PHP file: wp-env rebuilds the command line before it
	// reaches the container, so PHP with quotes cannot survive `wp eval`.
	wpCli( [ 'eval-file', getContainerPluginPath( 'tools/e2e-setup.php' ) ] );
};
