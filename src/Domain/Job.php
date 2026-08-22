<?php
/**
 * Virtual try-on job aggregate.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderResult;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop

/**
 * Owns the legal job state transitions and privacy-sensitive references.
 */
final class Job {

	public const SNAPSHOT_VERSION   = 1;
	public const DISPATCH_PENDING   = 'pending';
	public const DISPATCH_STARTED   = 'started';
	public const DISPATCH_COMPLETED = 'completed';

	/**
	 * Job identifier.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Stable owner hash.
	 *
	 * @var string
	 */
	private $owner_hash;

	/** @var string One-way user/guest quota identity key. */
	private $quota_identity_key;

	/**
	 * Idempotency fingerprint.
	 *
	 * @var string
	 */
	private $idempotency_fingerprint;

	/**
	 * Product identifier.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Optional variation identifier.
	 *
	 * @var int|null
	 */
	private $variation_id;

	/**
	 * Provider slug.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Experience type.
	 *
	 * @var ExperienceType
	 */
	private $experience_type;

	/**
	 * Composed prompt.
	 *
	 * @var string
	 */
	private $prompt;

	/**
	 * Private customer image reference.
	 *
	 * @var string
	 */
	private $customer_image_reference;

	/**
	 * Private product image reference.
	 *
	 * @var string
	 */
	private $product_image_reference;

	/**
	 * Private result reference.
	 *
	 * @var string|null
	 */
	private $result_reference;

	/**
	 * Result MIME type.
	 *
	 * @var string|null
	 */
	private $result_mime_type;

	/**
	 * Result byte count.
	 *
	 * @var int|null
	 */
	private $result_byte_size;

	/**
	 * Normalized terminal error.
	 *
	 * @var ProviderError|null
	 */
	private $error;

	/**
	 * Current job status.
	 *
	 * @var JobStatus
	 */
	private $status;

	/**
	 * Creation time.
	 *
	 * @var DateTimeImmutable
	 */
	private $created_at;

	/**
	 * Expiration time.
	 *
	 * @var DateTimeImmutable
	 */
	private $expires_at;

	/**
	 * Provider processing start time.
	 *
	 * @var DateTimeImmutable|null
	 */
	private $processing_at;

	/**
	 * Completion time.
	 *
	 * @var DateTimeImmutable|null
	 */
	private $completed_at;

	/** @var int Optimistic persistence revision. */
	private $revision;

	/** @var int Zero-based provider attempt expected by the worker. */
	private $dispatch_attempt;

	/** @var string Persisted per-attempt dispatch ledger state. */
	private $dispatch_state;

	/**
	 * Use create() or a repository hydrator to construct jobs.
	 */
	private function __construct() {
	}

