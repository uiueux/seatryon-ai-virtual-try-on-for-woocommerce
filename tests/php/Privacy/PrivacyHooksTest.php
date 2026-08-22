<?php
/**
 * Privacy integration tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Privacy {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		/** Test translation stub. */
		function __( string $text, string $domain ): string {
			unset( $domain );
			return $text;
		}
	}
}

namespace SeaTryOn\Tests\Privacy {

	use DateTimeImmutable;
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Contracts\ClockInterface;
	use SeaTryOn\Domain\ExperienceType;
	use SeaTryOn\Domain\Job;
	use SeaTryOn\DTO\CreateJobRequest;
	use SeaTryOn\Job\JobCleanupService;
	use SeaTryOn\Job\JobRepositoryMaintenanceInterface;
	use SeaTryOn\Job\SuccessCounter;
	use SeaTryOn\Privacy\PrivacyHooks;
	use SeaTryOn\Privacy\WordPressPersonalJobLocator;
	use SeaTryOn\Quota\QuotaIdentity;
	use SeaTryOn\Scheduler\ActionSchedulerInterface;
	use SeaTryOn\Scheduler\JobScheduler;
	use SeaTryOn\Security\OwnerIdentityHasher;
	use SeaTryOn\Storage\TemporaryStorageInterface;

	/** Verifies exporter minimization and eraser cleanup. */
	final class PrivacyHooksTest extends TestCase {

		/** Exporter finds only the account's jobs and never exposes private references. */
		public function test_exporter_returns_minimized_job_metadata(): void {
			$hasher        = new OwnerIdentityHasher( static function (): string { return 'site-secret'; } );
			$mine          = $this->job( 42, $hasher, str_repeat( 'a', 32 ), 'private/customer-secret.jpg', 'private/product-secret.png', 'sensitive merchant prompt' );
			$unlimited     = $this->job( 42, $hasher, str_repeat( 'f', 32 ), 'private/admin.jpg', 'private/admin-product.png', 'admin prompt', null, true );
			$other         = $this->job( 7, $hasher, str_repeat( 'b', 32 ), 'private/other.jpg', 'private/other-product.png', 'other prompt' );
			$spoofed_quota = $this->job( 7, $hasher, str_repeat( 'd', 32 ), 'private/spoof-one.jpg', 'private/product.png', 'prompt', 42 );
			$spoofed_owner = $this->job( 42, $hasher, str_repeat( 'e', 32 ), 'private/spoof-two.jpg', 'private/product.png', 'prompt', 7 );
			$repo          = new PrivacyMemoryRepository( array( $mine, $unlimited, $other, $spoofed_quota, $spoofed_owner ) );
			$hooks         = $this->hooks( $repo, $hasher, new PrivacyMemoryStorage(), static function () { return (object) array( 'ID' => 42 ); } );

			$export = $hooks->export_personal_data( 'customer@example.test' );
			$json   = (string) json_encode( $export );

			self::assertCount( 2, $export['data'] );
			self::assertStringNotContainsString( 'customer-secret', $json );
			self::assertStringNotContainsString( 'product-secret', $json );
			self::assertStringNotContainsString( 'sensitive merchant prompt', $json );
			self::assertStringNotContainsString( $other->id(), $json );
			self::assertStringNotContainsString( $spoofed_quota->id(), $json );
			self::assertStringNotContainsString( $spoofed_owner->id(), $json );
			self::assertStringContainsString( $mine->id(), $json );
			self::assertStringContainsString( $unlimited->id(), $json );
		}

		/** Eraser delegates through JobCleanupService to remove files, actions, and job state. */
		public function test_eraser_removes_job_files_actions_and_record(): void {
			$hasher  = new OwnerIdentityHasher( static function (): string { return 'site-secret'; } );
			$job     = $this->job( 42, $hasher, str_repeat( 'c', 32 ), 'scope/customer.jpg', 'scope/product.png', 'prompt' );
			$repo    = new PrivacyMemoryRepository( array( $job ) );
			$storage = new PrivacyMemoryStorage();
			$hooks   = $this->hooks( $repo, $hasher, $storage, static function () { return (object) array( 'ID' => 42 ); } );

			$result = $hooks->erase_personal_data( 'customer@example.test' );

			self::assertTrue( $result['items_removed'] );
			self::assertFalse( $result['items_retained'] );
			self::assertNull( $repo->find_by_id( $job->id() ) );
			self::assertSame( array( 'scope/customer.jpg', 'scope/product.png' ), $storage->deleted );
			self::assertGreaterThanOrEqual( 3, $repo->scheduler->unscheduled );
		}

