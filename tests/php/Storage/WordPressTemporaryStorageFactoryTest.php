<?php
/**
 * WordPress temporary storage factory tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Storage;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SeaTryOn\Storage\PrivateTemporaryStorage;
use SeaTryOn\Storage\StorageException;
use SeaTryOn\Storage\WordPressTemporaryStorageFactory;
use SeaTryOn\Support\NativeFilesystem;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that Apache's broader public document root is respected safely.
 */
final class WordPressTemporaryStorageFactoryTest extends TestCase {

	/** @var string */
	private $sandbox;

	/** Create an isolated public tree. */
	protected function setUp(): void {
		$this->sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sea-tryon-factory-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->sandbox, 0700, true );
	}

	/** Remove only this test's high-entropy sandbox. */
	protected function tearDown(): void {
		if ( ! is_dir( $this->sandbox ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->sandbox, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $this->sandbox );
	}

	/** A canonical DOCUMENT_ROOT ancestor is selected instead of narrower ABSPATH. */
	public function test_selects_document_root_when_it_is_wordpress_ancestor(): void {
		$document_root = $this->sandbox . DIRECTORY_SEPARATOR . 'htdocs';
		$wordpress_root = $document_root . DIRECTORY_SEPARATOR . 'shop';
		mkdir( $wordpress_root, 0700, true );

		$resolved = WordPressTemporaryStorageFactory::resolve_public_web_root(
			new NativeFilesystem(),
			$wordpress_root,
			$document_root
		);

		self::assertSame( realpath( $document_root ), $resolved );
	}

	/** A temp directory in htdocs but outside ABSPATH now fails closed. */
	public function test_broader_document_root_blocks_sibling_public_temp_directory(): void {
		$document_root = $this->sandbox . DIRECTORY_SEPARATOR . 'htdocs';
		$wordpress_root = $document_root . DIRECTORY_SEPARATOR . 'shop';
		$public_temp = $document_root . DIRECTORY_SEPARATOR . 'temp';
		mkdir( $wordpress_root, 0700, true );
		mkdir( $public_temp, 0700, true );
		$filesystem = new NativeFilesystem();
		$web_root = WordPressTemporaryStorageFactory::resolve_public_web_root( $filesystem, $wordpress_root, $document_root );
		$storage = new PrivateTemporaryStorage(
			$filesystem,
			new MutableClock( new DateTimeImmutable( '2026-08-09T12:00:00+00:00' ) ),
			$public_temp,
			$web_root,
			'test-site'
		);

		$this->expectException( StorageException::class );
		$storage->root_path();
	}

	/** An unrelated or unresolvable DOCUMENT_ROOT cannot widen the public root. */
	public function test_unrelated_document_root_falls_back_to_canonical_abspath(): void {
		$wordpress_root = $this->sandbox . DIRECTORY_SEPARATOR . 'wordpress';
		$unrelated_root = $this->sandbox . DIRECTORY_SEPARATOR . 'other-public';
		mkdir( $wordpress_root, 0700, true );
		mkdir( $unrelated_root, 0700, true );

		$resolved = WordPressTemporaryStorageFactory::resolve_public_web_root(
			new NativeFilesystem(),
			$wordpress_root,
			$unrelated_root
		);

		self::assertSame( realpath( $wordpress_root ), $resolved );
	}
}
