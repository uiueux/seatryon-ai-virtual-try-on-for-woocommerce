<?php
/**
 * Job repository contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Contracts;

use SeaTryOn\Domain\Job;

defined( 'ABSPATH' ) || exit;

/**
 * Persists jobs without exposing a WordPress storage mechanism to the domain.
 */
interface JobRepositoryInterface {

	/**
	 * Find a job by its opaque identifier.
	 *
	 * @param string $job_id Job identifier.
	 * @return Job|null
	 */
	public function find_by_id( string $job_id ): ?Job;

	/**
	 * Find a job by the owner-scoped idempotency fingerprint.
	 *
	 * @param string $owner_hash              Stable owner hash.
	 * @param string $idempotency_fingerprint SHA-256 idempotency fingerprint.
	 * @return Job|null
	 */
	public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job;

	/**
	 * Atomically save a job unless its owner/idempotency tuple already exists.
	 *
	 * Implementations MUST enforce a unique constraint or an equivalent atomic
	 * operation. When another request wins the race, return that persisted job.
	 *
	 * @param Job $job Candidate job.
	 * @return Job Persisted candidate or the existing race winner.
	 */
	public function save_if_absent( Job $job ): Job;

	/**
	 * Persist a state change to an existing job.
	 *
	 * @param Job $job Job to save.
	 * @return void
	 */
	public function save( Job $job ): void;
}
