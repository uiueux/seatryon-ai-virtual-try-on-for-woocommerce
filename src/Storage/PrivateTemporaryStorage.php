<?php
/**
 * Private temporary storage implementation.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Storage;

use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\FilesystemInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Stores data below a site-isolated, non-public directory.
 */
final class PrivateTemporaryStorage implements PurgeableTemporaryStorageInterface {

	public const MAX_TTL = 86400;

	/**
	 * Filesystem adapter.
	 *
	 * @var FilesystemInterface
	 */
	private $filesystem;

	/**
	 * Clock used for TTL checks.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * Configured temporary root.
	 *
	 * @var string
	 */
	private $temporary_root;

	/**
	 * Public web root.
	 *
	 * @var string
	 */
	private $web_root;

	/**
	 * Stable site identity material.
	 *
	 * @var string
	 */
	private $site_identifier;

	/**
	 * Retention in seconds.
	 *
	 * @var int
	 */
	private $ttl;

	/**
	 * Canonical site-isolated root.
	 *
	 * @var string|null
	 */
	private $root;

	/**
	 * Initialize private temporary storage.
	 *
	 * @param FilesystemInterface $filesystem      Filesystem adapter.
	 * @param ClockInterface      $clock           Clock.
	 * @param string              $temporary_root  Value resolved from get_temp_dir().
	 * @param string              $web_root        Public WordPress ABSPATH.
	 * @param string              $site_identifier Stable, site-specific identity material.
	 * @param int                 $ttl             Retention in seconds, at most 24 hours.
	 * @throws \InvalidArgumentException When TTL or site identity is invalid.
	 */
	public function __construct(
		FilesystemInterface $filesystem,
		ClockInterface $clock,
		string $temporary_root,
		string $web_root,
		string $site_identifier,
		int $ttl = self::MAX_TTL
	) {
		if ( $ttl < 1 || $ttl > self::MAX_TTL ) {
			throw new \InvalidArgumentException( 'Temporary storage TTL must be between 1 and 86400 seconds.' );
		}

		if ( '' === trim( $site_identifier ) ) {
			throw new \InvalidArgumentException( 'A site identifier is required.' );
		}

		$this->filesystem      = $filesystem;
		$this->clock           = $clock;
		$this->temporary_root  = $temporary_root;
		$this->web_root        = $web_root;
		$this->site_identifier = $site_identifier;
		$this->ttl             = $ttl;
		$this->root            = null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws StorageException When a private scope cannot be created.
	 */
	public function create_scope(): string {
		$root = $this->root_path();

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$scope_id = bin2hex( random_bytes( 16 ) );
			$path     = $root . DIRECTORY_SEPARATOR . $scope_id;

			if ( null !== $this->filesystem->real_path( $path ) ) {
				continue;
			}

			if ( $this->filesystem->create_directory( $path ) ) {
				$canonical = $this->filesystem->real_path( $path );
				if ( null === $canonical || ! $this->is_contained( $canonical, $root ) ) {
					throw new StorageException( 'The temporary scope could not be contained safely.' );
				}

				return $scope_id;
			}
		}

		throw new StorageException( 'A private temporary scope could not be created.' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $scope_id Scope identifier.
	 * @param string $role File purpose without user-provided names.
	 * @param string $contents Bytes to store.
	 * @param string $extension Validated file extension.
	 * @throws StorageException When validation or writing fails.
	 */
	public function write( string $scope_id, string $role, string $contents, string $extension ): string {
		$this->assert_scope_id( $scope_id );

		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]{0,31}$/', $role ) ) {
			throw new StorageException( 'The storage role is invalid.' );
		}

		$extension = strtolower( $extension );
		if ( 1 !== preg_match( '/^[a-z0-9]{1,8}$/', $extension ) ) {
			throw new StorageException( 'The storage extension is invalid.' );
		}

		$scope_path = $this->scope_path( $scope_id );
		if ( null === $scope_path ) {
			throw new StorageException( 'The temporary scope does not exist.' );
		}

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$filename   = $role . '-' . bin2hex( random_bytes( 16 ) ) . '.' . $extension;
			$storage_id = $scope_id . '/' . $filename;
			$path       = $scope_path . DIRECTORY_SEPARATOR . $filename;

			if ( $this->filesystem->write_exclusive( $path, $contents ) ) {
				$this->absolute_path( $storage_id );
				return $storage_id;
			}
		}

