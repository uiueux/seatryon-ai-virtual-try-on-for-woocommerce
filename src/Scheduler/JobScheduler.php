<?php
/**
 * Job scheduler.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Scheduler;

use SeaTryOn\Domain\Job;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** Owns fixed hooks/groups and privacy-safe Action Scheduler arguments. */
final class JobScheduler {
	private const CLEANUP_INTERVAL = 3600;
	public const GROUP             = 'sea-tryon';
	public const WORK_HOOK         = 'sea_tryon_process_job';
	public const CLEANUP_HOOK      = 'sea_tryon_cleanup_jobs';

	/** @var ActionSchedulerInterface */
	private $scheduler;

	public function __construct( ?ActionSchedulerInterface $scheduler = null ) {
		$this->scheduler = $scheduler ?? new WordPressActionScheduler();
	}

	public function enqueue( Job $job, int $timestamp ): int {
		return $this->schedule_attempt( $job->id(), 0, $timestamp );
	}

	public function schedule_retry( string $job_id, int $attempt, int $timestamp ): int {
		return $this->schedule_attempt( $job_id, $attempt, $timestamp );
	}

	public function cancel_job( string $job_id ): int {
		$total = 0;
		for ( $attempt = 0; $attempt <= 2; ++$attempt ) {
			$total += $this->scheduler->unschedule_all(
				self::WORK_HOOK,
				array(
					'job_id'  => $job_id,
					'attempt' => $attempt,
				),
				self::GROUP
			);
		}
		return $total;
	}

	public function ensure_cleanup( int $timestamp ): int {
		if ( ! $this->scheduler->is_available() ) {
			throw new SchedulerUnavailableException( 'Action Scheduler is unavailable.' );
		}
		if ( $this->scheduler->has_scheduled( self::CLEANUP_HOOK, array(), self::GROUP ) ) {
			return 1;
		}
		$action_id = $this->scheduler->schedule_recurring( max( 1, $timestamp ), self::CLEANUP_INTERVAL, self::CLEANUP_HOOK, array(), self::GROUP, true );
		if ( $action_id < 1 ) {
			throw new SchedulerUnavailableException( 'Action Scheduler did not accept recurring cleanup.' );
		}
		return $action_id;
	}

	public function unschedule_all_plugin_actions(): void {
		// An empty hook with a group is Action Scheduler's public group-cancel path;
		// hook + empty args would miss work actions carrying job_id/attempt.
		$this->scheduler->unschedule_all( '', array(), self::GROUP );
	}

	private function schedule_attempt( string $job_id, int $attempt, int $timestamp ): int {
		if ( ! $this->scheduler->is_available() ) {
			throw new SchedulerUnavailableException( 'Action Scheduler is unavailable.' ); }
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) || $attempt < 0 || $attempt > 2 ) {
			throw new \InvalidArgumentException( 'Invalid job action arguments.' ); }
		$args = array(
			'job_id'  => $job_id,
			'attempt' => $attempt,
		);
		if ( $this->scheduler->has_scheduled( self::WORK_HOOK, $args, self::GROUP ) ) {
			return 1; }
		$action_id = $this->scheduler->schedule_single( max( 1, $timestamp ), self::WORK_HOOK, $args, self::GROUP, true );
		if ( $action_id < 1 ) {
			throw new SchedulerUnavailableException( 'Action Scheduler did not accept the job.' ); }
		return $action_id;
	}
}
