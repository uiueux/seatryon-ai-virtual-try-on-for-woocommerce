<?php
/**
 * SeaAI Universal X provider adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\SeaAI;

use InvalidArgumentException;
use SeaTryOn\Contracts\ProviderInterface;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\DTO\ProviderResult;
use SeaTryOn\Http\MultipartFile;
use SeaTryOn\Http\ProviderTransport;
use SeaTryOn\Http\TransportException;
use SeaTryOn\Image\ImageValidationException;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Image\UnsafeUrlException;
use SeaTryOn\Image\UrlSafetyPolicy;
use SeaTryOn\Image\ValidatedImage;
use SeaTryOn\Storage\StorageException;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

// Domain exceptions are not rendered; callers map them to escaped UI output.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Uploads two private references and executes one synchronous universal_x job.
 */
final class SeaAIProvider implements ProviderInterface {

	private const MODEL                   = 'universal_x';
	private const MAX_RESULT_BYTES        = 10485760;
	private const MAX_HTTP_RESPONSE_BYTES = 1048576;
	private const TIMEOUT_SECONDS         = 240;

	/**
	 * Shared provider transport.
	 *
	 * @var ProviderTransport
	 */
	private $transport;

	/**
	 * SSRF-safe remote result downloader.
	 *
	 * @var RemoteImageDownloader
	 */
	private $downloader;

	/**
	 * Private temporary storage.
	 *
	 * @var TemporaryStorageInterface
	 */
	private $storage;

	/**
	 * Configured gateway API root.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Server-side SeaAI user key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Private input image validator.
	 *
	 * @var ImageValidator
	 */
	private $image_validator;

	/**
	 * Upload and result URL safety policy.
	 *
	 * @var UrlSafetyPolicy
	 */
	private $url_policy;

	/**
	 * Gateway error mapper.
	 *
	 * @var SeaAIErrorMapper
	 */
	private $error_mapper;

	/**
	 * Initialize the synchronous SeaAI provider.
	 *
	 * @param ProviderTransport         $transport                    Shared provider HTTP transport.
	 * @param RemoteImageDownloader     $downloader                   SSRF-safe result downloader.
	 * @param TemporaryStorageInterface $storage                      Private temporary storage.
	 * @param string                    $base_url                     Configured SeaAI API root.
	 * @param string                    $api_key                      Server-side SeaAI user key.
	 * @param ImageValidator|null       $image_validator              Private input validator.
	 * @param SeaAIErrorMapper|null     $error_mapper                 Error normalizer.
	 * @param bool                      $allow_insecure_local_development Explicit localhost HTTP exception.
	 * @param callable|null             $environment_type             Test seam returning the WordPress environment type.
	 * @param callable|null             $url_resolver                 Test seam returning resolved IP addresses.
	 * @throws InvalidArgumentException When the API root or user key is invalid.
	 */
	public function __construct(
		ProviderTransport $transport,
		RemoteImageDownloader $downloader,
		TemporaryStorageInterface $storage,
		string $base_url,
		string $api_key,
		?ImageValidator $image_validator = null,
		?SeaAIErrorMapper $error_mapper = null,
		bool $allow_insecure_local_development = false,
		?callable $environment_type = null,
		?callable $url_resolver = null
	) {
		$environment          = $this->environment_type( $environment_type );
		$allow_insecure_local = $allow_insecure_local_development
			&& in_array( $environment, array( 'local', 'development' ), true );

		$this->base_url = $this->validate_base_url( $base_url, $allow_insecure_local );

		if ( 1 !== preg_match( '/^sk-[\x21-\x7E]{1,509}$/D', $api_key ) ) {
			throw new InvalidArgumentException( 'A valid SeaAI user API key is required.' );
		}

		$this->transport       = $transport;
		$this->downloader      = $downloader;
		$this->storage         = $storage;
		$this->api_key         = $api_key;
		$this->image_validator = $image_validator ?? new ImageValidator( self::MAX_RESULT_BYTES );
		$this->url_policy      = new UrlSafetyPolicy( $allow_insecure_local, $url_resolver );
		$this->error_mapper    = $error_mapper ?? new SeaAIErrorMapper();
	}

