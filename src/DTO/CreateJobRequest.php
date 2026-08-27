<?php
/**
 * Create-job request DTO.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\DTO;

use InvalidArgumentException;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag

/**
 * Carries validated job creation inputs into the domain service.
 */
final class CreateJobRequest {

	/**
	 * Construct a validated create-job request.
	 *
	 * Stable owner hash.
	 *
	 * @var string
	 */
	private $owner_hash;

	/**
	 * Raw client idempotency key.
	 *
	 * @var string
	 */
	private $idempotency_key;

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
	 * Selected experience type.
	 *
	 * @var ExperienceType
	 */
	private $experience_type;

	/**
	 * Composed provider prompt.
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

	/** @var string One-way user/guest quota key safe for asynchronous persistence. */
	private $quota_identity_key;

	/** @var string|null One-way anonymous IP quota key safe for asynchronous persistence. */
	private $guest_ip_quota_identity_key;

	/**
	 * Construct a validated create-job request.
	 *
	 * @param string         $owner_hash              Stable owner hash.
	 * @param string         $idempotency_key         Client-generated idempotency key.
	 * @param int            $product_id              Product identifier.
	 * @param int|null       $variation_id            Variation identifier.
	 * @param string         $provider                Provider slug.
	 * @param ExperienceType $experience_type         Experience type.
	 * @param string         $prompt                  Composed English prompt.
	 * @param string         $customer_image_reference Private customer image reference.
	 * @param string         $product_image_reference Private product image reference.
	 * @throws InvalidArgumentException When a value violates the contract.
	 */
	public function __construct(
		string $owner_hash,
		string $idempotency_key,
		int $product_id,
		?int $variation_id,
		string $provider,
		ExperienceType $experience_type,
		string $prompt,
		string $customer_image_reference,
		string $product_image_reference,
		?string $quota_identity_key = null,
		?string $guest_ip_quota_identity_key = null
	) {
		$provider = strtolower( trim( $provider ) );

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $owner_hash ) ) {
			throw new InvalidArgumentException( 'Owner hash must be a lowercase SHA-256 hexadecimal value.' );
		}

		if ( $product_id < 1 ) {
			throw new InvalidArgumentException( 'Product ID must be positive.' );
		}

		if ( null !== $variation_id && $variation_id < 1 ) {
			throw new InvalidArgumentException( 'Variation ID must be positive when provided.' );
		}

		if ( ! in_array( $provider, array( 'openai', 'seaai' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported provider.' );
		}

		if ( '' === trim( $prompt ) ) {
			throw new InvalidArgumentException( 'Prompt must not be empty.' );
		}

		if ( '' === trim( $customer_image_reference ) || '' === trim( $product_image_reference ) ) {
			throw new InvalidArgumentException( 'Both image references are required.' );
		}

		// Backward-compatible domain fallback. REST creation supplies the explicit
		// user/guest namespaced key returned by QuotaIdentity::key().
		$quota_identity_key = null === $quota_identity_key ? 'guest-' . hash( 'sha256', $owner_hash ) : trim( $quota_identity_key );
		if ( 1 !== preg_match( '/^(?:user|guest|unlimited)-[a-f0-9]{64}$/D', $quota_identity_key ) ) {
			throw new InvalidArgumentException( 'Quota identity key must be a one-way namespaced SHA-256 value.' );
		}

		if ( null !== $guest_ip_quota_identity_key ) {
			$guest_ip_quota_identity_key = trim( $guest_ip_quota_identity_key );
			if ( 1 !== preg_match( '/^guest-ip-[a-f0-9]{64}$/D', $guest_ip_quota_identity_key ) ) {
				throw new InvalidArgumentException( 'Guest IP quota identity key must be a one-way namespaced SHA-256 value.' );
			}
		}

		$this->owner_hash                  = $owner_hash;
		$this->idempotency_key             = $idempotency_key;
		$this->product_id                  = $product_id;
		$this->variation_id                = $variation_id;
		$this->provider                    = $provider;
		$this->experience_type             = $experience_type;
		$this->prompt                      = trim( $prompt );
		$this->customer_image_reference    = trim( $customer_image_reference );
		$this->product_image_reference     = trim( $product_image_reference );
		$this->quota_identity_key          = $quota_identity_key;
		$this->guest_ip_quota_identity_key = $guest_ip_quota_identity_key;
	}

	/** Get the stable owner hash. */
	public function owner_hash(): string {
		return $this->owner_hash;
	}

	/** Get the raw client idempotency key. */
	public function idempotency_key(): string {
		return $this->idempotency_key;
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

	/** Get the one-way persisted quota identity key. */
	public function quota_identity_key(): string {
		return $this->quota_identity_key;
	}

	/** Get the optional one-way guest-IP quota identity key. */
	public function guest_ip_quota_identity_key(): ?string {
		return $this->guest_ip_quota_identity_key;
	}
}
