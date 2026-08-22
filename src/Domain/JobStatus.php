<?php
/**
 * Job status value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * PHP 7.4-compatible job state enum and transition policy.
 */
final class JobStatus {

	public const QUEUED     = 'queued';
	public const PROCESSING = 'processing';
	public const SUCCEEDED  = 'succeeded';
	public const FAILED     = 'failed';
	public const CANCELLED  = 'cancelled';
	public const EXPIRED    = 'expired';

	/**
	 * Serialized status value.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Construct a validated status.
	 *
	 * @param string $value Valid status.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * Create a validated status.
	 *
	 * @param string $value Raw status.
	 * @return self
	 * @throws InvalidArgumentException When the status is unsupported.
	 */
	public static function from_string( string $value ): self {
		$value = strtolower( trim( $value ) );

		if ( ! in_array( $value, self::values(), true ) ) {
			throw new InvalidArgumentException( 'Unsupported job status.' );
		}

		return new self( $value );
	}

	/**
	 * Return all serialized values.
	 *
	 * @return string[]
	 */
	public static function values(): array {
		return array( self::QUEUED, self::PROCESSING, self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED );
	}

	/** Get the serialized status value. */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Compare this status with another status.
	 *
	 * @param self $other Other status.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Whether no more provider processing may occur.
	 *
	 * Expiry remains an allowed lifecycle cleanup transition for completed jobs.
	 *
	 * @return bool
	 */
	public function is_terminal(): bool {
		return in_array( $this->value, array( self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED ), true );
	}

	/**
	 * Whether a transition to another state is legal.
	 *
	 * @param self $next Proposed status.
	 * @return bool
	 */
	public function can_transition_to( self $next ): bool {
		$allowed = array(
			self::QUEUED     => array( self::PROCESSING, self::FAILED, self::CANCELLED, self::EXPIRED ),
			self::PROCESSING => array( self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED ),
			self::SUCCEEDED  => array( self::EXPIRED ),
			self::FAILED     => array( self::EXPIRED ),
			self::CANCELLED  => array( self::EXPIRED ),
			self::EXPIRED    => array(),
		);

		return in_array( $next->value, $allowed[ $this->value ], true );
	}
}
