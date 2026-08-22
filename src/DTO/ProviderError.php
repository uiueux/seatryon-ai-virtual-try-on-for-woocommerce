<?php
/**
 * Normalized provider error DTO.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\DTO;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Carries stable, safe provider failure information across layers.
 */
final class ProviderError {

	/**
	 * Construct a normalized provider error.
	 *
	 * Stable plugin error code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Safe English message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Whether bounded retry is permitted.
	 *
	 * @var bool
	 */
	private $retryable;

	/**
	 * Optional retry delay.
	 *
	 * @var int|null
	 */
	private $retry_after_seconds;

	/**
	 * Optional provider HTTP status.
	 *
	 * @var int|null
	 */
	private $http_status;

	/**
	 * Optional allow-listed provider reference for internal diagnostics.
	 *
	 * @var string|null
	 */
	private $diagnostic_reference;

	/**
	 * Construct a normalized provider error.
	 *
	 * @param string      $code                Stable plugin error code.
	 * @param string      $message             Safe English diagnostic message.
	 * @param bool        $retryable           Whether a bounded retry is allowed.
	 * @param int|null    $retry_after_seconds Retry delay hint.
	 * @param int|null    $http_status         Provider HTTP status.
	 * @param string|null $diagnostic_reference Safe provider reference retained only for internal diagnostics.
	 * @throws InvalidArgumentException When error data violates the contract.
	 */
	public function __construct(
		string $code,
		string $message,
		bool $retryable,
		?int $retry_after_seconds = null,
		?int $http_status = null,
		?string $diagnostic_reference = null
	) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $code ) ) {
			throw new InvalidArgumentException( 'Provider error code is invalid.' );
		}

		if ( '' === trim( $message ) || strlen( $message ) > 500 ) {
			throw new InvalidArgumentException( 'Provider error message must contain 1 to 500 bytes.' );
		}

		if ( null !== $retry_after_seconds && ( ! $retryable || $retry_after_seconds < 0 || $retry_after_seconds > 86400 ) ) {
			throw new InvalidArgumentException( 'Retry delay is invalid.' );
		}

		if ( null !== $http_status && ( $http_status < 100 || $http_status > 599 ) ) {
			throw new InvalidArgumentException( 'HTTP status is invalid.' );
		}

		if ( null !== $diagnostic_reference && 1 !== preg_match( '/^[A-Za-z0-9._:-]{1,128}$/', $diagnostic_reference ) ) {
			throw new InvalidArgumentException( 'Provider diagnostic reference is invalid.' );
		}

		$this->code                 = $code;
		$this->message              = trim( $message );
		$this->retryable            = $retryable;
		$this->retry_after_seconds  = $retry_after_seconds;
		$this->http_status          = $http_status;
		$this->diagnostic_reference = $diagnostic_reference;
	}

	/** Get the stable error code. */
	public function code(): string {
		return $this->code;
	}

	/** Get the safe English message. */
	public function message(): string {
		return $this->message;
	}

	/** Whether bounded retry is permitted. */
	public function is_retryable(): bool {
		return $this->retryable;
	}

	/** Get the optional retry delay. */
	public function retry_after_seconds(): ?int {
		return $this->retry_after_seconds;
	}

	/** Get the optional provider HTTP status. */
	public function http_status(): ?int {
		return $this->http_status;
	}

	/** Get an allow-listed provider reference for internal diagnostics only. */
	public function diagnostic_reference(): ?string {
		return $this->diagnostic_reference;
	}
}
