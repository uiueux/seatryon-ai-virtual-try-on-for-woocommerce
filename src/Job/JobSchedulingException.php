<?php
/**
 * Safe queued-job scheduling exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Signals that a queued job and its original upload scope were retained.
 */
final class JobSchedulingException extends RuntimeException {
	/**
	 * Retained job identifier.
	 *
	 * @var string
	 */
	private $job_id;

	/**
	 * Hash of the retained private upload reference.
	 *
	 * @var string
	 */
	private $customer_reference_hash;

	/**
	 * Wrap an internal scheduler failure without exposing storage details.
	 *
	 * @param string    $job_id                   Opaque retained job ID.
	 * @param string    $customer_image_reference Private storage reference.
	 * @param Throwable $previous                 Original scheduler failure.
	 * @throws InvalidArgumentException When identifiers are invalid.
	 */
	public function __construct( string $job_id, string $customer_image_reference, Throwable $previous ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) || '' === $customer_image_reference ) {
			throw new InvalidArgumentException( 'Invalid retained job scheduling context.' );
		}
		parent::__construct( 'The queued job could not be scheduled.', 0, $previous );
		$this->job_id                  = $job_id;
		$this->customer_reference_hash = hash( 'sha256', $customer_image_reference );
	}

	/** Return the opaque retained job ID for internal diagnostics. */
	public function job_id(): string {
		return $this->job_id;
	}

	/**
	 * Determine whether a request upload belongs to the retained queued job.
	 *
	 * @param string $reference Private storage reference.
	 */
	public function owns_customer_image_reference( string $reference ): bool {
		return '' !== $reference && hash_equals( $this->customer_reference_hash, hash( 'sha256', $reference ) );
	}
}