	/**
	 * Generate and privately store one synchronous Universal X result.
	 *
	 * @param ProviderRequest $request Normalized provider request.
	 * @return ProviderResult
	 * @throws ProviderException When an input, gateway response, download, or write fails.
	 */
	public function generate( ProviderRequest $request ): ProviderResult {
		$customer = $this->read_input( $request->customer_image_reference() );
		$product  = $this->read_input( $request->product_image_reference() );

		// Upload order is part of the provider contract: customer/scene, then product.
		$image_urls = array(
			$this->upload( $customer, 'customer' ),
			$this->upload( $product, 'product' ),
		);

		$quality = 'auto' === $request->quality() ? 'low' : $request->quality();
		$payload = array(
			'model_name'    => self::MODEL,
			'image_urls'    => $image_urls,
			'prompt'        => $request->prompt(),
			'resolution'    => $request->size(),
			'size'          => $request->size(),
			'n'             => 1,
			'quality'       => $quality,
			'background'    => 'auto',
			'output_format' => 'png',
		);

		try {
			$response = $this->transport->post_json(
				$this->base_url . '/forward/image/generate',
				$this->auth_headers(),
				$payload,
				self::TIMEOUT_SECONDS,
				self::MAX_HTTP_RESPONSE_BYTES
			);
		} catch ( TransportException $exception ) {
			throw new ProviderException( $this->transport_error( $exception ) );
		}

		if ( $response->status() < 200 || $response->status() > 299 ) {
			throw new ProviderException( $this->error_mapper->from_http_response( $response->status(), $response->body() ) );
		}

		$urls = $this->parse_generation_urls( $response->body(), $response->status() );

		try {
			$result = $this->downloader->download( $urls[0], self::TIMEOUT_SECONDS, self::MAX_RESULT_BYTES );
		} catch ( UnsafeUrlException | ImageValidationException $exception ) {
			throw new ProviderException( $this->error_mapper->contract_error( $response->status() ) );
		} catch ( TransportException $exception ) {
			throw new ProviderException( $this->transport_error( $exception ) );
		}

		if ( ! in_array( $result->mime(), array( 'image/png', 'image/jpeg' ), true ) ) {
			throw new ProviderException( $this->error_mapper->contract_error( $response->status() ) );
		}

		return $this->store_result(
			$request->customer_image_reference(),
			$result,
			$response->header( 'x-request-id' )
		);
	}

	/**
	 * Read and validate one private image.
	 *
	 * @param string $reference Private storage identifier.
	 * @throws ProviderException When the private input is unavailable or invalid.
	 */
	private function read_input( string $reference ): ValidatedImage {
		try {
			return $this->image_validator->validate( $this->storage->read( $reference ) );
		} catch ( StorageException | ImageValidationException $exception ) {
			throw new ProviderException( new ProviderError( 'seaai_input_unavailable', 'A private input image is unavailable or invalid.', false ) );
		}
	}

	/**
	 * Upload one image and return its strictly validated gateway URL.
	 *
	 * @param ValidatedImage $image Image established from private bytes.
	 * @param string         $role  Fixed safe filename role.
	 * @throws ProviderException When upload or its response fails validation.
	 */
	private function upload( ValidatedImage $image, string $role ): string {
		try {
			$response = $this->transport->post_multipart(
				$this->base_url . '/forward/image/upload',
				$this->auth_headers(),
				array(),
				array( new MultipartFile( 'file', $role . '.' . $image->extension(), $image->mime(), $image->bytes() ) ),
				self::TIMEOUT_SECONDS,
				self::MAX_HTTP_RESPONSE_BYTES
			);
		} catch ( TransportException $exception ) {
			throw new ProviderException( $this->transport_error( $exception ) );
		}

		if ( $response->status() < 200 || $response->status() > 299 ) {
			throw new ProviderException( $this->error_mapper->from_http_response( $response->status(), $response->body() ) );
		}

		$decoded = $this->decode_json_object( $response->body(), $response->status() );
		if ( ! isset( $decoded['download_url'] ) || ! is_string( $decoded['download_url'] ) || '' === trim( $decoded['download_url'] ) ) {
			throw new ProviderException( $this->error_mapper->contract_error( $response->status() ) );
		}

		$url = trim( $decoded['download_url'] );
		try {
			$this->url_policy->assert_safe( $url );
		} catch ( UnsafeUrlException $exception ) {
			throw new ProviderException( $this->error_mapper->contract_error( $response->status() ) );
		}

		return $url;
	}

