import { readdir, readFile, unlink, writeFile } from 'node:fs/promises';
import { dirname, extname, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(
	dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const buildDirectory = resolve( projectRoot, 'assets', 'build' );
const expectedPrefix = `${ projectRoot }${ sep }`;

if ( ! buildDirectory.startsWith( expectedPrefix ) ) {
	throw new Error(
		'Refusing to clean a build directory outside the project.'
	);
}

const sourceMapCommentPattern =
	/(?:\/\/[#@]\s*sourceMappingURL=.*|\/\*[#@]\s*sourceMappingURL=.*?\*\/)\s*$/gm;

async function stripSourceMaps( directory ) {
	const entries = await readdir( directory, { withFileTypes: true } );

	await Promise.all(
		entries.map( async ( entry ) => {
			const filePath = resolve( directory, entry.name );

			if ( entry.isDirectory() ) {
				await stripSourceMaps( filePath );
				return;
			}

			if ( extname( filePath ) === '.map' ) {
				await unlink( filePath );
				return;
			}

			if ( ! [ '.css', '.js' ].includes( extname( filePath ) ) ) {
				return;
			}

			const contents = await readFile( filePath, 'utf8' );
			const strippedContents = contents.replace(
				sourceMapCommentPattern,
				''
			);

			if ( strippedContents !== contents ) {
				await writeFile( filePath, strippedContents, 'utf8' );
			}
		} )
	);
}

await stripSourceMaps( buildDirectory );
