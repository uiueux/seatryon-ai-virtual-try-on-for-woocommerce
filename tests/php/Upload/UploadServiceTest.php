<?php
/**
 * Private upload service tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Upload {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $text, string $domain ): string { unset( $domain ); return $text; }
	}
}

namespace SeaTryOn\Tests\Upload {
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Storage\TemporaryStorageInterface;
	use SeaTryOn\Upload\ImageProcessorInterface;
	use SeaTryOn\Upload\NormalizedImage;
	use SeaTryOn\Upload\UploadException;
	use SeaTryOn\Upload\UploadService;

	final class UploadServiceTest extends TestCase {
		public function test_valid_upload_is_normalized_and_written_to_private_scope(): void {
			$storage = new UploadStorage();
			$service = new UploadService( new UploadImageProcessor(), $storage, static function (): int { return UploadService::MAX_BYTES; } );
			$stored  = $service->store_customer( array( 'error' => UPLOAD_ERR_OK, 'size' => 3, 'tmp_name' => 'private-upload', 'name' => 'face.jpg' ) );

			$this->assertSame( str_repeat( 'a', 32 ), $stored->scope_id() );
			$this->assertSame( str_repeat( 'a', 32 ) . '/customer.jpg', $stored->reference() );
			$this->assertSame( array( str_repeat( 'a', 32 ), 'customer', 'jpg', 'abc' ), $storage->write_call );
		}

		public function test_declared_oversize_is_rejected_before_processing(): void {
			$processor = new UploadImageProcessor();
			$service   = new UploadService( $processor, new UploadStorage(), static function (): int { return 10; } );
			try {
				$service->store_customer( array( 'error' => UPLOAD_ERR_OK, 'size' => 11, 'tmp_name' => 'x', 'name' => 'x.jpg' ) );
				$this->fail( 'Expected an upload exception.' );
			} catch ( UploadException $exception ) {
				$this->assertSame( 'file_too_large', $exception->error_code() );
				$this->assertSame( 0, $processor->calls );
			}
		}

		public function test_missing_server_upload_limit_has_a_private_diagnostic_code(): void {
			$service = new UploadService( new UploadImageProcessor(), new UploadStorage(), static function (): int { return 0; } );

			try {
				$service->store_customer( array( 'error' => UPLOAD_ERR_OK, 'size' => 3, 'tmp_name' => 'x', 'name' => 'x.jpg' ) );
				$this->fail( 'Expected a configuration exception.' );
			} catch ( UploadException $exception ) {
				$this->assertSame( 'configuration_error', $exception->error_code() );
				$this->assertSame( 'server_upload_limit_unavailable', $exception->diagnostic_code() );
			}
		}

		public function test_storage_failure_removes_the_private_scope(): void {
			$storage       = new UploadStorage();
			$storage->fail = true;
			$service       = new UploadService( new UploadImageProcessor(), $storage, static function (): int { return 100; } );
			try {
				$service->store_customer( array( 'error' => UPLOAD_ERR_OK, 'size' => 3, 'tmp_name' => 'x', 'name' => 'x.jpg' ) );
				$this->fail( 'Expected storage failure.' );
			} catch ( \RuntimeException $exception ) {
				$this->assertSame( array( str_repeat( 'a', 32 ) ), $storage->deleted_scopes );
			}
		}
	}

	final class UploadImageProcessor implements ImageProcessorInterface {
		/** @var int */ public $calls = 0;
		public function normalize( string $path, string $original_name, int $maximum_bytes ): NormalizedImage {
			unset( $path, $original_name, $maximum_bytes );
			++$this->calls;
			return new NormalizedImage( 'abc', 'image/jpeg', 'jpg', 512, 512 );
		}
	}

	final class UploadStorage implements TemporaryStorageInterface {
		/** @var bool */ public $fail = false;
		/** @var array<int,mixed> */ public $write_call = array();
		/** @var string[] */ public $deleted_scopes = array();
		public function create_scope(): string { return str_repeat( 'a', 32 ); }
		public function write( string $scope_id, string $role, string $contents, string $extension ): string {
			$this->write_call = array( $scope_id, $role, $extension, $contents );
			if ( $this->fail ) { throw new \RuntimeException( 'failed' ); }
			return $scope_id . '/' . $role . '.' . $extension;
		}
		public function read( string $storage_id ): string { return ''; }
		public function absolute_path( string $storage_id ): string { return ''; }
		public function delete( string $storage_id ): bool { return true; }
		public function delete_scope( string $scope_id ): bool { $this->deleted_scopes[] = $scope_id; return true; }
		public function cleanup_expired(): int { return 0; }
		public function root_path(): string { return 'private'; }
	}
}
