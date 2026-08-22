<?php
/**
 * Job worker tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Job;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Contracts\JobRepositoryInterface;
use SeaTryOn\Contracts\ProviderInterface;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\Job;
use SeaTryOn\Domain\JobStatus;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\DTO\ProviderResult;
use SeaTryOn\Job\JobWorker;
use SeaTryOn\Job\SuccessCounter;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Provider\ProviderRuntime;
use SeaTryOn\Provider\ProviderRuntimeFactoryInterface;
use SeaTryOn\Quota\QuotaIdentity;
use SeaTryOn\Quota\QuotaService;
use SeaTryOn\Quota\QuotaStoreInterface;
use SeaTryOn\Scheduler\ActionSchedulerInterface;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Settings\OptionsStoreInterface;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

final class JobWorkerTest extends TestCase {
	public function test_duplicate_callback_does_not_repeat_provider_quota_or_statistics(): void {
		$provider = new SequenceProvider( array( new ProviderResult( str_repeat( 'a', 32 ) . '/result.png', 'image/png', 100 ) ) );
		$fixture = $this->fixture( $provider );

		$fixture->worker->handle( $fixture->job_id, 0 );
		$fixture->worker->handle( $fixture->job_id, 0 );

		self::assertSame( 1, $provider->calls );
		self::assertSame( 1, $fixture->quota_store->count() );
		self::assertSame( 1, $fixture->successes );
		self::assertSame( JobStatus::SUCCEEDED, $fixture->repository->find_by_id( $fixture->job_id )->status()->value() );
		self::assertCount( 2, $fixture->storage->deleted );
	}

	public function test_retry_uses_persisted_attempt_and_stable_quota_dispatch(): void {
		$provider = new SequenceProvider(
			array(
				new ProviderException( new ProviderError( 'provider_network_failure', 'Provider unavailable.', true, 7 ) ),
				new ProviderResult( str_repeat( 'a', 32 ) . '/result.png', 'image/png', 100 ),
			)
		);
		$fixture = $this->fixture( $provider );

		$fixture->worker->handle( $fixture->job_id, 0 );
		self::assertSame( 1, $fixture->repository->find_by_id( $fixture->job_id )->dispatch_attempt() );
		self::assertSame( 7, $fixture->scheduler->scheduled[0]['timestamp'] - $fixture->clock->now()->getTimestamp() );

		$fixture->worker->handle( $fixture->job_id, 1 );
		self::assertSame( 2, $provider->calls );
		self::assertSame( 1, $fixture->quota_store->count() );
		self::assertSame( 1, $fixture->successes );
	}

	public function test_malformed_response_has_only_one_retry(): void {
		$error = new ProviderException( new ProviderError( 'provider_invalid_response', 'Malformed response.', true ) );
		$provider = new SequenceProvider( array( $error, $error, new ProviderResult( str_repeat( 'a', 32 ) . '/result.png', 'image/png', 100 ) ) );
		$fixture = $this->fixture( $provider );

		$fixture->worker->handle( $fixture->job_id, 0 );
		$fixture->worker->handle( $fixture->job_id, 1 );
		$fixture->worker->handle( $fixture->job_id, 2 );

		self::assertSame( 2, $provider->calls );
		self::assertSame( JobStatus::FAILED, $fixture->repository->find_by_id( $fixture->job_id )->status()->value() );
	}

	public function test_persisted_started_ledger_never_replays_ambiguous_provider_call(): void {
		$provider = new SequenceProvider( array( new ProviderResult( str_repeat( 'a', 32 ) . '/result.png', 'image/png', 100 ) ) );
		$fixture = $this->fixture( $provider );
		$job = $fixture->repository->find_by_id( $fixture->job_id );
		$job->start_processing( $fixture->clock->now() );
		$job->claim_dispatch( 0 );
		$fixture->repository->save( $job );

		$fixture->worker->handle( $fixture->job_id, 0 );

		self::assertSame( 0, $provider->calls );
		self::assertSame( 0, $fixture->quota_store->count() );
		self::assertSame( JobStatus::PROCESSING, $fixture->repository->find_by_id( $fixture->job_id )->status()->value() );
		// Ambiguous started attempts deliberately remain processing until TTL cleanup;
		// automatically resetting them could duplicate an external provider side effect.
	}

	public function test_provider_factory_failure_does_not_consume_quota(): void {
		$provider = new SequenceProvider( array() );
		$fixture = $this->fixture( $provider, new ThrowingRuntimeFactory() );

		$fixture->worker->handle( $fixture->job_id, 0 );

		self::assertSame( 0, $fixture->quota_store->count() );
		self::assertSame( 0, $provider->calls );
		self::assertSame( JobStatus::FAILED, $fixture->repository->find_by_id( $fixture->job_id )->status()->value() );
	}

	public function test_terminal_provider_failure_is_logged_with_safe_diagnostics(): void {
		$provider = new SequenceProvider(
			array(
				new ProviderException( new ProviderError( 'openai_authentication_failed', 'Provider rejected credentials.', false, null, 401, 'request_123' ) ),
			)
		);
		$fixture = $this->fixture( $provider );

		$fixture->worker->handle( $fixture->job_id, 0 );

		self::assertCount( 1, $fixture->log_backend->records );
		self::assertSame( 'error', $fixture->log_backend->records[0]['level'] );
		self::assertSame( 'openai_authentication_failed', $fixture->log_backend->records[0]['context']['provider_error_code'] );
		self::assertSame( 401, $fixture->log_backend->records[0]['context']['provider_http_status'] );
		self::assertSame( 'request_123', $fixture->log_backend->records[0]['context']['diagnostic_reference'] );
	}

	public function test_quota_exempt_manager_dispatches_without_consuming_quota(): void {
		$provider = new SequenceProvider( array( new ProviderResult( str_repeat( 'a', 32 ) . '/result.png', 'image/png', 100 ) ) );
		$fixture  = $this->fixture( $provider, null, true );

		$fixture->worker->handle( $fixture->job_id, 0 );

		self::assertSame( 1, $provider->calls );
		self::assertSame( 0, $fixture->quota_store->count() );
		self::assertSame( 1, $fixture->successes );
		self::assertSame( JobStatus::SUCCEEDED, $fixture->repository->find_by_id( $fixture->job_id )->status()->value() );
	}

	private function fixture( SequenceProvider $provider, ?ProviderRuntimeFactoryInterface $runtime_factory = null, bool $quota_exempt = false ): WorkerFixture {
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$job = $this->job( $quota_exempt );
		$repository = new SnapshotRepository( $job );
		$lock = new WorkerLock();
		$quota_store = new WorkerQuotaStore();
		$quota = new QuotaService( $quota_store, $lock, $clock, new DateTimeZone( 'UTC' ) );
		$options = new WorkerOptions();
		$settings = new SettingsRepository( $options );
		$action_scheduler = new WorkerActionScheduler();
		$scheduler = new JobScheduler( $action_scheduler );
		$storage = new WorkerStorage();
		$successes = 0;
		$counter = new SuccessCounter(
			static function (): bool { return true; },
			static function () use ( &$successes ): bool { ++$successes; return true; },
			static function (): bool { return true; }
		);
		$log_backend = new WorkerLogBackend();
		$worker = new JobWorker( $repository, $clock, $lock, $quota, $settings, $runtime_factory ?? new WorkerRuntimeFactory( $provider ), $scheduler, $storage, $counter, new Logger( $log_backend ), static function (): int { return 0; } );

		return new WorkerFixture( $worker, $repository, $quota_store, $action_scheduler, $storage, $clock, $job->id(), $log_backend, $successes );
	}

	private function job( bool $quota_exempt = false ): Job {
		$request = new CreateJobRequest(
			hash( 'sha256', 'owner' ), 'worker-idempotency', 10, null, 'openai',
			ExperienceType::from_string( ExperienceType::CLOTHING ), 'Keep the product accurate.',
			str_repeat( 'a', 32 ) . '/customer.png', str_repeat( 'a', 32 ) . '/product.png',
			QuotaIdentity::for_user( 7, $quota_exempt )->key()
		);
		return Job::create( str_repeat( 'b', 32 ), hash( 'sha256', 'worker-idempotency' ), $request, new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ), new DateTimeImmutable( '2026-08-10T00:00:00+00:00' ) );
	}
}

final class WorkerFixture {
	/** @var JobWorker */ public $worker; /** @var SnapshotRepository */ public $repository; /** @var WorkerQuotaStore */ public $quota_store;
	/** @var WorkerActionScheduler */ public $scheduler; /** @var WorkerStorage */ public $storage; /** @var MutableClock */ public $clock;
	/** @var string */ public $job_id; /** @var WorkerLogBackend */ public $log_backend; /** @var int */ public $successes;
	public function __construct( JobWorker $worker, SnapshotRepository $repository, WorkerQuotaStore $quota_store, WorkerActionScheduler $scheduler, WorkerStorage $storage, MutableClock $clock, string $job_id, WorkerLogBackend $log_backend, int &$successes ) { $this->worker = $worker; $this->repository = $repository; $this->quota_store = $quota_store; $this->scheduler = $scheduler; $this->storage = $storage; $this->clock = $clock; $this->job_id = $job_id; $this->log_backend = $log_backend; $this->successes =& $successes; }
}

