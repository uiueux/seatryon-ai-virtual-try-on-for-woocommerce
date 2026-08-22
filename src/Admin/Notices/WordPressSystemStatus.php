<?php
/**
 * WordPress runtime status adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

use Throwable;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Dependencies;
use SeaTryOn\Storage\PrivateTemporaryStorage;
use SeaTryOn\Storage\StorageException;
use SeaTryOn\Storage\WordPressTemporaryStorageFactory;
use SeaTryOn\Support\FilesystemInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Reads public WordPress APIs and verifies the actual private storage root.
 */
final class WordPressSystemStatus implements SystemStatusInterface {

	/**
	 * Filesystem adapter.
	 *
	 * @var FilesystemInterface
	 */
	private $filesystem;

	/**
	 * Clock used by private storage.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * Set up the runtime adapter.
	 *
	 * @param FilesystemInterface $filesystem Filesystem adapter.
	 * @param ClockInterface      $clock      Clock used by private storage.
	 */
	public function __construct( FilesystemInterface $filesystem, ClockInterface $clock ) {
		$this->filesystem = $filesystem;
		$this->clock      = $clock;
	}

	/** {@inheritDoc} */
	public function is_woocommerce_active(): bool {
		return Dependencies::is_woocommerce_active();
	}

	/** {@inheritDoc} */
	public function woocommerce_version(): string {
		return Dependencies::woocommerce_version();
	}

	/** {@inheritDoc} */
	public function minimum_woocommerce_version(): string {
		return Dependencies::minimum_woocommerce_version();
	}

	/** {@inheritDoc} */
	public function storage_status(): string {
		$temporary_root   = $this->filesystem->real_path( get_temp_dir() );
		$document_root    = isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) )
			: '';
		$public_root      = WordPressTemporaryStorageFactory::resolve_public_web_root(
			$this->filesystem,
			ABSPATH,
			$document_root
		);
		$canonical_public = $this->filesystem->real_path( $public_root );

		if ( null === $temporary_root || null === $canonical_public ) {
			return self::STORAGE_UNAVAILABLE;
		}

		if ( $this->is_contained( $temporary_root, $canonical_public ) ) {
			return self::STORAGE_PUBLIC;
		}

		try {
			$storage = new PrivateTemporaryStorage(
				$this->filesystem,
				$this->clock,
				$temporary_root,
				$canonical_public,
				ABSPATH . '|' . get_current_network_id() . '|' . get_current_blog_id()
			);
			$storage->root_path();
		} catch ( StorageException $exception ) {
			return false !== strpos( $exception->getMessage(), 'public web root' )
				? self::STORAGE_PUBLIC
				: self::STORAGE_UNAVAILABLE;
		} catch ( Throwable $exception ) {
			return self::STORAGE_UNAVAILABLE;
		}

		return self::STORAGE_AVAILABLE;
	}

	/**
	 * Determine whether a canonical path is equal to or below a root.
	 *
	 * @param string $path Candidate path.
	 * @param string $root Root path.
	 */
	private function is_contained( string $path, string $root ): bool {
		$path = $this->normalize_path( $path );
		$root = rtrim( $this->normalize_path( $root ), '/' );

		return $path === $root || 0 === strpos( $path, $root . '/' );
	}

	/**
	 * Normalize a path for containment checks.
	 *
	 * @param string $path Path to normalize.
	 */
	private function normalize_path( string $path ): string {
		$normalized = str_replace( '\\', '/', $path );

		return '\\' === DIRECTORY_SEPARATOR ? strtolower( $normalized ) : $normalized;
	}
}