	/**
	 * Strictly parse the synchronous Universal X response.
	 *
	 * An unexpected task_id is intentionally not queried: the installed query
	 * route belongs to a legacy provider and is not a Universal X contract.
	 *
	 * @param string $body   Raw bounded response body.
	 * @param int    $status HTTP success status.
	 * @return array<string>
	 * @throws ProviderException When the response drifts from the synchronous contract.
	 */
	private function parse_generation_urls( string $body, int $status ): array {
		$decoded = $this->decode_json_object( $body, $status );
		if ( array_key_exists( 'task_id', $decoded ) ) {
			$task_id = isset( $decoded['task_id'] ) && is_string( $decoded['task_id'] )
				&& 1 === preg_match( '/^[A-Za-z0-9._:-]{1,128}$/', $decoded['task_id'] )
				? $decoded['task_id']
				: null;

			throw new ProviderException( $this->error_mapper->contract_error( $status, $task_id ) );
		}

		if (
			! isset( $decoded['images'] )
			|| ! is_array( $decoded['images'] )
			|| array() === $decoded['images']
		) {
			throw new ProviderException( $this->error_mapper->contract_error( $status ) );
		}

		$urls = array();
		foreach ( $decoded['images'] as $entry ) {
			if ( is_string( $entry ) ) {
				$url = trim( $entry );
			} elseif ( is_array( $entry ) && isset( $entry['url'] ) && is_string( $entry['url'] ) ) {
				$url = trim( $entry['url'] );
			} else {
				throw new ProviderException( $this->error_mapper->contract_error( $status ) );
			}

			if ( '' === $url ) {
				throw new ProviderException( $this->error_mapper->contract_error( $status ) );
			}

			try {
				$this->url_policy->assert_safe( $url );
			} catch ( UnsafeUrlException $exception ) {
				throw new ProviderException( $this->error_mapper->contract_error( $status ) );
			}

			$urls[] = $url;
		}

		return $urls;
	}

	/**
	 * Decode a JSON object and distinguish malformed JSON from contract drift.
	 *
	 * @param string $body   Raw bounded response body.
	 * @param int    $status HTTP success status.
	 * @return array<mixed>
	 * @throws ProviderException When the response is malformed or is not a JSON object.
	 */
	private function decode_json_object( string $body, int $status ): array {
		$decoded = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new ProviderException( $this->error_mapper->invalid_response( $status ) );
		}

		if ( ! is_array( $decoded ) ) {
			throw new ProviderException( $this->error_mapper->contract_error( $status ) );
		}

