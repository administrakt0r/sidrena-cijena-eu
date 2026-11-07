/** Lightweight regressions: no WordPress, browser, Docker daemon or third-party test runner. */
const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );
const { execFileSync, spawnSync } = require( 'node:child_process' );
const { dependencyKey } = require( './cache-keys.cjs' );
const root = path.resolve( __dirname, '../..' );

function helper( env = {}, execute = () => '' ) {
	const calls = [];
	const load = ( name ) => name === 'child_process' ? {
		execFileSync( ...args ) { calls.push( args ); return execute( ...args ); },
	} : require( name );
	load.resolve = require.resolve;
	const context = {
		require: load,
		module: { exports: {} },
		__dirname: path.join( root, 'tests/e2e' ),
		process: { env, execPath: process.execPath, getuid: () => 1001, getgid: () => 1001 },
	};
	vm.runInNewContext( fs.readFileSync( path.join( root, 'tests/e2e/helpers.js' ), 'utf8' ), context );
	return { ...context.module.exports, calls };
}

test( 'CI executes WP-CLI in the exact existing service with unchanged argument boundaries', () => {
	const api = helper( { TWBCE_WP_CLI_CONTAINER: 'tests-container' } );
	const php = 'echo "quotes & spaces; $(must_not_execute)";';
	api.wpCli( [ 'eval', php ] );
	assert.equal( api.calls.length, 1 );
	assert.equal( api.calls[ 0 ][ 0 ], 'docker' );
	assert.deepEqual( Array.from( api.calls[ 0 ][ 1 ] ), [ 'exec', '--workdir', '/var/www/html', '--user', '1001:1001', 'tests-container', 'wp', 'eval', php ] );
} );

test( 'a failed WP mutation propagates without silently replaying it', () => {
	const failure = new Error( 'command failed' );
	const api = helper( { TWBCE_WP_CLI_CONTAINER: 'tests-container' }, () => { throw failure; } );
	assert.throws( () => api.wpCli( [ 'option', 'update', 'key', 'value' ] ), ( error ) => error === failure );
	assert.equal( api.calls.length, 1 );
} );

test( 'plugin resolution is cached and unrelated plugin arguments are retained', () => {
	const api = helper( { TWBCE_WP_CLI_CONTAINER: 'tests-container' }, ( bin, args ) => args.includes( 'list' ) ? 'woocommerce\ntitanworks-bazna-cijena\n' : '' );
	api.wpCli( [ 'plugin', 'activate', 'sidrena-cijena-eu', 'woocommerce' ] );
	api.wpCli( [ 'plugin', 'is-active', 'sidrena-cijena-eu' ] );
	assert.equal( api.calls.filter( ( call ) => call[ 1 ].includes( 'list' ) ).length, 1 );
	assert.deepEqual( Array.from( api.calls[ 1 ][ 1 ].slice( -4 ) ), [ 'plugin', 'activate', 'titanworks-bazna-cijena', 'woocommerce' ] );
} );

test( 'local development folder names are resolved without requiring a directory rename', () => {
	const api = helper( {}, () => 'woocommerce\ntitanworks-sidrena-bazna-cijena-dev\n' );
	api.wpCli( [ 'plugin', 'activate', 'sidrena-cijena-eu' ] );
	assert.equal( api.calls[ 1 ][ 1 ].at( -1 ), 'titanworks-sidrena-bazna-cijena-dev' );
} );

test( 'local use keeps wp-env and avoids npm process startup', () => {
	const api = helper();
	api.wpCli( [ 'option', 'get', 'timezone_string' ] );
	assert.equal( api.calls[ 0 ][ 0 ], process.execPath );
	assert.match( api.calls[ 0 ][ 1 ][ 0 ], /[\\/]bin[\\/]wp-env$/ );
	assert.equal( api.calls[ 0 ][ 1 ][ 2 ], 'tests-cli' );
} );

test( 'release metadata does not invalidate installed dependencies; dependency/config changes do', () => {
	const manifest = { name: 'plugin', version: '1.0.0', devDependencies: { tool: '1' } };
	const lock = { name: 'plugin', version: '1.0.0', packages: { '': { name: 'plugin', version: '1.0.0' }, 'node_modules/tool': { version: '1.0.0', integrity: 'sha512-a' } } };
	const key = dependencyKey( manifest, lock );
	assert.equal( dependencyKey( { ...manifest, version: '1.0.1' }, { ...lock, version: '1.0.1', packages: { ...lock.packages, '': { name: 'plugin', version: '1.0.1' } } } ), key );
	assert.notEqual( dependencyKey( { ...manifest, devDependencies: { tool: '2' } }, lock ), key );
	assert.notEqual( dependencyKey( manifest, { ...lock, packages: { ...lock.packages, 'node_modules/tool': { version: '2.0.0' } } } ), key );
	assert.notEqual( dependencyKey( manifest, lock, 'ignore-scripts=true' ), key );
	assert.equal( lock.version, '1.0.0' );
} );

test( 'docs-only changes skip integration; runtime changes, missing bases and no diff run it', () => {
	const directory = fs.mkdtempSync( path.join( os.tmpdir(), 'twbce-ci-git-' ) );
	const git = ( ...args ) => execFileSync( 'git', args, { cwd: directory, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } ).trim();
	const run = ( base ) => spawnSync( 'bash', [ path.join( root, 'tools/ci/integration-required.sh' ), base ], { cwd: directory, encoding: 'utf8' } ).status;
	try {
		git( 'init' );
		git( 'config', 'user.name', 'CI fixture' );
		git( 'config', 'user.email', 'ci@example.invalid' );
		fs.writeFileSync( path.join( directory, 'plugin.php' ), '<?php' );
		git( 'add', '.' ); git( 'commit', '-m', 'base' );
		const base = git( 'rev-parse', 'HEAD' );
		assert.equal( run( base ), 0 );
		fs.mkdirSync( path.join( directory, 'docs' ) );
		fs.writeFileSync( path.join( directory, 'docs/with spaces.md' ), 'Documentation' );
		git( 'add', '.' ); git( 'commit', '-m', 'docs' );
		assert.equal( run( base ), 10 );
		fs.writeFileSync( path.join( directory, 'plugin.php' ), '<?php echo 1;' );
		git( 'add', '.' ); git( 'commit', '-m', 'runtime' );
		assert.equal( run( base ), 0 );
		assert.equal( run( 'missing-base' ), 0 );
	} finally {
		fs.rmSync( directory, { recursive: true, force: true } );
	}
} );