final class WorkerLogBackend {
	/** @var array<int,array{level:string,message:string,context:array<mixed>}> */ public $records = array();
	/** @param array<mixed> $context Context. */
	public function log( string $level, string $message, array $context ): void { $this->records[] = compact( 'level', 'message', 'context' ); }
}

final class SnapshotRepository implements JobRepositoryInterface {
	/** @var array<string,array<string,mixed>> */ private $jobs = array();
	public function __construct( Job $job ) { $job->mark_persisted_revision( 1 ); $this->jobs[ $job->id() ] = $job->snapshot(); }
	public function find_by_id( string $job_id ): ?Job { return isset( $this->jobs[ $job_id ] ) ? Job::from_snapshot( $this->jobs[ $job_id ] ) : null; }
	public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job { return null; }
	public function save_if_absent( Job $job ): Job { $this->save( $job ); return $job; }
	public function save( Job $job ): void { $job->mark_persisted_revision( max( 1, $job->revision() + 1 ) ); $this->jobs[ $job->id() ] = $job->snapshot(); }
}

final class WorkerLock implements LockInterface {
	public function acquire( string $key, int $ttl ): ?LockHandle { return new LockHandle( $key, (string) $ttl, 'memory' ); }
	public function release( LockHandle $handle ): bool { return true; }
}

