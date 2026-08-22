<?php
/**
 * Native filesystem implementation.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Performs the small set of filesystem operations required by storage.
 */
final class NativeFilesystem implements FilesystemInterface {
	// Native operations are required for non-public temporary files and exclusive
	// creation; WP_Filesystem may require credentials and has no exclusive write.
	// phpcs:disable WordPress.WP.AlternativeFunctions

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to resolve.
	 */
	public function real_path( string $path ): ?string {
		$resolved = realpath( $path );

		return false === $resolved ? null : $resolved;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_directory( string $path ): bool {
		return is_dir( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_writable( string $path ): bool {
		return is_writable( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_link( string $path ): bool {
		return is_link( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_file( string $path ): bool {
		return is_file( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Directory path.
	 * @phpstan-impure
	 */
	public function create_directory( string $path ): bool {
		if ( is_dir( $path ) ) {
			return true;
		}

		return mkdir( $path, 0700, true );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path     Destination path.
	 * @param string $contents Bytes to write.
	 */
	public function write_exclusive( string $path, string $contents ): bool {
		$handle = fopen( $path, 'xb' );
		if ( false === $handle ) {
			return false;
		}

		$length  = strlen( $contents );
		$written = 0;

		while ( $written < $length ) {
			$result = fwrite( $handle, substr( $contents, $written ) );
			if ( false === $result || 0 === $result ) {
				fclose( $handle );
				return false;
			}

			$written += $result;
		}

		return fclose( $handle );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path File path.
	 */
	public function read( string $path ): ?string {
		$contents = file_get_contents( $path );

		return false === $contents ? null : $contents;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Directory path.
	 */
	public function children( string $path ): array {
		$entries = scandir( $path );
		if ( false === $entries ) {
			return array();
		}

		return array_values(
			array_filter(
				$entries,
				static function ( string $entry ): bool {
					return '.' !== $entry && '..' !== $entry;
				}
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to inspect.
	 */
	public function modified_at( string $path ): ?int {
		$modified = filemtime( $path );

		return false === $modified ? null : $modified;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Path to delete.
	 */
	public function delete_file( string $path ): bool {
		return ! file_exists( $path ) && ! is_link( $path ) ? true : unlink( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Directory path.
	 */
	public function delete_directory( string $path ): bool {
		return ! is_dir( $path ) ? true : rmdir( $path );
	}
	// phpcs:enable WordPress.WP.AlternativeFunctions
}
