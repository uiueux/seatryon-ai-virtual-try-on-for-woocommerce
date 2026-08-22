<?php
/**
 * Job application/domain service.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use DateInterval;
use DomainException;
use InvalidArgumentException;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Contracts\IdGeneratorInterface;
use SeaTryOn\Contracts\JobRepositoryInterface;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderResult;

defined( 'ABSPATH' ) || exit;

/**
 * Creates idempotent jobs and persists state-machine transitions.
 */
final class JobService {

	/**
	 * Job repository.
	 *
	 * @var JobRepositoryInterface
	 */
	private $repository;

	/**
	 * Domain clock.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * Secure identifier generator.
	 *
	 * @var IdGeneratorInterface
	 */
	private $id_generator;

	/**
	 * Job TTL in seconds.
	 *
	 * @var int
	 */
	private $ttl_seconds;

	/**
	 * Construct the job service.
	 *
	 * @param JobRepositoryInterface $repository   Job repository.
	 * @param ClockInterface         $clock        Clock.
	 * @param IdGeneratorInterface   $id_generator Secure ID generator.
	 * @param int                    $ttl_seconds  Job TTL in seconds.
	 * @throws InvalidArgumentException When TTL is outside the supported range.
	 */
	public function __construct(
		JobRepositoryInterface $repository,
		ClockInterface $clock,
		IdGeneratorInterface $id_generator,
		int $ttl_seconds = 86400
	) {
		if ( $ttl_seconds < 60 || $ttl_seconds > 86400 ) {
			throw new InvalidArgumentException( 'Job TTL must be between 60 seconds and 24 hours.' );
		}

		$this->repository   = $repository;
		$this->clock        = $clock;
		$this->id_generator = $id_generator;
		$this->ttl_seconds  = $ttl_seconds;
	}

	/**
	 * Create a job, or return the original job for an idempotent replay.
	 *
	 * The repository's atomic save_if_absent() is the final concurrency barrier.
	 *
	 * @param CreateJobRequest $request Validated creation request.
	 * @return Job
	 */
	public function create( CreateJobRequest $request ): Job {
		$idempotency = new IdempotencyKey( $request->idempotency_key() );
		$existing    = $this->repository->find_by_idempotency_fingerprint(
			$request->owner_hash(),
			$idempotency->fingerprint()
		);

		if ( null !== $existing ) {
			return $existing;
		}

		$created_at = $this->clock->now();
		$expires_at = $created_at->add( new DateInterval( 'PT' . $this->ttl_seconds . 'S' ) );
		$candidate  = Job::create(
			$this->id_generator->generate(),
			$idempotency->fingerprint(),
			$request,
			$created_at,
			$expires_at
		);

		return $this->repository->save_if_absent( $candidate );
	}

	/**
	 * Start provider processing.
	 *
	 * @param string $job_id Job ID.
	 * @return Job
	 * @throws DomainException When the job is missing.
	 * @throws DomainException When the job is missing or the transition is illegal.
	 */
	public function start_processing( string $job_id ): Job {
		$job = $this->require_job( $job_id );
		$job->start_processing( $this->clock->now() );
		$this->repository->save( $job );

		return $job;
	}

	/**
	 * Save a successful result.
	 *
	 * @param string         $job_id Job ID.
	 * @param ProviderResult $result Validated private result.
	 * @return Job
	 * @throws DomainException When the job is missing or the transition is illegal.
	 */
	public function succeed( string $job_id, ProviderResult $result ): Job {
		$job = $this->require_job( $job_id );
		$job->succeed( $result, $this->clock->now() );
		$this->repository->save( $job );

		return $job;
	}

	/**
	 * Save a normalized terminal failure.
	 *
	 * @param string        $job_id Job ID.
	 * @param ProviderError $error  Normalized safe error.
	 * @return Job
	 * @throws DomainException When the job is missing or the transition is illegal.
	 */
	public function fail( string $job_id, ProviderError $error ): Job {
		$job = $this->require_job( $job_id );
		$job->fail( $error, $this->clock->now() );
		$this->repository->save( $job );

		return $job;
	}

	/**
	 * Cancel a job.
	 *
	 * @param string $job_id Job ID.
	 * @return Job
	 * @throws DomainException When the job is missing or the transition is illegal.
	 */
	public function cancel( string $job_id ): Job {
		$job = $this->require_job( $job_id );
		$job->cancel( $this->clock->now() );
		$this->repository->save( $job );

		return $job;
	}

	/**
	 * Expire a job and clear its storage references.
	 *
	 * @param string $job_id Job ID.
	 * @return Job
	 * @throws DomainException When the job is missing or the transition is illegal.
	 */
	public function expire( string $job_id ): Job {
		$job = $this->require_job( $job_id );
		$job->expire( $this->clock->now() );
		$this->repository->save( $job );

		return $job;
	}

	/**
	 * Load a required job.
	 *
	 * @param string $job_id Job ID.
	 * @return Job
	 * @throws DomainException When the job is missing.
	 */
	private function require_job( string $job_id ): Job {
		$job = $this->repository->find_by_id( $job_id );

		if ( null === $job ) {
			throw new DomainException( 'Job was not found.' );
		}

		return $job;
	}
}