		/** Unknown accounts produce complete empty responses without revealing jobs. */
		public function test_unknown_email_returns_empty_privacy_results(): void {
			$hasher = new OwnerIdentityHasher( static function (): string { return 'site-secret'; } );
			$repo   = new PrivacyMemoryRepository( array() );
			$hooks  = $this->hooks( $repo, $hasher, new PrivacyMemoryStorage(), static function () { return false; } );

			self::assertSame( array( 'data' => array(), 'done' => true ), $hooks->export_personal_data( 'missing@example.test' ) );
			self::assertSame(
				array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				),
				$hooks->erase_personal_data( 'missing@example.test' )
			);
		}

		/** Build the real privacy hooks around in-memory adapters. */
		private function hooks( PrivacyMemoryRepository $repo, OwnerIdentityHasher $hasher, PrivacyMemoryStorage $storage, callable $resolver ): PrivacyHooks {
			$locator = new WordPressPersonalJobLocator( $repo, $hasher );
			$clock   = new PrivacyClock();
			$counter = new SuccessCounter(
				static function (): bool { return true; },
				static function (): bool { return true; },
				static function (): bool { return true; }
			);
			$cleanup = new JobCleanupService( $repo, $clock, $storage, new JobScheduler( $repo->scheduler ), $counter );

			return new PrivacyHooks( $locator, $cleanup, $resolver );
		}

		/** Create a temporary logged-in job. */
		private function job( int $user_id, OwnerIdentityHasher $hasher, string $id, string $customer_ref, string $product_ref, string $prompt, ?int $quota_user_id = null, bool $quota_exempt = false ): Job {
			$quota_user_id = null === $quota_user_id ? $user_id : $quota_user_id;
			$request = new CreateJobRequest(
				$hasher->for_user_id( $user_id ),
				'idempotency-' . $id,
				123,
				null,
				'openai',
				ExperienceType::from_string( 'clothing' ),
				$prompt,
				$customer_ref,
				$product_ref,
				QuotaIdentity::for_user( $quota_user_id, $quota_exempt )->key()
			);

			return Job::create(
				$id,
				hash( 'sha256', 'idem-' . $id ),
				$request,
				new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ),
				new DateTimeImmutable( '2026-08-10T00:00:00+00:00' )
			);
		}
	}

	/** Memory job repository and action scheduler. */
	final class PrivacyMemoryRepository implements JobRepositoryMaintenanceInterface {
		/** @var array<string,Job> */
		private $jobs = array();
		/** @var PrivacyActionScheduler */
		public $scheduler;
		/** @param array<int,Job> $jobs Initial jobs. */
		public function __construct( array $jobs ) {
			foreach ( $jobs as $job ) {
				$this->jobs[ $job->id() ] = $job;
			}
			$this->scheduler = new PrivacyActionScheduler();
		}
		public function find_by_id( string $job_id ): ?Job { return isset( $this->jobs[ $job_id ] ) ? $this->jobs[ $job_id ] : null; }
		public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job { return null; }
		public function save_if_absent( Job $job ): Job { $this->jobs[ $job->id() ] = $job; return $job; }
		public function save( Job $job ): void { $this->jobs[ $job->id() ] = $job; }
		public function find_expired_ids( DateTimeImmutable $now, int $limit ): array { return array(); }
		public function find_job_ids( int $limit ): array { return array_slice( array_keys( $this->jobs ), 0, $limit ); }
		public function delete( string $job_id ): bool { unset( $this->jobs[ $job_id ] ); return true; }
	}

	/** Memory private storage. */
	final class PrivacyMemoryStorage implements TemporaryStorageInterface {
		/** @var array<int,string> */
		public $deleted = array();
		public function create_scope(): string { return str_repeat( 'f', 32 ); }
		public function write( string $scope_id, string $role, string $contents, string $extension ): string { return ''; }
		public function read( string $storage_id ): string { return ''; }
		public function absolute_path( string $storage_id ): string { return ''; }
		public function delete( string $storage_id ): bool { $this->deleted[] = $storage_id; return true; }
		public function delete_scope( string $scope_id ): bool { return true; }
		public function cleanup_expired(): int { return 0; }
		public function root_path(): string { return 'private'; }
	}

	/** Fixed clock. */
	final class PrivacyClock implements ClockInterface {
		public function now(): DateTimeImmutable { return new DateTimeImmutable( '2026-08-09T01:00:00+00:00' ); }
	}

	/** Action Scheduler double. */
	final class PrivacyActionScheduler implements ActionSchedulerInterface {
		/** @var int */
		public $unscheduled = 0;
		public function is_available(): bool { return true; }
		public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int { return 1; }
		public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int { return 1; }
		public function has_scheduled( string $hook, array $args, string $group ): bool { return false; }
		public function unschedule_all( string $hook, array $args, string $group ): int { ++$this->unscheduled; return 1; }
	}
}
