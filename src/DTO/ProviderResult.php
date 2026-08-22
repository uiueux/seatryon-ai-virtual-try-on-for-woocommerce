<?php
/**
 * Provider result DTO.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\DTO;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a validated image already stored in private temporary storage.
 */
final class ProviderResult {

	/**
	 * Construct a validated private result.
	 *
	 * Private result reference.
	 *
	 * @var string
	 */
	private $result_reference;

	/**
	 * Validated image MIME type.
	 *
	 * @var string
	 */
	private $mime_type;

	/**
	 * Validated byte count.
	 *
	 * @var int
	 */
	private $byte_size;

	/**
	 * Sanitized provider request identifier.
	 *
	 * @var string|null
	 */
	private $provider_request_id;

	/**
	 * Construct a validated private result.
	 *
	 * @param string      $result_reference   Private storage reference, never a public path or URL.
	 * @param string      $mime_type          Validated image MIME type.
	 * @param int         $byte_size          Validated byte count.
	 * @param string|null $provider_request_id Sanitized provider request identifier.
	 * @throws InvalidArgumentException When result data violates the contract.
	 */
	public function __construct( string $result_reference, string $mime_type, int $byte_size, ?string $provider_request_id = null ) {
		if ( '' === trim( $result_reference ) ) {
			throw new InvalidArgumentException( 'Result reference must not be empty.' );
		}

		if ( false !== strpos( $result_reference, '..' ) || 1 !== preg_match( '#^[A-Za-z0-9][A-Za-z0-9._/-]{0,255}$#D', $result_reference ) ) {
			throw new InvalidArgumentException( 'Result reference must be a safe storage-relative identifier.' );
		}

		if ( ! in_array( strtolower( $mime_type ), array( 'image/png', 'image/jpeg' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported result MIME type.' );
		}

		if ( $byte_size < 1 ) {
			throw new InvalidArgumentException( 'Result byte size must be positive.' );
		}

		if (
			null !== $provider_request_id
			&& (
				strlen( $provider_request_id ) < 1
				|| strlen( $provider_request_id ) > 128
				|| 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/D', $provider_request_id )
			)
		) {
			throw new InvalidArgumentException( 'Provider request ID must be a safe ASCII identifier containing 1 to 128 characters.' );
		}

		$this->result_reference    = trim( $result_reference );
		$this->mime_type           = strtolower( $mime_type );
		$this->byte_size           = $byte_size;
		$this->provider_request_id = $provider_request_id;
	}

	/** Get the private storage reference. */
	public function result_reference(): string {
		return $this->result_reference;
	}

	/** Get the validated MIME type. */
	public function mime_type(): string {
		return $this->mime_type;
	}

	/** Get the validated byte count. */
	public function byte_size(): int {
		return $this->byte_size;
	}

	/** Get the sanitized provider request identifier. */
	public function provider_request_id(): ?string {
		return $this->provider_request_id;
	}
}
