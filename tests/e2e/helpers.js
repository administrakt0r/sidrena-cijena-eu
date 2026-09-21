/**
 * Playwright helpers for the wp-env WordPress instance.
 */
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );

/**
 * Run a WP-CLI command inside the wp-env CLI container.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Command output.
 */
function wpCli( args ) {
	const bin = process.platform === 'win32' ? 'npx.cmd' : 'npx';

	return execFileSync( bin, [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		cwd: REPO_ROOT,
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
}

/**
 * Evaluate PHP inside WordPress.
 *
 * @param {string} code PHP code without the opening tag.
 * @return {string} Command output.
 */
function wpEval( code ) {
	return wpCli( [ 'eval', code ] );
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
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

module.exports = { wpCli, wpEval, login, REPO_ROOT };