		return $decoded;
	}

	/**
	 * Persist the downloaded PNG/JPEG in the same private request scope.
	 *
	 * @param string         $input_reference     Customer input reference.
	 * @param ValidatedImage $result              Validated downloaded result.
	 * @param string|null    $provider_request_id Raw provider request identifier.
	 * @throws ProviderException When the private scope or storage write is invalid.
	 */
	private function store_result( string $input_reference, ValidatedImage $result, ?string $provider_request_id ): ProviderResult {
		$scope = strstr( $input_reference, '/', true );
		if ( false === $scope || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $scope ) ) {
			throw new ProviderException( new ProviderError( 'seaai_storage_error', 'The private result scope is invalid.', false ) );
		}

		try {
			$reference = $this->storage->write( $scope, 'result', $result->bytes(), $result->extension() );
		} catch ( StorageException $exception ) {
			throw new ProviderException( new ProviderError( 'seaai_storage_error', 'The generated image could not be stored privately.', false ) );
		}

		return new ProviderResult(
			$reference,
			$result->mime(),
			strlen( $result->bytes() ),
			$this->safe_request_id( $provider_request_id )
		);
	}

	/**
	 * Return only the required server-side authorization header.
	 *
	 * @return array<string,string>
	 */
	private function auth_headers(): array {
		return array( 'Authorization' => 'Bearer ' . $this->api_key );
	}

	/**
	 * Map a safe transport reason without exposing its message.
	 *
	 * @param TransportException $exception Safe transport failure.
	 */
	private function transport_error( TransportException $exception ): ProviderError {
		if ( in_array( $exception->reason(), array( 'network_error', 'timeout' ), true ) || $exception->is_retryable() ) {
			return new ProviderError( 'provider_network_failure', 'The SeaAI service could not be reached.', true );
		}

		if ( in_array( $exception->reason(), array( 'invalid_http_response', 'response_too_large' ), true ) ) {
			return $this->error_mapper->invalid_response();
		}

		return new ProviderError( 'provider_transport_error', 'The SeaAI request could not be sent safely.', false );
	}

	/**
	 * Accept only the ProviderResult request-ID allow-list.
	 *
	 * @param string|null $request_id Raw provider request identifier.
	 */
	private function safe_request_id( ?string $request_id ): ?string {
		$request_id = null === $request_id ? '' : trim( $request_id );
		if ( strlen( $request_id ) < 1 || strlen( $request_id ) > 128 ) {
			return null;
		}

		return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/D', $request_id ) ? $request_id : null;
	}

	/**
	 * Validate the configured API root without appending an OpenAI-style path.
	 *
	 * @param string $base_url                         Configured API root.
	 * @param bool   $allow_insecure_local_development Explicit localhost exception.
	 * @throws InvalidArgumentException When the API root is invalid or insecure.
	 */
	private function validate_base_url( string $base_url, bool $allow_insecure_local_development ): string {
		$base_url = rtrim( trim( $base_url ), '/' );
		$parts    = function_exists( 'wp_parse_url' ) ? wp_parse_url( $base_url ) : parse_url( $base_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit-test fallback.

		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'], $parts['path'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
			|| false === filter_var( $base_url, FILTER_VALIDATE_URL )
		) {
			throw new InvalidArgumentException( 'The SeaAI API root is invalid.' );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( rtrim( (string) $parts['host'], '.' ) );
		$path   = rtrim( (string) $parts['path'], '/' );
		$local  = 'localhost' === $host || '::1' === $host || 0 === strpos( $host, '127.' );
		if ( '/wp-json/seaai/v1' !== substr( $path, -strlen( '/wp-json/seaai/v1' ) ) ) {
			throw new InvalidArgumentException( 'The SeaAI API root must end with /wp-json/seaai/v1.' );
		}

		if ( 'https' !== $scheme && ! ( $allow_insecure_local_development && $local && 'http' === $scheme ) ) {
			throw new InvalidArgumentException( 'The SeaAI API root must use HTTPS.' );
		}

		return $base_url;
	}

	/**
	 * Resolve the environment type without allowing a caller flag to weaken production.
	 *
	 * @param callable|null $environment_type Optional test seam.
	 */
	private function environment_type( ?callable $environment_type ): string {
		if ( null !== $environment_type ) {
			$value = call_user_func( $environment_type );

			return is_string( $value ) ? strtolower( trim( $value ) ) : 'production';
		}

		return function_exists( 'wp_get_environment_type' )
			? strtolower( (string) wp_get_environment_type() )
			: 'production';
	}
}

// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
