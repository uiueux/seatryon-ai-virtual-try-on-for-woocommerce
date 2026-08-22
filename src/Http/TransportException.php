<?php
/**
 * HTTP transport exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Reports a stable transport reason without retaining a secret request body.
 */
final class TransportException extends RuntimeException {

	/**
	 * Stable failure reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Retry classification.
	 *
	 * @var bool
	 */
	private $retryable;

	/**
	 * Set up a sanitized transport failure.
	 *
	 * @param string         $reason    Stable reason.
	 * @param string         $message   Safe message.
	 * @param bool           $retryable Whether a queue retry may be attempted.
	 * @param Throwable|null $previous  Previous exception.
	 */
	public function __construct( string $reason, string $message, bool $retryable = false, ?Throwable $previous = null ) {
		$this->reason    = $reason;
		$this->retryable = $retryable;

		parent::__construct( $message, 0, $previous );
	}

	/** Return the stable reason. */
	public function reason(): string {
		return $this->reason;
	}

	/** Determine whether a queue retry may be attempted. */
	public function is_retryable(): bool {
		return $this->retryable;
	}
}
