<?php
/**
 * Scheduler failure upload ownership tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! class_exists( 'WP_REST_Controller', false ) ) {
		class WP_REST_Controller {
			/** @var string */ protected $namespace = '';
			/** @var string */ protected $rest_base = '';
			/** @var array<string,mixed>|null */ protected $schema;
		}
	}
}

namespace SeaTryOn\Tests\Rest {
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;
	use RuntimeException;
	use SeaTryOn\Job\JobSchedulingException;
	use SeaTryOn\Rest\JobsController;
	use SeaTryOn\Storage\TemporaryStorageInterface;
	use SeaTryOn\Upload\StoredUpload;

	final class SchedulingFailureCleanupTest extends TestCase {
		public function test_retained_job_preserves_its_original_scope(): void {
			$storage = new SchedulingStorage();
			$this->invoke_cleanup( $storage, 'scope-a/customer.jpg', 'scope-a/customer.jpg', 'scope-a' );
			$this->assertSame( array(), $storage->deleted );
		}

		public function test_idempotent_race_scope_is_deleted_when_retained_job_owns_original_upload(): void {
			$storage = new SchedulingStorage();
			$this->invoke_cleanup( $storage, 'scope-original/customer.jpg', 'scope-new/customer.jpg', 'scope-new' );
			$this->assertSame( array( 'scope-new' ), $storage->deleted );
		}

		private function invoke_cleanup( SchedulingStorage $storage, string $retained_reference, string $current_reference, string $scope ): void {
			$reflection = new ReflectionClass( JobsController::class );
			$controller = $reflection->newInstanceWithoutConstructor();
			$property   = $reflection->getProperty( 'storage' );
			$property->setAccessible( true );
			$property->setValue( $controller, $storage );
			$method = $reflection->getMethod( 'cleanup_after_scheduling_failure' );
			$method->setAccessible( true );
			$method->invoke( $controller, new JobSchedulingException( str_repeat( 'a', 32 ), $retained_reference, new RuntimeException( 'scheduler' ) ), new StoredUpload( $scope, $current_reference, 'image/jpeg' ) );
		}
	}

	final class SchedulingStorage implements TemporaryStorageInterface {
		/** @var string[] */ public $deleted = array();
		public function create_scope(): string { return ''; }
		public function write( string $scope_id, string $role, string $contents, string $extension ): string { return ''; }
		public function read( string $storage_id ): string { return ''; }
		public function absolute_path( string $storage_id ): string { return ''; }
		public function delete( string $storage_id ): bool { return true; }
		public function delete_scope( string $scope_id ): bool { $this->deleted[] = $scope_id; return true; }
		public function cleanup_expired(): int { return 0; }
		public function root_path(): string { return ''; }
	}
}
