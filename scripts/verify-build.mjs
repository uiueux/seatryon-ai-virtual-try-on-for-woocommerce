import { readdir, readFile } from 'node:fs/promises';
import { dirname, extname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(
	dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const buildDirectory = resolve( projectRoot, 'assets', 'build' );
const requiredFiles = [
	'admin.asset.php',
	'admin.css',
	'admin.js',
	'frontend.asset.php',
	'frontend.css',
	'frontend.js',
	'virtual-try-on-editor.asset.php',
	'virtual-try-on-editor.js',
];

async function collectFiles( directory ) {
	const entries = await readdir( directory, { withFileTypes: true } );
	const files = await Promise.all(
		entries.map( async ( entry ) => {
			const filePath = resolve( directory, entry.name );

			return entry.isDirectory() ? collectFiles( filePath ) : filePath;
		} )
	);

	return files.flat();
}

const buildFiles = await collectFiles( buildDirectory );
const relativeBuildFiles = new Set(
	buildFiles.map( ( filePath ) => relative( buildDirectory, filePath ) )
);
const missingFiles = requiredFiles.filter(
	( filePath ) => ! relativeBuildFiles.has( filePath )
);

if ( missingFiles.length > 0 ) {
	throw new Error( `Missing build outputs: ${ missingFiles.join( ', ' ) }` );
}

const sourceMapFiles = buildFiles.filter(
	( filePath ) => extname( filePath ) === '.map'
);

if ( sourceMapFiles.length > 0 ) {
	throw new Error( 'Production build contains source map files.' );
}

for ( const filePath of buildFiles ) {
	if ( ! [ '.css', '.js' ].includes( extname( filePath ) ) ) {
		continue;
	}

	const contents = await readFile( filePath, 'utf8' );

	if ( /sourceMappingURL=/.test( contents ) ) {
		throw new Error(
			`Production asset contains a source map reference: ${ relative(
				buildDirectory,
				filePath
			) }`
		);
	}
}

process.stdout.write( 'Build artifacts verified (no source maps).\n' );