	/**
	 * Create a queued job.
	 *
	 * @param string            $id                      Opaque CSPRNG job ID.
	 * @param string            $idempotency_fingerprint SHA-256 fingerprint.
	 * @param CreateJobRequest  $request                 Validated request.
	 * @param DateTimeImmutable $created_at              Creation time.
	 * @param DateTimeImmutable $expires_at              Expiry time.
	 * @return self
	 * @throws InvalidArgumentException When identity or timing values are invalid.
	 */
	public static function create(
		string $id,
		string $idempotency_fingerprint,
		CreateJobRequest $request,
		DateTimeImmutable $created_at,
		DateTimeImmutable $expires_at
	): self {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $id ) ) {
			throw new InvalidArgumentException( 'Job ID must be a 128-bit or stronger hexadecimal identifier.' );
		}

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $idempotency_fingerprint ) ) {
			throw new InvalidArgumentException( 'Idempotency fingerprint must be SHA-256 hexadecimal.' );
		}

		if ( $expires_at <= $created_at ) {
			throw new InvalidArgumentException( 'Job expiry must be after its creation time.' );
		}

		$job                           = new self();
		$job->id                       = $id;
		$job->owner_hash               = $request->owner_hash();
		$job->quota_identity_key       = $request->quota_identity_key();
		$job->idempotency_fingerprint  = $idempotency_fingerprint;
		$job->product_id               = $request->product_id();
		$job->variation_id             = $request->variation_id();
		$job->provider                 = $request->provider();
		$job->experience_type          = $request->experience_type();
		$job->prompt                   = $request->prompt();
		$job->customer_image_reference = $request->customer_image_reference();
		$job->product_image_reference  = $request->product_image_reference();
		$job->result_reference         = null;
		$job->result_mime_type         = null;
		$job->result_byte_size         = null;
		$job->error                    = null;
		$job->status                   = JobStatus::from_string( JobStatus::QUEUED );
		$job->created_at               = $created_at;
		$job->expires_at               = $expires_at;
		$job->processing_at            = null;
		$job->completed_at             = null;
		$job->revision                 = 0;
		$job->dispatch_attempt         = 0;
		$job->dispatch_state           = self::DISPATCH_PENDING;

		return $job;
	}

	/**
	 * Reconstitute a repository-validated snapshot without PHP object unserialization.
	 *
	 * @param array<string,mixed> $data Strict versioned snapshot.
	 * @return self
	 */
	public static function from_snapshot( array $data ): self {
		$required = array(
			'version',
			'revision',
			'id',
			'owner_hash',
			'quota_identity_key',
			'idempotency_fingerprint',
			'product_id',
			'variation_id',
			'provider',
			'experience_type',
			'prompt',
			'customer_image_reference',
			'product_image_reference',
			'result_reference',
			'result_mime_type',
			'result_byte_size',
			'error',
			'status',
			'created_at',
			'expires_at',
			'processing_at',
			'completed_at',
			'dispatch_attempt',
			'dispatch_state',
		);
		$keys     = array_keys( $data );
		sort( $keys );
		$expected = $required;
		sort( $expected );
		if ( $keys !== $expected || self::SNAPSHOT_VERSION !== $data['version'] ) {
			throw new InvalidArgumentException( 'Job snapshot schema is invalid.' );
		}

		if ( ! is_int( $data['revision'] ) || $data['revision'] < 1 || ! is_int( $data['dispatch_attempt'] ) || $data['dispatch_attempt'] < 0 || $data['dispatch_attempt'] > 2 ) {
			throw new InvalidArgumentException( 'Job snapshot revision or attempt is invalid.' );
		}
		foreach ( array( 'id', 'owner_hash', 'quota_identity_key', 'idempotency_fingerprint', 'provider', 'experience_type', 'prompt', 'customer_image_reference', 'product_image_reference', 'status', 'created_at', 'expires_at', 'dispatch_state' ) as $string_key ) {
			if ( ! is_string( $data[ $string_key ] ) ) {
				throw new InvalidArgumentException( 'Job snapshot contains an invalid scalar.' );
			}
		}
		if ( ! is_int( $data['product_id'] ) || ( null !== $data['variation_id'] && ! is_int( $data['variation_id'] ) ) ) {
			throw new InvalidArgumentException( 'Job snapshot product identity is invalid.' );
		}
		foreach ( array( 'result_reference', 'result_mime_type', 'processing_at', 'completed_at' ) as $nullable_string ) {
			if ( null !== $data[ $nullable_string ] && ! is_string( $data[ $nullable_string ] ) ) {
				throw new InvalidArgumentException( 'Job snapshot contains an invalid nullable scalar.' );
			}
		}
		if ( null !== $data['result_byte_size'] && ! is_int( $data['result_byte_size'] ) ) {
			throw new InvalidArgumentException( 'Job snapshot result size is invalid.' );
		}
		if ( ! in_array( $data['dispatch_state'], array( self::DISPATCH_PENDING, self::DISPATCH_STARTED, self::DISPATCH_COMPLETED ), true ) ) {
			throw new InvalidArgumentException( 'Job dispatch state is invalid.' );
		}

		$created_at                    = self::parse_snapshot_date( $data['created_at'] );
		$expires_at                    = self::parse_snapshot_date( $data['expires_at'] );
		$request                       = new CreateJobRequest(
			$data['owner_hash'],
			str_repeat( 'x', 16 ),
			$data['product_id'],
			$data['variation_id'],
			$data['provider'],
			ExperienceType::from_string( $data['experience_type'] ),
			$data['prompt'],
			'' === $data['customer_image_reference'] ? 'cleared/customer' : $data['customer_image_reference'],
			'' === $data['product_image_reference'] ? 'cleared/product' : $data['product_image_reference'],
			$data['quota_identity_key']
		);
		$job                           = self::create( $data['id'], $data['idempotency_fingerprint'], $request, $created_at, $expires_at );
		$job->customer_image_reference = $data['customer_image_reference'];
		$job->product_image_reference  = $data['product_image_reference'];
		$job->result_reference         = $data['result_reference'];
		$job->result_mime_type         = $data['result_mime_type'];
		$job->result_byte_size         = $data['result_byte_size'];
		$job->error                    = self::error_from_snapshot( $data['error'] );
		$job->status                   = JobStatus::from_string( $data['status'] );
		$job->processing_at            = null === $data['processing_at'] ? null : self::parse_snapshot_date( $data['processing_at'] );
		$job->completed_at             = null === $data['completed_at'] ? null : self::parse_snapshot_date( $data['completed_at'] );
		$job->revision                 = $data['revision'];
		$job->dispatch_attempt         = $data['dispatch_attempt'];
		$job->dispatch_state           = $data['dispatch_state'];

		return $job;
	}

	/** @return array<string,mixed> Canonical JSON-safe persistence snapshot. */
	public function snapshot(): array {
		return array(
			'version'                  => self::SNAPSHOT_VERSION,
			'revision'                 => $this->revision,
			'id'                       => $this->id,
			'owner_hash'               => $this->owner_hash,
			'quota_identity_key'       => $this->quota_identity_key,
			'idempotency_fingerprint'  => $this->idempotency_fingerprint,
			'product_id'               => $this->product_id,
			'variation_id'             => $this->variation_id,
			'provider'                 => $this->provider,
			'experience_type'          => $this->experience_type->value(),
			'prompt'                   => $this->prompt,
			'customer_image_reference' => $this->customer_image_reference,
			'product_image_reference'  => $this->product_image_reference,
			'result_reference'         => $this->result_reference,
			'result_mime_type'         => $this->result_mime_type,
			'result_byte_size'         => $this->result_byte_size,
			'error'                    => null === $this->error ? null : array(
				'code'                 => $this->error->code(),
				'message'              => $this->error->message(),
				'retryable'            => $this->error->is_retryable(),
				'retry_after_seconds'  => $this->error->retry_after_seconds(),
				'http_status'          => $this->error->http_status(),
				'diagnostic_reference' => $this->error->diagnostic_reference(),
			),
			'status'                   => $this->status->value(),
			'created_at'               => $this->created_at->format( DATE_ATOM ),
			'expires_at'               => $this->expires_at->format( DATE_ATOM ),
			'processing_at'            => null === $this->processing_at ? null : $this->processing_at->format( DATE_ATOM ),
			'completed_at'             => null === $this->completed_at ? null : $this->completed_at->format( DATE_ATOM ),
			'dispatch_attempt'         => $this->dispatch_attempt,
			'dispatch_state'           => $this->dispatch_state,
		);
	}

	/** Mark a repository-assigned revision after an atomic write. */
	public function mark_persisted_revision( int $revision ): void {
		if ( $revision < 1 ) {
			throw new InvalidArgumentException( 'Job revision must be positive.' );
		}
		$this->revision = $revision;
	}

	/** Atomically-persisted worker claim made before provider side effects. */
	public function claim_dispatch( int $attempt ): void {
		if ( JobStatus::PROCESSING !== $this->status->value() || $attempt !== $this->dispatch_attempt || self::DISPATCH_PENDING !== $this->dispatch_state ) {
			throw new DomainException( 'The provider attempt cannot be claimed.' );
		}
		$this->dispatch_state = self::DISPATCH_STARTED;
	}

	/** Prepare a bounded retry after a completed retryable provider response. */
	public function prepare_retry( int $next_attempt ): void {
		if ( JobStatus::PROCESSING !== $this->status->value() || self::DISPATCH_STARTED !== $this->dispatch_state || $next_attempt !== $this->dispatch_attempt + 1 || $next_attempt > 2 ) {
			throw new DomainException( 'The provider retry cannot be prepared.' );
		}
		$this->dispatch_attempt = $next_attempt;
		$this->dispatch_state   = self::DISPATCH_PENDING;
	}

	/** Clear private input references after terminal completion. */
	public function clear_input_references(): void {
		$this->customer_image_reference = '';
		$this->product_image_reference  = '';
	}

	/** Current optimistic revision. */
	public function revision(): int {
		return $this->revision; }
	/** Current expected worker attempt. */
	public function dispatch_attempt(): int {
		return $this->dispatch_attempt; }
	/** Current per-attempt ledger state. */
	public function dispatch_state(): string {
		return $this->dispatch_state; }

	/** @param mixed $value Encoded error. */
	private static function error_from_snapshot( $value ): ?ProviderError {
		if ( null === $value ) {
			return null; }
		$keys = is_array( $value ) ? array_keys( $value ) : array();
		sort( $keys );
		$expected = array( 'code', 'diagnostic_reference', 'http_status', 'message', 'retry_after_seconds', 'retryable' );
		sort( $expected );
		if ( ! is_array( $value ) || $keys !== $expected || ! is_string( $value['code'] ) || ! is_string( $value['message'] ) || ! is_bool( $value['retryable'] ) || ( null !== $value['retry_after_seconds'] && ! is_int( $value['retry_after_seconds'] ) ) || ( null !== $value['http_status'] && ! is_int( $value['http_status'] ) ) || ( null !== $value['diagnostic_reference'] && ! is_string( $value['diagnostic_reference'] ) ) ) {
			throw new InvalidArgumentException( 'Job snapshot error is invalid.' );
		}
		return new ProviderError( $value['code'], $value['message'], $value['retryable'], $value['retry_after_seconds'], $value['http_status'], $value['diagnostic_reference'] );
	}

	private static function parse_snapshot_date( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( DATE_ATOM, $value );
		if ( false === $date || $date->format( DATE_ATOM ) !== $value ) {
			throw new InvalidArgumentException( 'Job snapshot timestamp is invalid.' );
		}
		return $date;
	}

	/**
	 * Move a queued job into provider processing.
	 *
	 * @param DateTimeImmutable $at Transition time.
	 * @return void
	 */
	public function start_processing( DateTimeImmutable $at ): void {
		$this->transition( JobStatus::PROCESSING, $at );
		$this->processing_at = $at;
	}

	/**
	 * Complete a processing job with one private image result.
	 *
	 * @param ProviderResult    $result Validated private result.
	 * @param DateTimeImmutable $at     Transition time.
	 * @return void
	 */
	public function succeed( ProviderResult $result, DateTimeImmutable $at ): void {
		$this->transition( JobStatus::SUCCEEDED, $at );
		$this->result_reference = $result->result_reference();
		$this->result_mime_type = $result->mime_type();
		$this->result_byte_size = $result->byte_size();
		$this->error            = null;
		$this->completed_at     = $at;
	}

	/**
	 * Fail a queued or processing job with a normalized error.
	 *
	 * @param ProviderError     $error Normalized safe error.
	 * @param DateTimeImmutable $at    Transition time.
	 * @return void
	 */
	public function fail( ProviderError $error, DateTimeImmutable $at ): void {
		$this->transition( JobStatus::FAILED, $at );
		$this->error        = $error;
		$this->completed_at = $at;
	}

	/**
	 * Cancel a queued or processing job.
	 *
	 * @param DateTimeImmutable $at Transition time.
	 * @return void
	 */
	public function cancel( DateTimeImmutable $at ): void {
		$this->transition( JobStatus::CANCELLED, $at );
		$this->completed_at = $at;
	}

	/**
	 * Expire a job and clear all private storage references.
	 *
	 * @param DateTimeImmutable $at Transition time.
	 * @return void
	 */
	public function expire( DateTimeImmutable $at ): void {
		$this->transition( JobStatus::EXPIRED, $at );
		$this->customer_image_reference = '';
		$this->product_image_reference  = '';
		$this->result_reference         = null;
		$this->result_mime_type         = null;
		$this->result_byte_size         = null;
		$this->completed_at             = $at;
	}

	/**
	 * Apply and validate a status transition.
	 *
	 * @param string            $next_value Next status value.
	 * @param DateTimeImmutable $at         Transition time.
	 * @return void
	 * @throws DomainException When the transition or timestamp is illegal.
	 */
	private function transition( string $next_value, DateTimeImmutable $at ): void {
		$next = JobStatus::from_string( $next_value );

		if ( ! $this->status->can_transition_to( $next ) ) {
			throw new DomainException(
				sprintf( 'Illegal job status transition from %s to %s.', $this->status->value(), $next->value() ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		if ( $at < $this->created_at ) {
			throw new DomainException( 'Job transition cannot precede creation.' );
		}

		$this->status = $next;
	}

	/** Get the job identifier. */
	public function id(): string {
		return $this->id;
	}

	/** Get the stable owner hash. */
	public function owner_hash(): string {
		return $this->owner_hash;
	}

	/** Get the one-way persisted quota identity key. */
	public function quota_identity_key(): string {
		return $this->quota_identity_key; }

	/** Get the idempotency fingerprint. */
	public function idempotency_fingerprint(): string {
		return $this->idempotency_fingerprint;
	}

	/** Get the product identifier. */
	public function product_id(): int {
		return $this->product_id;
	}

	/** Get the optional variation identifier. */
	public function variation_id(): ?int {
		return $this->variation_id;
	}

	/** Get the provider slug. */
	public function provider(): string {
		return $this->provider;
	}

	/** Get the experience type. */
	public function experience_type(): ExperienceType {
		return $this->experience_type;
	}

	/** Get the composed prompt. */
	public function prompt(): string {
		return $this->prompt;
	}

	/** Get the private customer image reference. */
	public function customer_image_reference(): string {
		return $this->customer_image_reference;
	}

	/** Get the private product image reference. */
	public function product_image_reference(): string {
		return $this->product_image_reference;
	}

	/** Get the private result reference. */
	public function result_reference(): ?string {
		return $this->result_reference;
	}

	/** Get the result MIME type. */
	public function result_mime_type(): ?string {
		return $this->result_mime_type;
	}

	/** Get the result byte count. */
	public function result_byte_size(): ?int {
		return $this->result_byte_size;
	}

	/** Get the normalized terminal error. */
	public function error(): ?ProviderError {
		return $this->error;
	}

	/** Get the current status. */
	public function status(): JobStatus {
		return $this->status;
	}

	/** Get the creation time. */
	public function created_at(): DateTimeImmutable {
		return $this->created_at;
	}

	/** Get the expiration time. */
	public function expires_at(): DateTimeImmutable {
		return $this->expires_at;
	}

	/** Get the provider processing start time. */
	public function processing_at(): ?DateTimeImmutable {
		return $this->processing_at;
	}

	/** Get the completion time. */
	public function completed_at(): ?DateTimeImmutable {
		return $this->completed_at;
	}
}
