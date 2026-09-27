/** Generate original directory artwork and capture the local demo UI. */
const { chromium } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

( async () => {
	const directory = path.resolve( __dirname, '../.wordpress-org' );
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage();
		for ( const [ source, width, height, output ] of [
			[ 'icon.svg', 128, 128, 'icon-128x128.png' ],
			[ 'icon.svg', 256, 256, 'icon-256x256.png' ],
			[ 'banner.svg', 772, 250, 'banner-772x250.png' ],
			[ 'banner.svg', 1544, 500, 'banner-1544x500.png' ],
		] ) {
			await page.setViewportSize( { width, height } );
			const svg = fs.readFileSync( path.join( directory, source ), 'utf8' );
			await page.setContent( `<style>body{margin:0}svg{display:block;width:100%;height:100%}</style>${ svg }` );
			await page.screenshot( { path: path.join( directory, output ) } );
		}
		const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( `${ baseUrl }/sidrena-cjenik/`, { waitUntil: 'networkidle' } );
		await page.screenshot( { path: path.join( directory, 'screenshot-1.png' ) } );
		await page.goto( `${ baseUrl }/wp-login.php` );
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await Promise.all( [ page.waitForURL( /wp-admin/ ), page.locator( '#wp-submit' ).click() ] );
		await page.goto( `${ baseUrl }/wp-admin/admin.php?page=sidrena-cijena`, { waitUntil: 'networkidle' } );
		await page.screenshot( { path: path.join( directory, 'screenshot-2.png' ) } );
		await page.goto( `${ baseUrl }/wp-admin/admin.php?page=sidrena-cijena-history`, { waitUntil: 'networkidle' } );
		await page.screenshot( { path: path.join( directory, 'screenshot-3.png' ) } );
		await page.goto( `${ baseUrl }/wp-admin/admin.php?page=sidrena-cijena-settings`, { waitUntil: 'networkidle' } );
		await page.screenshot( { path: path.join( directory, 'screenshot-4.png' ) } );
		console.log( 'Directory icons, banners and 4 screenshots generated.' );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => { console.error( error ); process.exitCode = 1; } );
