<?php
/**
 * HTTP response value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Contains the bounded response data provider adapters may inspect.
 */
final class HttpResponse {

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Normalized response headers.
	 *
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Bounded response body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Set up a bounded response.
	 *
	 * @param int                  $status  HTTP status code.
	 * @param array<string,string> $headers Response headers.
	 * @param string               $body    Already bounded body.
	 * @throws InvalidArgumentException When the status is invalid.
	 */
	public function __construct( int $status, array $headers, string $body ) {
		if ( $status < 100 || $status > 599 ) {
			throw new InvalidArgumentException( 'The HTTP response status is invalid.' );
		}

		$normalized = array();
		foreach ( $headers as $name => $value ) {
			$normalized[ strtolower( trim( (string) $name ) ) ] = trim( (string) $value );
		}

		$this->status  = $status;
		$this->headers = $normalized;
		$this->body    = $body;
	}

	/** Return the HTTP status. */
	public function status(): int {
		return $this->status;
	}

	/**
	 * Return normalized headers.
	 *
	 * @return array<string,string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * Return a case-insensitive response header.
	 *
	 * @param string $name Header name.
	 */
	public function header( string $name ): ?string {
		$name = strtolower( trim( $name ) );

		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
	}

	/** Return the bounded response body. */
	public function body(): string {
		return $this->body;
	}
}
