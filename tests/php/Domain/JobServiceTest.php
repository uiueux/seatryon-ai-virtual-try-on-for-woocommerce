<?php
/**
 * Job service and aggregate tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Domain;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Contracts\IdGeneratorInterface;
use SeaTryOn\Contracts\JobRepositoryInterface;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\Job;
use SeaTryOn\Domain\JobService;
use SeaTryOn\Domain\JobStatus;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderResult;

defined( 'ABSPATH' ) || exit;

final class JobServiceTest extends TestCase {

	/** @var InMemoryJobRepository */
	private $repository;

	/** @var FrozenClock */
	private $clock;

	/** @var SequentialIdGenerator */
	private $ids;

	/** @var JobService */
	private $service;

	protected function setUp(): void {
		$this->repository = new InMemoryJobRepository();
		$this->clock      = new FrozenClock( new DateTimeImmutable( '2026-08-09T01:00:00+00:00' ) );
		$this->ids        = new SequentialIdGenerator();
		$this->service    = new JobService( $this->repository, $this->clock, $this->ids );
	}

	public function test_creates_queued_job_with_24_hour_expiry_and_no_raw_key(): void {
		$request = $this->request( 'request-00000001' );
		$job     = $this->service->create( $request );

		self::assertSame( str_repeat( 'a', 32 ), $job->id() );
		self::assertSame( JobStatus::QUEUED, $job->status()->value() );
		self::assertSame( '2026-08-10T01:00:00+00:00', $job->expires_at()->format( DATE_ATOM ) );
		self::assertSame( hash( 'sha256', 'request-00000001' ), $job->idempotency_fingerprint() );
		self::assertNotSame( 'request-00000001', $job->idempotency_fingerprint() );
		self::assertSame( 42, $job->product_id() );
		self::assertSame( 84, $job->variation_id() );
		self::assertSame( 'openai', $job->provider() );
	}

	public function test_replay_returns_original_job_without_generating_or_saving_again(): void {
		$request  = $this->request( 'request-00000001' );
		$original = $this->service->create( $request );
		$replay   = $this->service->create( $request );

		self::assertSame( $original, $replay );
		self::assertSame( 1, $this->ids->calls );
		self::assertSame( 1, $this->repository->insert_attempts );
	}

	public function test_atomic_repository_winner_is_returned_for_concurrent_replay(): void {
		$request = $this->request( 'request-00000001' );
		$winner  = Job::create(
			str_repeat( 'b', 32 ),
			hash( 'sha256', $request->idempotency_key() ),
			$request,
			$this->clock->now(),
			$this->clock->now()->modify( '+1 day' )
		);

		$this->repository->race_winner = $winner;

		self::assertSame( $winner, $this->service->create( $request ) );
		self::assertSame( 1, $this->repository->insert_attempts );
	}

	public function test_same_key_is_scoped_to_owner(): void {
		$first  = $this->service->create( $this->request( 'request-00000001', 'owner-one' ) );
		$second = $this->service->create( $this->request( 'request-00000001', 'owner-two' ) );

		self::assertNotSame( $first->id(), $second->id() );
	}

	public function test_success_lifecycle_records_private_result_then_expiry_clears_references(): void {
		$job = $this->service->create( $this->request( 'request-00000001' ) );

		$this->clock->instant = new DateTimeImmutable( '2026-08-09T01:01:00+00:00' );
		$this->service->start_processing( $job->id() );
		$this->clock->instant = new DateTimeImmutable( '2026-08-09T01:02:00+00:00' );
		$this->service->succeed( $job->id(), new ProviderResult( 'results/result-a', 'image/png', 1234, 'request-safe-id' ) );

		self::assertSame( JobStatus::SUCCEEDED, $job->status()->value() );
		self::assertSame( 'results/result-a', $job->result_reference() );
		self::assertSame( 'image/png', $job->result_mime_type() );
		self::assertSame( 1234, $job->result_byte_size() );

		$this->clock->instant = new DateTimeImmutable( '2026-08-10T01:02:00+00:00' );
		$this->service->expire( $job->id() );

		self::assertSame( JobStatus::EXPIRED, $job->status()->value() );
		self::assertSame( '', $job->customer_image_reference() );
		self::assertSame( '', $job->product_image_reference() );
		self::assertNull( $job->result_reference() );
	}

	public function test_can_fail_from_queued_state_with_normalized_error(): void {
		$job   = $this->service->create( $this->request( 'request-00000001' ) );
		$error = new ProviderError( 'provider_invalid_request', 'The provider rejected the request.', false, null, 400 );

		$this->service->fail( $job->id(), $error );

		self::assertSame( JobStatus::FAILED, $job->status()->value() );
		self::assertSame( $error, $job->error() );
		self::assertNotNull( $job->completed_at() );
	}

	public function test_can_cancel_processing_job(): void {
		$job = $this->service->create( $this->request( 'request-00000001' ) );
		$this->service->start_processing( $job->id() );
		$this->service->cancel( $job->id() );

		self::assertSame( JobStatus::CANCELLED, $job->status()->value() );
	}

	public function test_rejects_illegal_transition(): void {
		$job = $this->service->create( $this->request( 'request-00000001' ) );

		$this->expectException( DomainException::class );
		$this->service->succeed( $job->id(), new ProviderResult( 'results/result-a', 'image/png', 1234 ) );
	}

	public function test_rejects_unknown_job_transition(): void {
		$this->expectException( DomainException::class );
		$this->service->cancel( str_repeat( 'f', 32 ) );
	}

	private function request( string $idempotency_key, string $owner_hash = 'owner-one' ): CreateJobRequest {
		return new CreateJobRequest(
			hash( 'sha256', $owner_hash ),
			$idempotency_key,
			42,
			84,
			'openai',
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			'Keep the selected shirt accurate.',
			'inputs/customer-a',
			'inputs/product-a'
		);
	}
}

