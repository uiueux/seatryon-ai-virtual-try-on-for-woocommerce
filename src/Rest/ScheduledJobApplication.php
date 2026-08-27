<?php
/**
 * Existing job-worker bridge for REST.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use SeaTryOn\Contracts\JobRepositoryInterface;
use SeaTryOn\Domain\Job;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Job\JobCleanupService;
use SeaTryOn\Job\ScheduledJobCreator;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

/** Keeps the controller independent of persistence and Action Scheduler details. */
final class ScheduledJobApplication implements JobApplicationInterface {
	/** @var ScheduledJobCreator */ private $creator;
	/** @var JobRepositoryInterface */ private $repository;
	/** @var JobCleanupService */ private $cleanup;
	public function __construct( ScheduledJobCreator $creator, JobRepositoryInterface $repository, JobCleanupService $cleanup ) {
		$this->creator    = $creator;
		$this->repository = $repository;
		$this->cleanup    = $cleanup;
	}

	public function create( CreateJobCommand $command ): Job {
		$product  = $command->product();
		$identity = $command->identity();
		$request  = new CreateJobRequest(
			$identity->owner_hash(),
			$command->idempotency_key(),
			$product->product_id(),
			$product->variation_id(),
			$product->provider(),
			$product->experience_type(),
			$product->prompt(),
			$command->customer_image_reference(),
			$product->product_image_reference(),
			$identity->quota_identity_key(),
			$identity->guest_ip_quota_identity_key()
		);

		return $this->creator->create( $request );
	}

	public function find( string $job_id ): ?Job {
		return $this->repository->find_by_id( $job_id ); }
	public function delete( string $job_id ): bool {
		return $this->cleanup->delete_job( $job_id ); }
}
