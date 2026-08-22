<?php
/**
 * Encoded multipart payload.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Carries an encoded body and safe Content-Type value.
 */
final class MultipartPayload {

	/**
	 * Encoded multipart body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Unpredictable boundary.
	 *
	 * @var string
	 */
	private $boundary;

	/**
	 * Set up an encoded payload.
	 *
	 * @param string $body     Encoded body.
	 * @param string $boundary Unpredictable boundary.
	 */
	public function __construct( string $body, string $boundary ) {
		$this->body     = $body;
		$this->boundary = $boundary;
	}

	/** Return the encoded body. */
	public function body(): string {
		return $this->body;
	}

	/** Return the boundary. */
	public function boundary(): string {
		return $this->boundary;
	}

	/** Return the Content-Type header value. */
	public function content_type(): string {
		return 'multipart/form-data; boundary=' . $this->boundary;
	}
}
