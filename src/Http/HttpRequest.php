<?php
/**
 * Bounded HTTP request value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Describes only the HTTP options provider integrations are allowed to control.
 */
final class HttpRequest {

	/**
	 * HTTP method.
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Absolute destination URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Validated headers.
	 *
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Secret request body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Response byte limit.
	 *
	 * @var int
	 */
	private $max_response_bytes;

	/**
	 * Redirect limit.
	 *
	 * @var int
	 */
	private $redirections;

	/**
	 * Set up a bounded request.
	 *
	 * @param string               $method             HTTP method.
	 * @param string               $url                Absolute URL.
	 * @param array<string,string> $headers            Request headers.
	 * @param string               $body               Request body. It must never be logged.
	 * @param int                  $timeout             Total timeout in seconds.
	 * @param int                  $max_response_bytes  Maximum response body size.
	 * @param int                  $redirections        Maximum redirects followed by WordPress.
	 * @throws InvalidArgumentException When request metadata or bounds are invalid.
	 */
	public function __construct(
		string $method,
		string $url,
		array $headers = array(),
		string $body = '',
		int $timeout = 120,
		int $max_response_bytes = 10485760,
		int $redirections = 0
	) {
		$method = strtoupper( trim( $method ) );
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			throw new InvalidArgumentException( 'Only GET and POST HTTP methods are supported.' );
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( 'The HTTP request URL is invalid.' );
		}

		if ( $timeout < 1 || $timeout > 300 ) {
			throw new InvalidArgumentException( 'The HTTP timeout must be between 1 and 300 seconds.' );
		}

		if ( $max_response_bytes < 1 || $max_response_bytes > 52428800 ) {
			throw new InvalidArgumentException( 'The response size limit must be between 1 byte and 50 MB.' );
		}

		if ( $redirections < 0 || $redirections > 3 ) {
			throw new InvalidArgumentException( 'The redirect limit must be between zero and three.' );
		}

		$validated_headers = array();
		foreach ( $headers as $name => $value ) {
			if (
				1 !== preg_match( '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]{1,128}$/', $name )
				|| false !== strpbrk( $value, "\r\n" )
			) {
				throw new InvalidArgumentException( 'An HTTP header is invalid.' );
			}

			$validated_headers[ $name ] = $value;
		}

		$this->method             = $method;
		$this->url                = $url;
		$this->headers            = $validated_headers;
		$this->body               = $body;
		$this->timeout            = $timeout;
		$this->max_response_bytes = $max_response_bytes;
		$this->redirections       = $redirections;
	}

	/** Return the HTTP method. */
	public function method(): string {
		return $this->method;
	}

	/** Return the absolute destination URL. */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Return request headers.
	 *
	 * @return array<string,string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/** Return the secret request body for transport only. */
	public function body(): string {
		return $this->body;
	}

	/** Return the timeout in seconds. */
	public function timeout(): int {
		return $this->timeout;
	}

	/** Return the response byte limit. */
	public function max_response_bytes(): int {
		return $this->max_response_bytes;
	}

	/** Return the redirect limit. */
	public function redirections(): int {
		return $this->redirections;
	}
}
