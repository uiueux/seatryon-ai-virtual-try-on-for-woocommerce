<?php
/**
 * Background job worker.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use Throwable;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Contracts\JobRepositoryInterface;
use SeaTryOn\Domain\Job;
use SeaTryOn\Domain\JobStatus;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Provider\ProviderRuntime;
use SeaTryOn\Provider\ProviderRuntimeFactoryInterface;
use SeaTryOn\Quota\QuotaException;
use SeaTryOn\Quota\QuotaIdentity;
use SeaTryOn\Quota\QuotaService;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Scheduler\SchedulerUnavailableException;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

/** Idempotent Action Scheduler callback with persisted dispatch-attempt ledger. */
final class JobWorker {
	private const LOCK_TTL = 300;
	/** @var JobRepositoryInterface */ private $repository;
	/** @var ClockInterface */ private $clock;
	/** @var LockInterface */ private $lock;
	/** @var QuotaService */ private $quota;
	/** @var SettingsRepository */ private $settings;
	/** @var callable */ private $runtime_factory;
	/** @var JobScheduler */ private $scheduler;
	/** @var TemporaryStorageInterface */ private $storage;
	/** @var SuccessCounter */ private $counter;
	/** @var Logger */ private $logger;
	/** @var callable */ private $jitter;

	public function __construct(
		JobRepositoryInterface $repository,
		ClockInterface $clock,
		LockInterface $lock,
		QuotaService $quota,
		SettingsRepository $settings,
		ProviderRuntimeFactoryInterface $provider_factory,
		JobScheduler $scheduler,
		TemporaryStorageInterface $storage,
		?SuccessCounter $counter = null,
		?Logger $logger = null,
		?callable $jitter = null
	) {
		$this->repository      = $repository;
		$this->clock           = $clock;
		$this->lock            = $lock;
		$this->quota           = $quota;
		$this->settings        = $settings;
		$this->runtime_factory = array( $provider_factory, 'create_for_job' );
		$this->scheduler       = $scheduler;
		$this->storage         = $storage;
		$this->counter         = $counter ?? new SuccessCounter();
		$this->logger          = $logger ?? new Logger();
		$this->jitter          = $jitter ?? static function (): int {
			return random_int( 0, 10 );
		};
	}

