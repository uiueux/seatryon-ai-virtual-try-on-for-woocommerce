<?php
/**
 * SeaAI gateway connection test.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

use SeaTryOn\Http\HttpClientInterface;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Http\TransportException;
use SeaTryOn\Http\WordPressHttpClient;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the gateway's dedicated, non-generating authentication probe.
 */
final class SeaAIConnectionTester {

	/** Dedicated non-generating gateway route. */
	private const ENDPOINT = '/connection-test';

	/** Existing route used only when an older gateway lacks the probe route. */
	private const COMPATIBILITY_ENDPOINT = '/forward/image/upload';

	/** Short diagnostic timeout. */
	private const TIMEOUT_SECONDS = 15;

	/** Bounded diagnostic response size. */
	private const MAX_RESPONSE_BYTES = 65536;

	/**
	 * Provider credential access.
	 *
	 * @var SecretStore
	 */
	private $secrets;

	/**
	 * Gateway URL policy.
	 *
	 * @var SeaAIBaseUrlValidator
	 */
	private $urls;

	/**
	 * Safe HTTP boundary.
	 *
	 * @var HttpClientInterface
	 */
	private $http;

	/**
	 * Set up the connection test service.
	 *
	 * @param SecretStore|null           $secrets Provider credential access.
	 * @param SeaAIBaseUrlValidator|null $urls    Gateway URL policy.
	 * @param HttpClientInterface|null   $http    Safe HTTP boundary.
	 */
	public function __construct(
		?SecretStore $secrets = null,
		?SeaAIBaseUrlValidator $urls = null,
		?HttpClientInterface $http = null
	) {
		$this->secrets = $secrets ?? new SecretStore();
		$this->urls    = $urls ?? new SeaAIBaseUrlValidator();
		$this->http    = $http ?? new WordPressHttpClient( null, true );
	}

	/**
	 * Verify the currently entered URL and key without saving or generating.
	 *
	 * @param string $submitted_url Submitted SeaAI API root.
	 * @param string $submitted_key Submitted key or the fixed saved-key mask.
	 */
	public function test( string $submitted_url, string $submitted_key ): SeaAIConnectionTestResult {
		$base_url = $this->urls->normalize( $submitted_url );
		if ( '' === $base_url ) {
			return $this->failure(
				'invalid_url',
				__( 'Enter a valid SeaAI Base URL ending in /wp-json/seaai/v1.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				400
			);
		}

		$key = $this->sanitize_key( $submitted_key );
		if ( '' === $key || SecretStore::MASK === $key ) {
			$key = $this->secrets->get_seaai_api_key_for_connection_test();
		}

		if ( '' === $key ) {
			return $this->failure( 'missing_key', __( 'Enter a SeaAI API key before testing the connection.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
		}

		if ( 1 !== preg_match( '/^sk-[\x21-\x7E]{1,509}$/D', $key ) ) {
			return $this->failure( 'invalid_key', __( 'The SeaAI API key format is invalid.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
		}

		$compatibility_probe = false;
		try {
			$response = $this->request( $base_url . self::ENDPOINT, $key );
			if ( 404 === $response->status() ) {
				// Older gateways can still verify authentication without an upload:
				// auth is evaluated before the deliberately absent image payload.
				$response            = $this->request( $base_url . self::COMPATIBILITY_ENDPOINT, $key );
				$compatibility_probe = true;
			}
		} catch ( TransportException $exception ) {
			return 'timeout' === $exception->reason()
				? $this->failure( 'timeout', __( 'The SeaAI gateway did not respond in time.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 504 )
				: $this->failure( 'network_error', __( 'The SeaAI gateway could not be reached securely.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
		}

		$status = $response->status();
		if ( $compatibility_probe && ( ( $status >= 200 && $status <= 299 ) || in_array( $status, array( 400, 402, 415, 422 ), true ) ) ) {
			$message = 402 === $status
				? __( 'Authentication succeeded, but the SeaAI account has insufficient points.', 'seatryon-ai-virtual-try-on-for-woocommerce' )
				: __( 'Connection successful. The URL and API key are valid.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			return new SeaAIConnectionTestResult( true, 'connection_ok', $message );
		}

		if ( $status >= 200 && $status <= 299 ) {
			$data          = json_decode( $response->body(), true );
			$authenticated = is_array( $data )
				&& (
					true === ( $data['authenticated'] ?? null )
					|| true === ( $data['success'] ?? null )
					|| true === ( $data['ok'] ?? null )
				);
			if ( ! $authenticated && is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$authenticated = true === ( $data['data']['authenticated'] ?? null );
			}

			return $authenticated
				? new SeaAIConnectionTestResult( true, 'connection_ok', __( 'Connection successful. The URL and API key are valid.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) )
				: $this->failure( 'invalid_response', __( 'The SeaAI gateway returned an unexpected test response.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
		}

		if ( 401 === $status || 403 === $status ) {
			return $this->failure( 'authentication_failed', __( 'Authentication failed. Check the SeaAI API key.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), $status );
		}

		if ( 404 === $status ) {
			return $this->failure( 'gateway_route_missing', __( 'The SeaAI gateway URL does not expose the expected API routes.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
		}

		if ( 429 === $status ) {
			return $this->failure( 'rate_limited', __( 'The SeaAI gateway is rate limiting connection tests. Try again shortly.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 429 );
		}

		if ( $status >= 500 ) {
			return $this->failure( 'gateway_unavailable', __( 'The SeaAI gateway is temporarily unavailable.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
		}

		return $this->failure( 'unexpected_status', __( 'The SeaAI gateway rejected the connection test.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
	}

	/**
	 * Send one bounded, non-generating probe request.
	 *
	 * @param string $url Gateway endpoint URL.
	 * @param string $key Validated SeaAI key.
	 * @throws TransportException When the request fails safely.
	 */
	private function request( string $url, string $key ): HttpResponse {
		return $this->http->request(
			new HttpRequest(
				'POST',
				$url,
				array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'{}',
				self::TIMEOUT_SECONDS,
				self::MAX_RESPONSE_BYTES
			)
		);
	}

	/**
	 * Strip characters which must never reach an HTTP header.
	 *
	 * @param string $key Submitted credential.
	 */
	private function sanitize_key( string $key ): string {
		$key = preg_replace( '/[\x00-\x1F\x7F]/', '', trim( $key ) );

		return null === $key ? '' : $key;
	}

	/**
	 * Build a failed result.
	 *
	 * @param string $code        Stable diagnostic code.
	 * @param string $message     Safe merchant-facing message.
	 * @param int    $http_status AJAX response status.
	 */
	private function failure( string $code, string $message, int $http_status ): SeaAIConnectionTestResult {
		return new SeaAIConnectionTestResult( false, $code, $message, $http_status );
	}
}
