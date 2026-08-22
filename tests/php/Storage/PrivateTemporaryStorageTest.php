<?php
/**
 * Private temporary storage tests.
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
use SeaTryOn\Support\FilesystemInterface;
use SeaTryOn\Support\NativeFilesystem;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies privacy boundaries, random identifiers, eager deletion and TTL.
 */
final class PrivateTemporaryStorageTest extends TestCase {

	/** @var string */
	private $sandbox;

	/** @var string */
	private $temporary_root;

	/** @var string */
	private $web_root;

	/** @var MutableClock */
	private $clock;

	/** Create isolated real directories for each test. */
	protected function setUp(): void {
		$this->sandbox        = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sea-tryon-test-' . bin2hex( random_bytes( 8 ) );
		$this->temporary_root = $this->sandbox . DIRECTORY_SEPARATOR . 'private-temp';
		$this->web_root       = $this->sandbox . DIRECTORY_SEPARATOR . 'public-web';
		mkdir( $this->temporary_root, 0700, true );
		mkdir( $this->web_root, 0700, true );
		$this->clock = new MutableClock( new DateTimeImmutable( '2026-08-09T12:00:00+00:00' ) );
	}

	/** Remove only the per-test high-entropy sandbox. */
	protected function tearDown(): void {
		if ( ! is_dir( $this->sandbox ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->sandbox, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $this->sandbox );
	}

	/** Stored identifiers are random and resolve only inside the private root. */
	public function test_creates_site_isolated_random_storage_identifiers(): void {
		$storage = $this->storage();
		$scope   = $storage->create_scope();
		$first   = $storage->write( $scope, 'input', 'image-one', 'png' );
		$second  = $storage->write( $scope, 'input', 'image-two', 'png' );

		self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $scope );
		self::assertNotSame( $first, $second );
		self::assertSame( 'image-one', $storage->read( $first ) );
		self::assertStringStartsWith( $storage->root_path(), $storage->absolute_path( $first ) );
		self::assertStringNotContainsString( basename( $this->web_root ), $storage->root_path() );
	}

	/** Traversal and client-shaped path input never reaches filesystem resolution. */
	public function test_rejects_noncanonical_or_traversal_identifiers(): void {
		$storage = $this->storage();

		$this->expectException( StorageException::class );
		$storage->absolute_path( '../wp-config.php' );
	}

	/** A temporary directory within the public document root fails closed. */
	public function test_fails_closed_when_temp_directory_is_under_web_root(): void {
		$public_temp = $this->web_root . DIRECTORY_SEPARATOR . 'temp';
		mkdir( $public_temp, 0700, true );
		$storage = new PrivateTemporaryStorage(
			new NativeFilesystem(),
			$this->clock,
			$public_temp,
			$this->web_root,
			'example.test|1'
		);

		$this->expectException( StorageException::class );
		$storage->root_path();
	}

	/** Delivery/failure paths can eagerly delete a file or complete scope. */
	public function test_eager_delete_removes_files_and_scope(): void {
		$storage = $this->storage();
		$scope   = $storage->create_scope();
		$file    = $storage->write( $scope, 'result', 'private-result', 'png' );

		self::assertTrue( $storage->delete( $file ) );
		self::assertTrue( $storage->delete_scope( $scope ) );
		self::assertDirectoryDoesNotExist( $storage->root_path() . DIRECTORY_SEPARATOR . $scope );
	}

	/** TTL cleanup removes expired scopes and preserves recent ones. */
	public function test_cleanup_applies_ttl_boundary(): void {
		$storage = $this->storage( 60 );
		$old     = $storage->create_scope();
		$new     = $storage->create_scope();
		$cutoff  = $this->clock->now()->getTimestamp() - 60;

		touch( $storage->root_path() . DIRECTORY_SEPARATOR . $old, $cutoff );
		touch( $storage->root_path() . DIRECTORY_SEPARATOR . $new, $cutoff + 1 );

		self::assertSame( 1, $storage->cleanup_expired() );
		self::assertDirectoryDoesNotExist( $storage->root_path() . DIRECTORY_SEPARATOR . $old );
		self::assertDirectoryExists( $storage->root_path() . DIRECTORY_SEPARATOR . $new );
	}

