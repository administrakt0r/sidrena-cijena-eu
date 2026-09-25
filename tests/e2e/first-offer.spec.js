const { test, expect } = require( '@playwright/test' );
const { wpCli } = require( './helpers' );

test( 'new offers retain witnessed prices and variation identity without backfilling imports', () => {
	const output = wpCli( [
		'eval-file',
		'/var/www/html/wp-content/plugins/sidrena-cijena/tools/e2e-first-offer.php',
	] );
	expect( output ).toContain( 'Observed first offers:' );
} );
