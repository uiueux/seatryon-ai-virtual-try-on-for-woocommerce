<?php
/**
 * Safe remote image downloader.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

use SeaTryOn\Http\HttpClientInterface;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\TransportException;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads a bounded result through WordPress safe HTTP and returns bytes only.
 */
final class RemoteImageDownloader {

	/**
	 * Safe HTTP client.
	 *
	 * @var HttpClientInterface
	 */
	private $client;

	/**
	 * Image byte validator.
	 *
	 * @var ImageValidator
	 */
	private $validator;

	/**
	 * SSRF protection policy.
	 *
	 * @var UrlSafetyPolicy
	 */
	private $url_policy;

	/**
	 * Set up a safe remote image downloader.
	 *
	 * @param HttpClientInterface  $client     HTTP client.
	 * @param ImageValidator       $validator  Image validator.
	 * @param UrlSafetyPolicy|null $url_policy URL policy.
	 */
	public function __construct( HttpClientInterface $client, ImageValidator $validator, ?UrlSafetyPolicy $url_policy = null ) {
		$this->client     = $client;
		$this->validator  = $validator;
		$this->url_policy = $url_policy ?? new UrlSafetyPolicy();
	}

	/**
	 * Download and validate one remote image.
	 *
	 * @param string $url                Result URL.
	 * @param int    $timeout            Timeout in seconds.
	 * @param int    $max_response_bytes Maximum response bytes.
	 * @throws TransportException        When the remote request fails.
	 * @throws ImageValidationException When the result is not a supported image.
	 */
	public function download( string $url, int $timeout = 30, int $max_response_bytes = ImageValidator::DEFAULT_MAX_BYTES ): ValidatedImage {
		$this->url_policy->assert_safe( $url );

		$response = $this->client->request(
			new HttpRequest(
				'GET',
				$url,
				array( 'Accept' => 'image/png, image/jpeg, image/webp' ),
				'',
				$timeout,
				$max_response_bytes,
				2
			)
		);

		if ( $response->status() < 200 || $response->status() > 299 ) {
			throw new TransportException( 'download_http_error', 'The remote image download returned an unsuccessful status.', $response->status() >= 500 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Boolean comparison is not rendered output.
		}

		$content_type = strtolower( trim( (string) $response->header( 'content-type' ) ) );
		if ( false !== strpos( $content_type, ';' ) ) {
			$content_type = trim( (string) strtok( $content_type, ';' ) );
		}

		if ( ! in_array( $content_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			throw new ImageValidationException( 'image_content_type_invalid', 'The remote response is not a supported image.' );
		}

		return $this->validator->validate( $response->body(), $content_type );
	}
}