	/** Top-level scope symlinks are unlinked without following their target. */
	public function test_scope_symlinks_are_removed_without_touching_external_target(): void {
		$filesystem = new VirtualSymlinkFilesystem( new NativeFilesystem() );
		$storage    = $this->storage( 60, $filesystem );
		$target     = $this->sandbox . DIRECTORY_SEPARATOR . 'outside-private-image.png';
		file_put_contents( $target, 'external-private-bytes' );

		$explicit_scope = str_repeat( 'a', 32 );
		$explicit_link  = $storage->root_path() . DIRECTORY_SEPARATOR . $explicit_scope;
		$filesystem->add_link( $explicit_link, $target );

		self::assertTrue( $storage->delete_scope( $explicit_scope ) );
		self::assertFalse( $filesystem->has_link( $explicit_link ) );
		self::assertSame( 'external-private-bytes', file_get_contents( $target ) );

		$cleanup_scope = str_repeat( 'b', 32 );
		$cleanup_link  = $storage->root_path() . DIRECTORY_SEPARATOR . $cleanup_scope;
		$filesystem->add_link( $cleanup_link, $target );

		self::assertSame( 1, $storage->cleanup_expired() );
		self::assertFalse( $filesystem->has_link( $cleanup_link ) );
		self::assertSame( 'external-private-bytes', file_get_contents( $target ) );
	}

	/** Full lifecycle purge removes valid scopes and unlinks scope symlinks safely. */
	public function test_purge_all_never_follows_external_scope_symlink(): void {
		$filesystem = new VirtualSymlinkFilesystem( new NativeFilesystem() );
		$storage    = $this->storage( 60, $filesystem );
		$scope      = $storage->create_scope();
		$storage->write( $scope, 'customer', 'private-bytes', 'png' );
		$target = $this->sandbox . DIRECTORY_SEPARATOR . 'external-target.png';
		file_put_contents( $target, 'must-survive' );
		$link_scope = str_repeat( 'c', 32 );
		$link = $storage->root_path() . DIRECTORY_SEPARATOR . $link_scope;
		$filesystem->add_link( $link, $target );

		self::assertSame( 2, $storage->purge_all() );
		self::assertDirectoryDoesNotExist( $storage->root_path() . DIRECTORY_SEPARATOR . $scope );
		self::assertFalse( $filesystem->has_link( $link ) );
		self::assertSame( 'must-survive', file_get_contents( $target ) );
	}

	/** Full lifecycle purge reports a recognized scope it cannot delete. */
	public function test_purge_all_fails_closed_when_scope_deletion_fails(): void {
		$filesystem = new VirtualSymlinkFilesystem( new NativeFilesystem() );
		$storage    = $this->storage( 60, $filesystem );
		$scope      = $storage->create_scope();
		$reference  = $storage->write( $scope, 'customer', 'private-bytes', 'png' );
		$filesystem->fail_delete( $storage->absolute_path( $reference ) );

		$this->expectException( StorageException::class );
		$storage->purge_all();
	}

	/** The implementation refuses retention longer than the accepted 24 hours. */
	public function test_rejects_ttl_above_24_hours(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->storage( PrivateTemporaryStorage::MAX_TTL + 1 );
	}

	/** Build the real filesystem implementation. */
	private function storage( int $ttl = PrivateTemporaryStorage::MAX_TTL, ?FilesystemInterface $filesystem = null ): PrivateTemporaryStorage {
		return new PrivateTemporaryStorage(
			$filesystem ?? new NativeFilesystem(),
			$this->clock,
			$this->temporary_root,
			$this->web_root,
			'https://example.test|blog:1',
			$ttl
		);
	}
}
