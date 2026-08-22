<?php
/**
 * Provider transport helpers.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Creates bounded JSON and multipart requests on the shared HTTP boundary.
 */
final class ProviderTransport {

	/**
	 * Injected HTTP client.
	 *
	 * @var HttpClientInterface
	 */
	private $client;

	/**
	 * Multipart encoder.
	 *
	 * @var MultipartEncoder
	 */
	private $multipart;

	/**
	 * Set up provider transport helpers.
	 *
	 * @param HttpClientInterface   $client    HTTP client.
	 * @param MultipartEncoder|null $multipart Multipart encoder.
	 */
	public function __construct( HttpClientInterface $client, ?MultipartEncoder $multipart = null ) {
		$this->client    = $client;
		$this->multipart = $multipart ?? new MultipartEncoder();
	}

	/**
	 * Send one JSON request.
	 *
	 * @param string               $url                Destination URL.
	 * @param array<string,string> $headers            Provider headers.
	 * @param array<mixed>         $payload            JSON object.
	 * @param int                  $timeout             Timeout in seconds.
	 * @param int                  $max_response_bytes Maximum response bytes.
	 * @throws TransportException When encoding or transport fails.
	 */
	public function post_json( string $url, array $headers, array $payload, int $timeout = 120, int $max_response_bytes = 10485760 ): HttpResponse {
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $payload, JSON_UNESCAPED_SLASHES )
			: json_encode( $payload, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit-test fallback before WordPress is loaded.

		if ( false === $json ) {
			throw new TransportException( 'json_encode_failed', 'The provider request could not be encoded.' );
		}

		$headers['Content-Type'] = 'application/json';
		$headers['Accept']       = 'application/json';

		return $this->client->request( new HttpRequest( 'POST', $url, $headers, $json, $timeout, $max_response_bytes ) );
	}

	/**
	 * Send one ordered multipart request.
	 *
	 * @param string                   $url                Destination URL.
	 * @param array<string,string>     $headers            Provider headers.
	 * @param array<string,int|string> $fields             Text fields.
	 * @param array<MultipartFile>     $files              Ordered file parts.
	 * @param int                      $timeout             Timeout in seconds.
	 * @param int                      $max_response_bytes Maximum response bytes.
	 * @throws TransportException When transport fails.
	 */
	public function post_multipart(
		string $url,
		array $headers,
		array $fields,
		array $files,
		int $timeout = 120,
		int $max_response_bytes = 10485760
	): HttpResponse {
		$payload                 = $this->multipart->encode( $fields, $files );
		$headers['Content-Type'] = $payload->content_type();
		$headers['Accept']       = 'application/json';

		return $this->client->request( new HttpRequest( 'POST', $url, $headers, $payload->body(), $timeout, $max_response_bytes ) );
	}
}