	/** Action args must be registered with accepted_args=2. */
	public function handle( string $job_id, int $attempt = 0 ): void {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) || $attempt < 0 || $attempt > 2 ) {
			return; }
		$handle = $this->lock->acquire( 'job-worker:' . $job_id, self::LOCK_TTL );
		if ( null === $handle ) {
			return; }
		try {
			$this->process_under_lock( $job_id, $attempt ); } finally {
			$this->lock->release( $handle ); }
	}

	private function process_under_lock( string $job_id, int $attempt ): void {
		$job = $this->repository->find_by_id( $job_id );
		if ( null === $job || $job->status()->is_terminal() ) {
			return; }
		if ( $job->expires_at() <= $this->clock->now() ) {
			$this->expire_job( $job );
			return; }
		if ( JobStatus::QUEUED === $job->status()->value() ) {
			$job->start_processing( $this->clock->now() );
			$this->repository->save( $job ); }
		if ( $attempt !== $job->dispatch_attempt() || Job::DISPATCH_PENDING !== $job->dispatch_state() ) {
			return; }

		$job->claim_dispatch( $attempt );
		$this->repository->save( $job ); // Durable barrier before quota/provider side effects.

		try {
			/** @var ProviderRuntime $runtime */
			$runtime = call_user_func( $this->runtime_factory, $job );
			if ( ! $runtime instanceof ProviderRuntime ) {
				throw new \RuntimeException( 'The selected provider runtime is invalid.' ); }
			$identity = QuotaIdentity::from_persisted_key( $job->quota_identity_key() );
			if ( ! $identity->is_quota_exempt() ) {
				$identities                  = array( $identity );
				$guest_ip_quota_identity_key = $job->guest_ip_quota_identity_key();
				if ( null !== $guest_ip_quota_identity_key ) {
					$guest_ip_identity = QuotaIdentity::from_persisted_key( $guest_ip_quota_identity_key );
					if ( $identity->is_user() || ! $guest_ip_identity->is_guest_ip() ) {
						throw new QuotaException( 'Stored guest IP quota identity is invalid.' );
					}
					$identities[] = $guest_ip_identity;
				}
				$limit        = $identity->is_user() ? $this->settings->get_logged_in_daily_limit() : $this->settings->get_guest_daily_limit();
				$quota_result = $this->quota->consume_for_dispatches( $identities, $job->id(), $limit );
				if ( ! $quota_result->is_allowed() ) {
					$this->terminal_failure( $job, new ProviderError( 'quota_exceeded', 'The daily generation limit has been reached.', false ) );
					return;
				}
			}
			$result = $runtime->provider()->generate( $runtime->request() );
			$inputs = array( $job->customer_image_reference(), $job->product_image_reference() );
			$job->succeed( $result, $this->clock->now() );
			$job->clear_input_references();
			$this->repository->save( $job );
			$this->delete_references( $inputs );
			$this->counter->increment_once( $job->id() );
		} catch ( ProviderException $exception ) {
			$this->handle_provider_error( $job, $attempt, $exception->provider_error() );
		} catch ( QuotaException $exception ) {
			$this->terminal_failure( $job, new ProviderError( 'quota_unavailable', 'The generation quota could not be reserved.', false ) );
		} catch ( Throwable $exception ) {
			$this->logger->error(
				'The job worker encountered a safe internal failure.',
				array(
					'job_id'  => $job->id(),
					'attempt' => $attempt,
				)
			);
			$this->terminal_failure( $job, new ProviderError( 'generation_failed', 'The preview could not be generated.', false ) );
		}
	}

	private function handle_provider_error( Job $job, int $attempt, ProviderError $error ): void {
		if ( $error->is_retryable() && $attempt < $this->maximum_retry_attempt( $error ) ) {
			$next  = $attempt + 1;
			$delay = $error->retry_after_seconds();
			if ( null === $delay ) {
				$delay = min( 3600, 30 * ( 2 ** $attempt ) + max( 0, min( 10, (int) call_user_func( $this->jitter ) ) ) ); }
			try {
				$job->prepare_retry( $next );
				$this->repository->save( $job );
				$this->scheduler->schedule_retry( $job->id(), $next, $this->clock->now()->getTimestamp() + max( 1, $delay ) );
				return;
			} catch ( SchedulerUnavailableException | ConcurrentJobWriteException $exception ) {
				$error = new ProviderError( 'scheduler_unavailable', 'The background job scheduler is unavailable.', false );
			}
		}
		$this->terminal_failure( $job, $error );
	}

	private function terminal_failure( Job $job, ProviderError $error ): void {
		if ( $job->status()->is_terminal() ) {
			return; }
		$context = array(
			'job_id'                 => $job->id(),
			'dispatch_attempt'       => $job->dispatch_attempt(),
			'provider_error_code'    => $error->code(),
			'provider_error_message' => $error->message(),
			'provider_http_status'   => $error->http_status(),
			'diagnostic_reference'   => $error->diagnostic_reference(),
		);
		if ( 'quota_exceeded' === $error->code() ) {
			$this->logger->warning( 'Virtual Try-On job reached a terminal limit.', $context );
		} else {
			$this->logger->error( 'Virtual Try-On job failed.', $context );
		}
		$references = array( $job->customer_image_reference(), $job->product_image_reference() );
		$job->fail( $error, $this->clock->now() );
		$job->clear_input_references();
		$this->repository->save( $job );
		$this->delete_references( $references );
	}

	private function expire_job( Job $job ): void {
		$references = array( $job->customer_image_reference(), $job->product_image_reference(), $job->result_reference() );
		$job->expire( $this->clock->now() );
		$this->repository->save( $job );
		$this->scheduler->cancel_job( $job->id() );
		$this->delete_references( $references );
	}

	private function maximum_retry_attempt( ProviderError $error ): int {
		$code = $error->code();
		return false !== strpos( $code, 'timeout' ) || false !== strpos( $code, 'invalid_response' ) || false !== strpos( $code, 'contract_error' ) ? 1 : 2;
	}

	/** @param array<?string> $references */
	private function delete_references( array $references ): void {
		foreach ( array_unique( $references ) as $reference ) {
			if ( is_string( $reference ) && '' !== $reference ) {
				$this->storage->delete( $reference ); }
		}
	}
}
