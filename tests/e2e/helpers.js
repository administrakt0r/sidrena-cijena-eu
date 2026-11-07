/**
 * Playwright helpers for the wp-env WordPress instance.
 */
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );

/**
 * wp-env CLI service the suite talks to. The specs create and delete data, so
 * they run against the test environment (port 8889), never the demo shop.
 *
 * @type {string}
 */
const WP_ENV_CLI = process.env.WP_ENV_CLI || 'tests-cli';

/**
 * Run a WP-CLI command inside the wp-env CLI container.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Command output.
 */
let cachedPluginSlug = null;

function runWpCli( args ) {
	const options = { cwd: REPO_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] };
	if ( process.env.TWBCE_WP_CLI_CONTAINER ) {
		return execFileSync( 'docker', [
			'exec', '--workdir', '/var/www/html',
			'--user', `${ process.getuid() }:${ process.getgid() }`,
			process.env.TWBCE_WP_CLI_CONTAINER, 'wp', ...args,
		], options );
	}
	return execFileSync( process.execPath, [ path.join( path.dirname( require.resolve( '@wordpress/env/package.json' ) ), 'bin/wp-env' ), 'run', WP_ENV_CLI, 'wp', ...args ], options );
}

function wpCli( args ) {
	if ( args[ 0 ] === 'plugin' && [ 'activate', 'deactivate', 'is-active' ].includes( args[ 1 ] ) ) {
		const aliases = [ 'sidrena-cijena', 'sidrena-cijena-eu', 'titanworks-bazna-cijena' ];
		const index = args.findIndex( ( value ) => aliases.includes( value ) );
		if ( index !== -1 ) {
			if ( ! cachedPluginSlug ) {
				const names = runWpCli( [ 'plugin', 'list', '--field=name' ] ).split( '\n' ).map( ( name ) => name.trim() );
				cachedPluginSlug = names.find( ( name ) => aliases.includes( name ) ) || names.find( ( name ) => /^titanworks.*(?:sidrena|bazna)/.test( name ) ) || args[ index ];
			}
			args = [ ...args ];
			args[ index ] = cachedPluginSlug;
		}
	}
	return runWpCli( args );
}

/**
 * Evaluate PHP inside WordPress.
 *
 * @param {string} code PHP code without the opening tag.
 * @return {string} Command output.
 */
function wpEval( code ) {
	// Existing fixtures model imported historical catalogs, not witnessed first offers.
	return wpCli( [ 'eval', 'define("WP_IMPORTING", true); ' + code ] );
}

/**
 * Log in to wp-admin.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} username Username.
 * @param {string} password Password.
 * @return {Promise<void>}
 */
async function login( page, username = 'admin', password = 'password' ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );

	// The login form occasionally resets the focused password input while
	// wp-login scripts initialize. Confirm both values before submitting.
	if ( ( await page.locator( '#user_login' ).inputValue() ) !== username || ( await page.locator( '#user_pass' ).inputValue() ) !== password ) {
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
	}

	await page.click( '#wp-submit' );

	// A subscriber is not sent to wp-admin: WooCommerce lands them on their
	// account page, so the only reliable sign of a finished login is that
	// wp-login.php is no longer the current URL.
	await page.waitForURL( ( url ) => ! url.pathname.includes( 'wp-login.php' ) );
}

let cachedPluginPath = null;

/**
 * Resolve the absolute path of the plugin (or a file inside it) inside the wp-env container.
 *
 * @param {string} [relPath] Path relative to the plugin root.
 * @return {string} Absolute path inside the container.
 */
function getContainerPluginPath( relPath = '' ) {
	if ( ! cachedPluginPath ) {
		try {
			const res = wpCli( [
				'eval',
				'$m = array_merge( glob( WP_PLUGIN_DIR . "/*/titanworks-bazna-cijena.php" ) ?: array(), glob( WP_PLUGIN_DIR . "/*/titanworks-bazna-cijena.php" ) ?: array(), glob( WP_PLUGIN_DIR . "/*/sidrena-cijena.php" ) ?: array() ); echo ! empty( $m ) ? dirname( $m[0] ) : ( WP_PLUGIN_DIR . "/titanworks-bazna-cijena" );',
			] );
			cachedPluginPath = res.trim();
		} catch {
			cachedPluginPath = '/var/www/html/wp-content/plugins/titanworks-bazna-cijena';
		}
	}
	return relPath ? `${ cachedPluginPath }/${ relPath.replace( /^\/+/, '' ) }` : cachedPluginPath;
}

module.exports = { wpCli, wpEval, login, getContainerPluginPath, REPO_ROOT, WP_ENV_CLI };