/**
 * Deterministic clock test double.
 */
final class FrozenClock implements ClockInterface {

	/** @var DateTimeImmutable */
	public $instant;

	public function __construct( DateTimeImmutable $instant ) {
		$this->instant = $instant;
	}

	public function now(): DateTimeImmutable {
		return $this->instant;
	}
}

/**
 * Deterministic valid ID generator test double.
 */
final class SequentialIdGenerator implements IdGeneratorInterface {

	/** @var int */
	public $calls = 0;

	public function generate(): string {
		++$this->calls;

		return str_repeat( chr( 96 + $this->calls ), 32 );
	}
}

/**
 * Atomic semantics test repository.
 */
final class InMemoryJobRepository implements JobRepositoryInterface {

	/** @var array<string,Job> */
	private $jobs = array();

	/** @var Job|null */
	public $race_winner;

	/** @var int */
	public $insert_attempts = 0;

	public function find_by_id( string $job_id ): ?Job {
		return $this->jobs[ $job_id ] ?? null;
	}

	public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job {
		foreach ( $this->jobs as $job ) {
			if ( $owner_hash === $job->owner_hash() && $idempotency_fingerprint === $job->idempotency_fingerprint() ) {
				return $job;
			}
		}

		return null;
	}

	public function save_if_absent( Job $job ): Job {
		++$this->insert_attempts;

		if ( null !== $this->race_winner ) {
			$winner           = $this->race_winner;
			$this->race_winner = null;
			$this->jobs[ $winner->id() ] = $winner;

			return $winner;
		}

		$existing = $this->find_by_idempotency_fingerprint( $job->owner_hash(), $job->idempotency_fingerprint() );

		if ( null !== $existing ) {
			return $existing;
		}

		$this->jobs[ $job->id() ] = $job;

		return $job;
	}

	public function save( Job $job ): void {
		$this->jobs[ $job->id() ] = $job;
	}
}
