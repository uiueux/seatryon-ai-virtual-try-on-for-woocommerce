<?php
/**
 * Job cleanup service tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Job;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\Job;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Job\JobCleanupService;
use SeaTryOn\Job\JobRepositoryMaintenanceInterface;
use SeaTryOn\Job\SuccessCounter;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Scheduler\ActionSchedulerInterface;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Storage\PurgeableTemporaryStorageInterface;
use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

final class JobCleanupServiceTest extends TestCase {
	public function test_delete_all_removes_jobs_files_actions_and_success_markers(): void {
		$job = $this->job();
		$repository = new CleanupRepository( array( $job ) );
		$storage = new CleanupStorage();
		$actions = new CleanupActionScheduler();
		$markers = 0;
		$counter = new SuccessCounter( null, null, static function () use ( &$markers ): bool { ++$markers; return true; } );
		$service = new JobCleanupService( $repository, new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) ), $storage, new JobScheduler( $actions ), $counter );

		self::assertSame( 1, $service->deactivate() );
		self::assertSame( array(), $repository->find_job_ids( 100 ) );
		self::assertSame( 1, $markers );
		self::assertCount( 2, $storage->deleted );
		self::assertSame( 1, $storage->purges );
		self::assertSame( array( '', array(), JobScheduler::GROUP ), $actions->unscheduled[0] );
	}

	public function test_delete_all_prunes_stale_index_without_looping(): void {
		$repository = new CleanupRepository( array() );
		$repository->add_stale( str_repeat( 'e', 32 ) );
		$service = new JobCleanupService( $repository, new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) ), new CleanupStorage(), new JobScheduler( new CleanupActionScheduler() ) );

		self::assertSame( 1, $service->delete_all() );
		self::assertSame( array(), $repository->find_job_ids( 100 ) );
	}

	public function test_delete_job_does_not_touch_files_when_worker_lock_is_busy(): void {
		$job        = $this->job();
		$repository = new CleanupRepository( array( $job ) );
		$storage    = new CleanupStorage();
		$actions    = new CleanupActionScheduler();
		$service    = new JobCleanupService(
			$repository,
			new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) ),
			$storage,
			new JobScheduler( $actions ),
			null,
			new BusyCleanupLock()
		);

		self::assertFalse( $service->delete_job( $job->id() ) );
		self::assertSame( array(), $storage->deleted );
		self::assertSame( array(), $actions->unscheduled );
		self::assertSame( array( $job->id() ), $repository->find_job_ids( 100 ) );
	}

	private function job(): Job {
		$request = new CreateJobRequest(
			hash( 'sha256', 'owner' ), 'cleanup-idempotency', 10, null, 'openai',
			ExperienceType::from_string( ExperienceType::CLOTHING ), 'Keep the product accurate.',
			str_repeat( 'a', 32 ) . '/customer.png', str_repeat( 'a', 32 ) . '/product.png'
		);
		return Job::create( str_repeat( 'd', 32 ), hash( 'sha256', 'cleanup-idempotency' ), $request, new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ), new DateTimeImmutable( '2026-08-10T00:00:00+00:00' ) );
	}
}

final class CleanupRepository implements JobRepositoryMaintenanceInterface {
	/** @var array<string,Job> */ private $jobs = array();
	/** @var string[] */ private $index = array();
	/** @param Job[] $jobs Jobs. */
	public function __construct( array $jobs ) { foreach ( $jobs as $job ) { $this->jobs[ $job->id() ] = $job; $this->index[] = $job->id(); } }
	public function add_stale( string $job_id ): void { $this->index[] = $job_id; }
	public function find_by_id( string $job_id ): ?Job { return $this->jobs[ $job_id ] ?? null; }
	public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job { return null; }
	public function save_if_absent( Job $job ): Job { $this->jobs[ $job->id() ] = $job; return $job; }
	public function save( Job $job ): void { $this->jobs[ $job->id() ] = $job; }
	public function find_expired_ids( DateTimeImmutable $now, int $limit ): array { return array(); }
	public function find_job_ids( int $limit ): array { return array_slice( $this->index, 0, $limit ); }
	public function delete( string $job_id ): bool { unset( $this->jobs[ $job_id ] ); $this->index = array_values( array_diff( $this->index, array( $job_id ) ) ); return true; }
}

final class CleanupStorage implements PurgeableTemporaryStorageInterface {
	/** @var string[] */ public $deleted = array();
	/** @var int */ public $purges = 0;
	public function create_scope(): string { return str_repeat( 'a', 32 ); }
	public function write( string $scope_id, string $role, string $contents, string $extension ): string { return $scope_id . '/' . $role . '.' . $extension; }
	public function read( string $storage_id ): string { return ''; }
	public function absolute_path( string $storage_id ): string { return $storage_id; }
	public function delete( string $storage_id ): bool { $this->deleted[] = $storage_id; return true; }
	public function delete_scope( string $scope_id ): bool { return true; }
	public function cleanup_expired(): int { return 0; }
	public function purge_all(): int { ++$this->purges; return 0; }
	public function root_path(): string { return 'private'; }
}

final class CleanupActionScheduler implements ActionSchedulerInterface {
	/** @var array<int,array<mixed>> */ public $unscheduled = array();
	public function is_available(): bool { return true; }
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int { return 1; }
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int { return 1; }
	public function has_scheduled( string $hook, array $args, string $group ): bool { return false; }
	public function unschedule_all( string $hook, array $args, string $group ): int { $this->unscheduled[] = array( $hook, $args, $group ); return 1; }
}

final class BusyCleanupLock implements LockInterface {
	public function acquire( string $key, int $ttl ): ?LockHandle {
		return null;
	}
	public function release( LockHandle $handle ): bool {
		return false;
	}
}
