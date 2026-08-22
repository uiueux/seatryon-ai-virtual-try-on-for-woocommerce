<?php
/**
 * Deterministic virtual-symlink filesystem double.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Storage;

use SeaTryOn\Support\FilesystemInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps a real filesystem while modeling symlinks without OS privileges.
 */
final class VirtualSymlinkFilesystem implements FilesystemInterface {

	/** @var FilesystemInterface */
	private $delegate;

	/** @var array<string,string> */
	private $links = array();

	/** @var array<string,bool> Paths whose deletion must fail. */
	private $failed_deletes = array();

	/** Initialize the wrapper. */
	public function __construct( FilesystemInterface $delegate ) {
		$this->delegate = $delegate;
	}

	/** Add a virtual link path and target. */
	public function add_link( string $path, string $target ): void {
		$this->links[ $path ] = $target;
	}

	/** Whether a virtual link remains. */
	public function has_link( string $path ): bool {
		return isset( $this->links[ $path ] );
	}

	/** Force deletion failure for one exact path. */
	public function fail_delete( string $path ): void {
		$this->failed_deletes[ $path ] = true;
	}

	/** {@inheritDoc} */
	public function real_path( string $path ): ?string {
		return isset( $this->links[ $path ] ) ? $this->delegate->real_path( $this->links[ $path ] ) : $this->delegate->real_path( $path );
	}

	/** {@inheritDoc} */
	public function is_directory( string $path ): bool {
		return $this->delegate->is_directory( $path );
	}

	/** {@inheritDoc} */
	public function is_writable( string $path ): bool {
		return $this->delegate->is_writable( $path );
	}

	/** {@inheritDoc} */
	public function is_link( string $path ): bool {
		return isset( $this->links[ $path ] ) || $this->delegate->is_link( $path );
	}

	/** {@inheritDoc} */
	public function is_file( string $path ): bool {
		return $this->delegate->is_file( $path );
	}

	/** {@inheritDoc} */
	public function create_directory( string $path ): bool {
		return $this->delegate->create_directory( $path );
	}

	/** {@inheritDoc} */
	public function write_exclusive( string $path, string $contents ): bool {
		return $this->delegate->write_exclusive( $path, $contents );
	}

	/** {@inheritDoc} */
	public function read( string $path ): ?string {
		return $this->delegate->read( $path );
	}

	/** {@inheritDoc} */
	public function children( string $path ): array {
		$children = $this->delegate->children( $path );
		foreach ( $this->links as $link => $target ) {
			unset( $target );
			if ( dirname( $link ) === $path ) {
				$children[] = basename( $link );
			}
		}

		return array_values( array_unique( $children ) );
	}

	/** {@inheritDoc} */
	public function modified_at( string $path ): ?int {
		return isset( $this->links[ $path ] ) ? $this->delegate->modified_at( $this->links[ $path ] ) : $this->delegate->modified_at( $path );
	}

	/** {@inheritDoc} */
	public function delete_file( string $path ): bool {
		if ( isset( $this->failed_deletes[ $path ] ) ) {
			return false;
		}
		if ( isset( $this->links[ $path ] ) ) {
			unset( $this->links[ $path ] );
			return true;
		}

		return $this->delegate->delete_file( $path );
	}

	/** {@inheritDoc} */
	public function delete_directory( string $path ): bool {
		return $this->delegate->delete_directory( $path );
	}
}
