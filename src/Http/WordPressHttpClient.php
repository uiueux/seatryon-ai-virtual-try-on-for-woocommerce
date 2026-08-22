<?php
/**
 * WordPress safe HTTP client.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Uses wp_safe_remote_request() and exposes no unbounded WordPress HTTP options.
 */
final class WordPressHttpClient implements HttpClientInterface {

	/**
	 * Safe WordPress requester.
	 *
	 * @var callable|null
	 */
	private $safe_requester;

	/**
	 * Development-only WordPress requester.
	 *
	 * @var callable|null
	 */
	private $unsafe_requester;

	/**
	 * Explicit development opt-in.
	 *
	 * @var bool
	 */
	private $allow_unsafe_development;

	/**
	 * Environment type resolver.
	 *
	 * @var callable|null
	 */
	private $environment_resolver;

	/**
	 * Set up the WordPress HTTP boundary.
	 *
	 * @param callable|null $requester                 Safe requester seam matching wp_safe_remote_request().
	 * @param bool          $allow_unsafe_development Explicit local-development override.
	 * @param callable|null $environment_resolver     Returns the WordPress environment type.
	 * @param callable|null $unsafe_requester          Development requester seam matching wp_remote_request().
	 */
	public function __construct(
		?callable $requester = null,
		bool $allow_unsafe_development = false,
		?callable $environment_resolver = null,
		?callable $unsafe_requester = null
	) {
		$this->safe_requester           = $requester;
		$this->unsafe_requester         = $unsafe_requester;
		$this->allow_unsafe_development = $allow_unsafe_development;
		$this->environment_resolver     = $environment_resolver;
	}

	/**
	 * Send one request through the appropriate WordPress HTTP function.
	 *
	 * @param HttpRequest $request Validated request.
	 * @throws TransportException When the HTTP boundary fails or returns unsafe data.
	 */
	public function request( HttpRequest $request ): HttpResponse {
		$unsafe = $this->may_use_unsafe_requester( $request->url() );
		$this->assert_secure_url( $request->url(), $unsafe );
		$requester = $unsafe ? $this->unsafe_requester : $this->safe_requester;
		if ( null === $requester ) {
			$function = $unsafe ? 'wp_remote_request' : 'wp_safe_remote_request';
			if ( ! function_exists( $function ) ) {
				throw new TransportException( 'http_unavailable', 'The WordPress HTTP API is unavailable.' );
			}
			$requester = $function;
		}

		$args = array(
			'method'              => $request->method(),
			'headers'             => $request->headers(),
			'body'                => $request->body(),
			'timeout'             => $request->timeout(),
			'redirection'         => $unsafe ? 0 : $request->redirections(),
			'limit_response_size' => $request->max_response_bytes(),
			'reject_unsafe_urls'  => ! $unsafe,
			'stream'              => false,
		);

		$response = $requester( $request->url(), $args );
		if ( $this->is_error( $response ) ) {
			$reason = $this->error_reason( $response );
			throw new TransportException(
				$reason, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Stable internal allowlist, not rendered output.
				'timeout' === $reason ? 'The remote HTTP request timed out.' : 'The remote HTTP request failed.',
				true
			);
		}

		if ( ! is_array( $response ) ) {
			throw new TransportException( 'invalid_http_response', 'The WordPress HTTP API returned an invalid response.' );
		}

		$status  = $this->response_status( $response );
		$body    = $this->response_body( $response );
		$headers = $this->response_headers( $response );
		if ( $status < 100 || $status > 599 ) {
			throw new TransportException( 'invalid_http_response', 'The WordPress HTTP API returned an invalid status.' );
		}

		if ( strlen( $body ) > $request->max_response_bytes() ) {
			throw new TransportException( 'response_too_large', 'The remote response exceeded the allowed size.' );
		}

		return new HttpResponse( $status, $headers, $body );
	}

	/**
	 * Require HTTPS except for the tightly-scoped development loopback path.
	 *
	 * @param string $url    Candidate URL.
	 * @param bool   $unsafe Whether the local-development exception matched.
	 * @throws TransportException When cleartext transport is not allowed.
	 */
	private function assert_secure_url( string $url, bool $unsafe ): void {
		$parts  = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit-test fallback.
		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';

		if ( 'https' !== $scheme && ! $unsafe ) {
			throw new TransportException( 'insecure_url', 'Remote provider requests require HTTPS.' );
		}
	}

	/**
	 * Allow the unsafe WordPress requester solely for cleartext loopback in a
	 * development environment explicitly enabled by the application.
	 *
	 * @param string $url Candidate request URL.
	 */
	private function may_use_unsafe_requester( string $url ): bool {
		if ( ! $this->allow_unsafe_development || ! in_array( $this->environment_type(), array( 'local', 'development' ), true ) ) {
			return false;
		}

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit-test fallback.
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || 'http' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( trim( (string) $parts['host'], '[]' ) );
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 80;

		$ipv4_loopback = false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && 0 === strpos( $host, '127.' );

		return 80 === $port && ( 'localhost' === $host || '::1' === $host || $ipv4_loopback );
	}

	/** Return the current WordPress environment type. */
	private function environment_type(): string {
		if ( null !== $this->environment_resolver ) {
			return strtolower( (string) call_user_func( $this->environment_resolver ) );
		}

		return function_exists( 'wp_get_environment_type' ) ? strtolower( wp_get_environment_type() ) : 'production';
	}

	/**
	 * Determine whether WordPress returned an error.
	 *
	 * @param mixed $response Raw WordPress response.
	 */
	private function is_error( $response ): bool {
		return function_exists( 'is_wp_error' )
			? is_wp_error( $response )
			: is_object( $response ) && method_exists( $response, 'get_error_code' ) && method_exists( $response, 'get_error_message' );
	}

	/**
	 * Classify a WordPress HTTP error without preserving its potentially sensitive message.
	 *
	 * @param mixed $response WP_Error-compatible response.
	 */
	private function error_reason( $response ): string {
		if ( ! is_object( $response ) ) {
			return 'network_error';
		}

		$code    = method_exists( $response, 'get_error_code' ) ? (string) $response->get_error_code() : '';
		$message = method_exists( $response, 'get_error_message' ) ? (string) $response->get_error_message() : '';
		$signal  = strtolower( $code . ' ' . $message );

		return false !== strpos( $signal, 'timed out' )
			|| false !== strpos( $signal, 'timeout' )
			|| false !== strpos( $signal, 'curl error 28' )
			? 'timeout'
			: 'network_error';
	}

	/**
	 * Read the HTTP status.
	 *
	 * @param array<mixed> $response Raw response.
	 */
	private function response_status( array $response ): int {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}

		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}

	/**
	 * Read the response body.
	 *
	 * @param array<mixed> $response Raw response.
	 */
	private function response_body( array $response ): string {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}

		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}

	/**
	 * Read normalized response headers.
	 *
	 * @param array<mixed> $response Raw response.
	 * @return array<string,string>
	 */
	private function response_headers( array $response ): array {
		$raw = function_exists( 'wp_remote_retrieve_headers' )
			? wp_remote_retrieve_headers( $response )
			: ( isset( $response['headers'] ) ? $response['headers'] : array() );

		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			$raw = $raw->getAll();
		}

		$headers = array();
		if ( is_iterable( $raw ) ) {
			foreach ( $raw as $name => $value ) {
				$headers[ (string) $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		return $headers;
	}
}
