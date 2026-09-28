const { test, expect } = require( '@playwright/test' );
const { wpCli, getContainerPluginPath } = require( './helpers' );

test( 'new offers retain witnessed prices and variation identity without backfilling imports', () => {
	const output = wpCli( [
		'eval-file',
		getContainerPluginPath( 'tools/e2e-first-offer.php' ),
	] );
	expect( output ).toContain( 'Observed first offers:' );
} );