final class WorkerQuotaStore implements QuotaStoreInterface {
	/** @var array<string,array<string,mixed>> */ private $states = array();
	public function load( string $identity_key ): ?array { return $this->states[ $identity_key ] ?? null; }
	public function save( string $identity_key, array $state ): bool { $this->states[ $identity_key ] = $state; return true; }
	public function count(): int { $state = reset( $this->states ); return is_array( $state ) ? (int) $state['count'] : 0; }
}

final class WorkerOptions implements OptionsStoreInterface {
	public function get( string $name, $fallback = null ) { return $fallback; }
	public function update( string $name, $value, bool $autoload = false ): bool { return true; }
}

final class WorkerActionScheduler implements ActionSchedulerInterface {
	/** @var array<int,array<string,mixed>> */ public $scheduled = array();
	public function is_available(): bool { return true; }
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int { $this->scheduled[] = compact( 'timestamp', 'hook', 'args', 'group', 'unique' ); return count( $this->scheduled ); }
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int { return 1; }
	public function has_scheduled( string $hook, array $args, string $group ): bool { return false; }
	public function unschedule_all( string $hook, array $args, string $group ): int { return 0; }
}

final class WorkerStorage implements TemporaryStorageInterface {
	/** @var string[] */ public $deleted = array();
	public function create_scope(): string { return str_repeat( 'a', 32 ); }
	public function write( string $scope_id, string $role, string $contents, string $extension ): string { return $scope_id . '/' . $role . '.' . $extension; }
	public function read( string $storage_id ): string { return ''; }
	public function absolute_path( string $storage_id ): string { return $storage_id; }
	public function delete( string $storage_id ): bool { $this->deleted[] = $storage_id; return true; }
	public function delete_scope( string $scope_id ): bool { return true; }
	public function cleanup_expired(): int { return 0; }
	public function root_path(): string { return 'private'; }
}

final class SequenceProvider implements ProviderInterface {
	/** @var array<int,ProviderResult|ProviderException> */ private $sequence; /** @var int */ public $calls = 0;
	/** @param array<int,ProviderResult|ProviderException> $sequence Sequence. */
	public function __construct( array $sequence ) { $this->sequence = $sequence; }
	public function generate( ProviderRequest $request ): ProviderResult { $next = $this->sequence[ $this->calls++ ]; if ( $next instanceof ProviderException ) { throw $next; } return $next; }
}

final class WorkerRuntimeFactory implements ProviderRuntimeFactoryInterface {
	/** @var ProviderInterface */ private $provider;
	public function __construct( ProviderInterface $provider ) { $this->provider = $provider; }
	public function create_for_job( Job $job ): ProviderRuntime { return new ProviderRuntime( $this->provider, new ProviderRequest( $job->id(), $job->customer_image_reference(), $job->product_image_reference(), $job->prompt(), $job->experience_type(), 'auto' ) ); }
}

final class ThrowingRuntimeFactory implements ProviderRuntimeFactoryInterface {
	public function create_for_job( Job $job ): ProviderRuntime { throw new \RuntimeException( 'Configuration unavailable.' ); }
}