		throw new StorageException( 'The temporary file could not be written.' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $storage_id Storage-relative identifier.
	 * @throws StorageException When reading fails.
	 */
	public function read( string $storage_id ): string {
		$contents = $this->filesystem->read( $this->absolute_path( $storage_id ) );
		if ( null === $contents ) {
			throw new StorageException( 'The temporary file could not be read.' );
		}

		return $contents;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $storage_id Storage-relative identifier.
	 * @throws StorageException When the identifier is invalid or unsafe.
	 */
	public function absolute_path( string $storage_id ): string {
		if ( 1 !== preg_match( '#^[a-f0-9]{32}/[a-z][a-z0-9_-]{0,31}-[a-f0-9]{32}\.[a-z0-9]{1,8}$#', $storage_id ) ) {
			throw new StorageException( 'The storage identifier is invalid.' );
		}

		$path      = $this->root_path() . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $storage_id );
		$canonical = $this->filesystem->real_path( $path );

		if ( null === $canonical || ! $this->filesystem->is_file( $canonical ) || ! $this->is_contained( $canonical, $this->root_path() ) ) {
			throw new StorageException( 'The temporary file is missing or outside private storage.' );
		}

		return $canonical;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $storage_id Storage-relative identifier.
	 */
	public function delete( string $storage_id ): bool {
		try {
			$path = $this->absolute_path( $storage_id );
		} catch ( StorageException $exception ) {
			return false;
		}

		return $this->filesystem->delete_file( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $scope_id Scope identifier.
	 */
	public function delete_scope( string $scope_id ): bool {
		$this->assert_scope_id( $scope_id );
		$raw_path = $this->root_path() . DIRECTORY_SEPARATOR . $scope_id;

		// A top-level scope symlink is never a valid scope. Remove the link itself
		// without canonicalizing or traversing its target.
		if ( $this->filesystem->is_link( $raw_path ) ) {
			return $this->filesystem->delete_file( $raw_path );
		}

		$path = $this->scope_path( $scope_id );

		if ( null === $path ) {
			return true;
		}

		return $this->delete_tree( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws StorageException When storage privacy cannot be verified.
	 */
	public function cleanup_expired(): int {
		$removed = 0;
		$cutoff  = $this->clock->now()->getTimestamp() - $this->ttl;
		$root    = $this->root_path();

		foreach ( $this->filesystem->children( $root ) as $child ) {
			if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $child ) ) {
				continue;
			}

			$path = $root . DIRECTORY_SEPARATOR . $child;
			if ( $this->filesystem->is_link( $path ) ) {
				if ( $this->delete_scope( $child ) ) {
					++$removed;
				}
				continue;
			}

			$modified = $this->filesystem->modified_at( $path );

			if ( null !== $modified && $modified <= $cutoff && $this->delete_scope( $child ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws StorageException When a recognized private scope cannot be removed.
	 */
	public function purge_all(): int {
		$removed = 0;
		foreach ( $this->filesystem->children( $this->root_path() ) as $child ) {
			// Never recurse from the root and never touch unrecognized entries.
			if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $child ) ) {
				continue;
			}
			if ( ! $this->delete_scope( $child ) ) {
				throw new StorageException( 'A private temporary scope could not be purged.' );
			}
			++$removed;
		}
		return $removed;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws StorageException When storage privacy cannot be verified.
	 */
	public function root_path(): string {
		if ( null !== $this->root ) {
			return $this->root;
		}

		$temporary_root = $this->filesystem->real_path( $this->temporary_root );
		$web_root       = $this->filesystem->real_path( $this->web_root );

		if ( null === $temporary_root || null === $web_root ) {
			throw new StorageException( 'Temporary storage privacy could not be verified.' );
		}

		if ( ! $this->filesystem->is_directory( $temporary_root ) || ! $this->filesystem->is_writable( $temporary_root ) ) {
			throw new StorageException( 'The temporary directory is unavailable or not writable.' );
		}

		if ( $this->is_contained( $temporary_root, $web_root ) ) {
			throw new StorageException( 'Temporary storage resolves inside the public web root.' );
		}

		$site_hash = substr( hash( 'sha256', $this->site_identifier ), 0, 24 );
		$root      = $temporary_root . DIRECTORY_SEPARATOR . 'sea-tryon-' . $site_hash;

		if ( ! $this->filesystem->create_directory( $root ) ) {
			throw new StorageException( 'The site-isolated temporary directory could not be created.' );
		}

		$canonical = $this->filesystem->real_path( $root );
		if ( null === $canonical || ! $this->is_contained( $canonical, $temporary_root ) || $this->is_contained( $canonical, $web_root ) ) {
			throw new StorageException( 'The site-isolated temporary directory is not private.' );
		}

		if ( ! $this->filesystem->is_writable( $canonical ) ) {
			throw new StorageException( 'The site-isolated temporary directory is not writable.' );
		}

		$this->root = $canonical;

		return $this->root;
	}

	/**
	 * Resolve and validate an existing scope.
	 *
	 * @param string $scope_id Scope identifier.
	 */
	private function scope_path( string $scope_id ): ?string {
		$path      = $this->root_path() . DIRECTORY_SEPARATOR . $scope_id;
		$canonical = $this->filesystem->real_path( $path );

		if ( null === $canonical || ! $this->filesystem->is_directory( $canonical ) || ! $this->is_contained( $canonical, $this->root_path() ) ) {
			return null;
		}

		return $canonical;
	}

	/**
	 * Validate a storage scope identifier.
	 *
	 * @param string $scope_id Scope identifier.
	 * @throws StorageException When the identifier is invalid.
	 */
	private function assert_scope_id( string $scope_id ): void {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $scope_id ) ) {
			throw new StorageException( 'The storage scope identifier is invalid.' );
		}
	}

	/**
	 * Delete a contained tree without following symbolic links.
	 *
	 * @param string $path Canonical scope path.
	 * @throws StorageException When the path is outside an allowed scope.
	 */
	private function delete_tree( string $path ): bool {
		if ( ! $this->is_contained( $path, $this->root_path() ) || $this->same_path( $path, $this->root_path() ) ) {
			throw new StorageException( 'Refusing to delete outside a temporary scope.' );
		}

		foreach ( $this->filesystem->children( $path ) as $child ) {
			$child_path = $path . DIRECTORY_SEPARATOR . $child;
			if ( $this->filesystem->is_link( $child_path ) || $this->filesystem->is_file( $child_path ) ) {
				if ( ! $this->filesystem->delete_file( $child_path ) ) {
					return false;
				}
				continue;
			}

			$canonical = $this->filesystem->real_path( $child_path );
			if ( null === $canonical || ! $this->filesystem->is_directory( $canonical ) || ! $this->delete_tree( $canonical ) ) {
				return false;
			}
		}

		return $this->filesystem->delete_directory( $path );
	}

	/**
	 * Determine whether a canonical path is equal to or below a root.
	 *
	 * @param string $path Canonical candidate path.
	 * @param string $root Canonical root path.
	 */
	private function is_contained( string $path, string $root ): bool {
		$path = $this->normalize_path( $path );
		$root = rtrim( $this->normalize_path( $root ), '/' );

		return $path === $root || 0 === strpos( $path, $root . '/' );
	}

	/**
	 * Determine whether two paths are the same on the current platform.
	 *
	 * @param string $first First path.
	 * @param string $second Second path.
	 */
	private function same_path( string $first, string $second ): bool {
		return $this->normalize_path( $first ) === $this->normalize_path( $second );
	}

	/**
	 * Normalize separators and Windows path casing for containment checks.
	 *
	 * @param string $path Path to normalize.
	 */
	private function normalize_path( string $path ): string {
		$normalized = str_replace( '\\', '/', $path );

		return '\\' === DIRECTORY_SEPARATOR ? strtolower( $normalized ) : $normalized;
	}
}
