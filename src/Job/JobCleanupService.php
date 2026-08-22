<?php
/**
 * Job cleanup service.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use SeaTryOn\Auth\ReplayStoreMaintenanceInterface;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Domain\Job;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Storage\PurgeableTemporaryStorageInterface;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** Shared REST/lifecycle cleanup boundary for jobs, actions, and private files. */
final class JobCleanupService {
	/** @var JobRepositoryMaintenanceInterface */ private $repository;
	/** @var ClockInterface */ private $clock;
	/** @var TemporaryStorageInterface */ private $storage;
	/** @var JobScheduler */ private $scheduler;
	/** @var SuccessCounter */ private $counter;
	/** @var LockInterface|null */ private $lock;
	/** @var ReplayStoreMaintenanceInterface|null */ private $replays;
	public function __construct( JobRepositoryMaintenanceInterface $repository, ClockInterface $clock, TemporaryStorageInterface $storage, JobScheduler $scheduler, ?SuccessCounter $counter = null, ?LockInterface $lock = null, ?ReplayStoreMaintenanceInterface $replays = null ) {
		$this->repository = $repository;
		$this->clock      = $clock;
		$this->storage    = $storage;
		$this->scheduler  = $scheduler;
		$this->counter    = $counter ?? new SuccessCounter();
		$this->lock       = $lock;
		$this->replays    = $replays; }

	public function cleanup_expired( int $limit = 50 ): int {
		$removed = 0;
		foreach ( $this->repository->find_expired_ids( $this->clock->now(), $limit ) as $job_id ) {
			if ( $this->delete_job( $job_id ) ) {
				++$removed; }
		}
		$this->storage->cleanup_expired();
		if ( null !== $this->replays ) {
			$this->replays->cleanup_expired( 100 );
		}
		return $removed;
	}

	public function delete_job( string $job_id ): bool {
		$handle = null;
		if ( null !== $this->lock ) {
			$handle = $this->lock->acquire( 'job-worker:' . $job_id, 30 );
			if ( null === $handle ) {
				return false;
			}
		}

		try {
			return $this->delete_job_under_lock( $job_id );
		} finally {
			if ( null !== $handle ) {
				$this->lock->release( $handle );
			}
		}
	}

	/**
	 * Delete one job while its worker lock is held.
	 *
	 * @param string $job_id Opaque job identifier.
	 */
	private function delete_job_under_lock( string $job_id ): bool {
		$job = $this->repository->find_by_id( $job_id );
		if ( null === $job ) {
			$this->scheduler->cancel_job( $job_id );
			return $this->repository->delete( $job_id ); }
		$this->scheduler->cancel_job( $job_id );
		$this->delete_references( $job );
		$deleted = $this->repository->delete( $job_id );
		if ( $deleted ) {
			$this->counter->forget( $job_id );
		}
		return $deleted;
	}

	public function delete_all(): int {
		$removed = 0;
		$failed  = false;
		while ( true ) {
			$ids = $this->repository->find_job_ids( 100 );
			if ( array() === $ids ) {
				break;
			}
			foreach ( $ids as $job_id ) {
				if ( ! $this->delete_job( $job_id ) ) {
					$failed = true;
					break 2;
				}
				++$removed;
			}
		}
		if ( $this->storage instanceof PurgeableTemporaryStorageInterface ) {
			$this->storage->purge_all();
		} else {
			$this->storage->cleanup_expired();
		}
		if ( $failed ) {
			throw new \RuntimeException( 'A pending job could not be deleted safely.' );
		}
		return $removed;
	}

	public function deactivate(): int {
		$this->scheduler->unschedule_all_plugin_actions();
		return $this->delete_all(); }

	/** Expose the fixed scheduler to the hook registrar only. */
	public function scheduler(): JobScheduler {
		return $this->scheduler;
	}

	private function delete_references( Job $job ): void {
		foreach ( array( $job->customer_image_reference(), $job->product_image_reference(), $job->result_reference() ) as $reference ) {
			if ( is_string( $reference ) && '' !== $reference ) {
				$this->storage->delete( $reference ); }
		}
	}
}
