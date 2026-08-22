<?php
/**
 * Provider request DTO.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\DTO;

use InvalidArgumentException;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable normalized request passed to an image provider adapter.
 */
final class ProviderRequest {

	/**
	 * Construct a validated provider request.
	 *
	 * Job identifier.
	 *
	 * @var string
	 */
	private $job_id;

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
	 * Composed prompt.
	 *
	 * @var string
	 */
	private $prompt;

	/**
	 * Experience type.
	 *
	 * @var ExperienceType
	 */
	private $experience_type;

	/**
	 * Provider quality.
	 *
	 * @var string
	 */
	private $quality;

	/**
	 * Output size.
	 *
	 * @var string
	 */
	private $size;

	/**
	 * Construct a validated provider request.
	 *
	 * @param string         $job_id                   Job identifier.
	 * @param string         $customer_image_reference Private customer/scene image reference.
	 * @param string         $product_image_reference  Private product image reference.
	 * @param string         $prompt                   Composed prompt.
	 * @param ExperienceType $experience_type          Experience type.
	 * @param string         $quality                  Normalized quality.
	 * @param string         $size                     Normalized output size.
	 * @throws InvalidArgumentException When a value violates the contract.
	 */
	public function __construct(
		string $job_id,
		string $customer_image_reference,
		string $product_image_reference,
		string $prompt,
		ExperienceType $experience_type,
		string $quality,
		string $size = 'auto'
	) {
		$quality = strtolower( trim( $quality ) );
		$size    = strtolower( trim( $size ) );

		if ( '' === trim( $job_id ) || '' === trim( $customer_image_reference ) || '' === trim( $product_image_reference ) ) {
			throw new InvalidArgumentException( 'Job ID and both image references are required.' );
		}

		if ( '' === trim( $prompt ) ) {
			throw new InvalidArgumentException( 'Prompt must not be empty.' );
		}

		if ( ! in_array( $quality, array( 'auto', 'low', 'medium', 'high' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported quality.' );
		}

		if ( ! in_array( $size, array( 'auto', '1024x1024', '1536x1024', '1024x1536', '2048x2048' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported output size.' );
		}

		$this->job_id                   = trim( $job_id );
		$this->customer_image_reference = trim( $customer_image_reference );
		$this->product_image_reference  = trim( $product_image_reference );
		$this->prompt                   = trim( $prompt );
		$this->experience_type          = $experience_type;
		$this->quality                  = $quality;
		$this->size                     = $size;
	}

	/** Get the job identifier. */
	public function job_id(): string {
		return $this->job_id;
	}

	/** Get the private customer image reference. */
	public function customer_image_reference(): string {
		return $this->customer_image_reference;
	}

	/** Get the private product image reference. */
	public function product_image_reference(): string {
		return $this->product_image_reference;
	}

	/** Get the composed prompt. */
	public function prompt(): string {
		return $this->prompt;
	}

	/** Get the experience type. */
	public function experience_type(): ExperienceType {
		return $this->experience_type;
	}

	/** Get the normalized quality. */
	public function quality(): string {
		return $this->quality;
	}

	/** Get the normalized output size. */
	public function size(): string {
		return $this->size;
	}
}
