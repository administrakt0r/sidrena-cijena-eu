/** Dependency keys exclude release metadata while retaining native-module ABI isolation. */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const crypto = require( 'node:crypto' );

function dependencyKey( manifest, lock, npmrc = '' ) {
	const cleanManifest = { ...manifest };
	const cleanLock = JSON.parse( JSON.stringify( lock ) );
	for ( const key of [ 'name', 'version', 'description', 'license', 'private' ] ) {
		delete cleanManifest[ key ];
	}
	delete cleanLock.name;
	delete cleanLock.version;
	if ( cleanLock.packages?.[ '' ] ) {
		delete cleanLock.packages[ '' ].name;
		delete cleanLock.packages[ '' ].version;
	}
	return crypto.createHash( 'sha256' ).update( JSON.stringify( [ cleanManifest, cleanLock, npmrc ] ) ).digest( 'hex' );
}

if ( require.main === module ) {
	const directory = path.resolve( '.circleci/cache-keys' );
	fs.mkdirSync( directory, { recursive: true } );
	const manifest = JSON.parse( fs.readFileSync( 'package.json', 'utf8' ) );
	const lock = JSON.parse( fs.readFileSync( 'package-lock.json', 'utf8' ) );
	const npmrc = fs.existsSync( '.npmrc' ) ? fs.readFileSync( '.npmrc', 'utf8' ) : '';
	fs.writeFileSync( path.join( directory, 'npm' ), dependencyKey( manifest, lock, npmrc ) );
	fs.writeFileSync( path.join( directory, 'runtime' ), `${ process.platform }-${ process.arch }-node${ process.versions.node.split( '.' )[ 0 ] }-abi${ process.versions.modules }` );
}

module.exports = { dependencyKey };
