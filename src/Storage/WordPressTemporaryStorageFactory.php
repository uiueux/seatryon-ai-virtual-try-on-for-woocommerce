<?php
/**
 * WordPress temporary storage factory.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Storage;

use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\FilesystemInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies WordPress runtime values to the private storage implementation.
 */
final class WordPressTemporaryStorageFactory {

	/**
	 * Create storage for the current WordPress site.
	 *
	 * @param FilesystemInterface $filesystem Filesystem adapter.
	 * @param ClockInterface      $clock      Clock.
	 * @param int                 $ttl        Retention in seconds.
	 */
	public static function create( FilesystemInterface $filesystem, ClockInterface $clock, int $ttl = PrivateTemporaryStorage::MAX_TTL ): TemporaryStorageInterface {
		$site_identifier = ABSPATH . '|' . get_current_network_id() . '|' . get_current_blog_id();
		$document_root   = isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '';
		$public_web_root = self::resolve_public_web_root( $filesystem, ABSPATH, $document_root );

		return new PrivateTemporaryStorage(
			$filesystem,
			$clock,
			get_temp_dir(),
			$public_web_root,
			$site_identifier,
			$ttl
		);
	}

	/**
	 * Resolve the widest verified public root relevant to this WordPress site.
	 *
	 * Apache may serve a parent of ABSPATH. The broader DOCUMENT_ROOT is trusted
	 * only when both paths canonicalize and it is an ancestor of ABSPATH.
	 *
	 * @param FilesystemInterface $filesystem    Filesystem adapter.
	 * @param string              $wordpress_root WordPress ABSPATH.
	 * @param string              $document_root  Web-server document root.
	 */
	public static function resolve_public_web_root( FilesystemInterface $filesystem, string $wordpress_root, string $document_root ): string {
		$canonical_wordpress = $filesystem->real_path( $wordpress_root );
		if ( null === $canonical_wordpress ) {
			return $wordpress_root;
		}

		$canonical_document = '' === trim( $document_root ) ? null : $filesystem->real_path( $document_root );
		if ( null === $canonical_document ) {
			return $canonical_wordpress;
		}

		$wordpress = self::normalize_path( $canonical_wordpress );
		$document  = rtrim( self::normalize_path( $canonical_document ), '/' );

		if ( $wordpress === $document || 0 === strpos( $wordpress, $document . '/' ) ) {
			return $canonical_document;
		}

		return $canonical_wordpress;
	}

	/**
	 * Normalize a canonical path for cross-platform containment checks.
	 *
	 * @param string $path Canonical path.
	 */
	private static function normalize_path( string $path ): string {
		$normalized = str_replace( '\\', '/', $path );

		return '\\' === DIRECTORY_SEPARATOR ? strtolower( $normalized ) : $normalized;
	}
}
