/** Avoid reinstalling OS packages when the machine image already has Chromium's libraries. */
const path = require( 'node:path' );
const { chromium } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );

async function probe() {
	const browser = await chromium.launch();
	await browser.close();
}

( async () => {
	try {
		await probe();
	} catch ( error ) {
		if ( ! /missing dependencies|error while loading shared libraries|Host system is missing/i.test( String( error ) ) ) {
			throw error;
		}
		execFileSync( process.execPath, [ path.join( path.dirname( require.resolve( 'playwright/package.json' ) ), 'cli.js' ), 'install-deps', 'chromium' ], { stdio: 'inherit' } );
		await probe();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
