<?php
/**
 * Idempotency key value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Validates a client key and exposes only its non-reversible fingerprint.
 */
final class IdempotencyKey {

	/**
	 * SHA-256 key fingerprint.
	 *
	 * @var string
	 */
	private $fingerprint;

	/**
	 * Create a safe key fingerprint.
	 *
	 * @param string $raw_key Raw client idempotency key.
	 * @throws InvalidArgumentException When the key format is invalid.
	 */
	public function __construct( string $raw_key ) {
		$key_length = strlen( $raw_key );

		if ( $key_length < 16 || $key_length > 128 ) {
			throw new InvalidArgumentException( 'Idempotency key must contain 16 to 128 characters.' );
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/D', $raw_key ) ) {
			throw new InvalidArgumentException( 'Idempotency key contains unsupported characters.' );
		}

		$this->fingerprint = hash( 'sha256', $raw_key );
	}

	/**
	 * Return the non-reversible storage fingerprint.
	 *
	 * @return string
	 */
	public function fingerprint(): string {
		return $this->fingerprint;
	}
}
