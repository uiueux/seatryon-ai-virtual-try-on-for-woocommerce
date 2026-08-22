<?php
/**
 * Scheduled job creator.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use Throwable;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Domain\Job;
use SeaTryOn\Domain\JobService;
use SeaTryOn\Domain\JobStatus;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Scheduler\JobScheduler;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** Ensures every newly-created or idempotently-replayed queued job has one action. */
final class ScheduledJobCreator {
	/** @var JobService */ private $jobs;
	/** @var JobScheduler */ private $scheduler;
	/** @var ClockInterface */ private $clock;
	public function __construct( JobService $jobs, JobScheduler $scheduler, ClockInterface $clock ) {
		$this->jobs      = $jobs;
		$this->scheduler = $scheduler;
		$this->clock     = $clock; }
	public function create( CreateJobRequest $request ): Job {
		$job = $this->jobs->create( $request );
		if ( JobStatus::QUEUED !== $job->status()->value() ) {
			return $job;
		}
		try {
			$this->scheduler->enqueue( $job, $this->clock->now()->getTimestamp() );
		} catch ( Throwable $exception ) {
			// Keep the queued snapshot and its original scope. An idempotent replay
			// can re-ensure the action; TTL cleanup removes abandoned queued jobs.
			throw new JobSchedulingException( $job->id(), $job->customer_image_reference(), $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Opaque identifiers and the original exception remain internal.
		}
		return $job;
	}
}
