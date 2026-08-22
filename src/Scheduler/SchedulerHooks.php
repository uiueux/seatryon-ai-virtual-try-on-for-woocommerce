<?php
/**
 * Scheduler hook registrar.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Scheduler;

use SeaTryOn\Job\JobCleanupService;
use SeaTryOn\Job\JobWorker;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** WordPress hook registration kept separate from service construction. */
final class SchedulerHooks {
	/** @var JobWorker */ private $worker;
	/** @var JobCleanupService */ private $cleanup;
	public function __construct( JobWorker $worker, JobCleanupService $cleanup ) {
		$this->worker  = $worker;
		$this->cleanup = $cleanup; }
	public function register(): void {
		add_action( JobScheduler::WORK_HOOK, array( $this->worker, 'handle' ), 10, 2 );
		add_action( JobScheduler::CLEANUP_HOOK, array( $this, 'handle_cleanup' ) );
		add_action( 'action_scheduler_init', array( $this, 'ensure_cleanup' ) );
		if ( function_exists( 'did_action' ) && did_action( 'action_scheduler_init' ) > 0 ) {
			$this->ensure_cleanup();
		}
	}

	/** Run bounded cleanup without returning a value to WordPress. */
	public function handle_cleanup(): void {
		$this->cleanup->cleanup_expired();
	}

	/** Ensure automatic hourly TTL cleanup exists. */
	public function ensure_cleanup(): void {
		try {
			$this->cleanup_scheduler()->ensure_cleanup( time() + 60 );
		} catch ( SchedulerUnavailableException $exception ) {
			// WooCommerce dependency notices handle a missing scheduler fail-safe.
			unset( $exception );
		}
	}

	/** Resolve the scheduler already owned by the cleanup service. */
	private function cleanup_scheduler(): JobScheduler {
		return $this->cleanup->scheduler();
	}
}
