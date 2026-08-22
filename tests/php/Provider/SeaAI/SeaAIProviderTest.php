<?php
/**
 * SeaAI Universal X provider tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider\SeaAI;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\Http\HttpClientInterface;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Http\ProviderTransport;
use SeaTryOn\Http\TransportException;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Image\UrlSafetyPolicy;
use SeaTryOn\Provider\SeaAI\SeaAIProvider;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

/** Verifies exact uploads, generation, synchronous parsing and private writes. */
final class SeaAIProviderTest extends TestCase {

	public const SCOPE     = '0123456789abcdef0123456789abcdef';
	private const CUSTOMER = self::SCOPE . '/customer-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';
	private const PRODUCT  = self::SCOPE . '/product-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png';

	/** The happy path uses both M0 success fixtures and the exact canonical body. */
	public function test_uploads_both_images_in_order_and_generates_exact_fixture_shape(): void {
		$upload_fixture   = $this->fixture( 'seaai-upload-success.json' );
		$generate_fixture = $this->fixture( 'seaai-generate-success.json' );
		$customer_url     = $upload_fixture['response']['body']['download_url'];
		$product_url      = $generate_fixture['request']['body']['image_urls'][1];
		$result_url       = $generate_fixture['response']['body']['images'][0]['url'];
		$client           = new SeaAIFakeHttpClient(
			array(
				$this->json_response( 200, $upload_fixture['response']['body'] ),
				$this->json_response( 200, array( 'download_url' => $product_url ) ),
				$this->json_response( 200, $generate_fixture['response']['body'], array( 'X-Request-ID' => 'req_safe-123' ) ),
				new HttpResponse( 200, array( 'Content-Type' => 'image/png' ), $this->png_bytes() ),
			)
		);
		$storage          = new SeaAIFakeStorage( $this->png_bytes() );
		$result           = $this->provider( $client, $storage )->generate( $this->request() );

		self::assertCount( 4, $client->requests );
		self::assertSame( '/forward/image/upload', $this->path_suffix( $client->requests[0]->url() ) );
		self::assertSame( '/forward/image/upload', $this->path_suffix( $client->requests[1]->url() ) );
		self::assertSame( '/forward/image/generate', $this->path_suffix( $client->requests[2]->url() ) );
		self::assertSame( $result_url, $client->requests[3]->url() );
		self::assertSame( 'Bearer ' . $this->provider_key( 'test-safe-key' ), $client->requests[0]->headers()['Authorization'] );
		self::assertSame( 'POST', $client->requests[0]->method() );
		self::assertSame( 'application/json', $client->requests[0]->headers()['Accept'] );
		self::assertStringStartsWith( 'multipart/form-data; boundary=', $client->requests[0]->headers()['Content-Type'] );
		self::assertStringContainsString( 'name="file"; filename="customer.png"', $client->requests[0]->body() );
		self::assertStringContainsString( 'name="file"; filename="product.png"', $client->requests[1]->body() );
		self::assertSame( 'POST', $client->requests[2]->method() );
		self::assertSame( 'application/json', $client->requests[2]->headers()['Content-Type'] );
		self::assertSame( 'application/json', $client->requests[2]->headers()['Accept'] );
		self::assertSame( $generate_fixture['request']['body'], json_decode( $client->requests[2]->body(), true ) );
		self::assertSame( array( $customer_url, $product_url ), json_decode( $client->requests[2]->body(), true )['image_urls'] );
		self::assertSame( self::SCOPE, $storage->last_write['scope'] );
		self::assertSame( 'result', $storage->last_write['role'] );
		self::assertSame( 'png', $storage->last_write['extension'] );
		self::assertSame( 'image/png', $result->mime_type() );
		self::assertSame( 'req_safe-123', $result->provider_request_id() );
		self::assertSame( 10485760, $client->requests[3]->max_response_bytes() );
	}

	/** Mixed string/object image entries are accepted and only the first is downloaded. */
	public function test_accepts_mixed_fixture_shapes_and_downloads_first_result(): void {
		$fixture = $this->fixture( 'seaai-generate-mixed-image-shapes.json' );
		$first   = $fixture['expected']['normalized_urls'][0];
		$client  = $this->client_for_generation( $fixture['response']['body'], $first );
		$result  = $this->provider( $client, new SeaAIFakeStorage( $this->png_bytes() ) )->generate( $this->request() );

		self::assertSame( 'image/png', $result->mime_type() );
		self::assertSame( $first, $client->requests[3]->url() );
		self::assertCount( 4, $client->requests );
	}

	/** Unexpected task_id fails closed and never reaches the legacy query route. */
	public function test_task_fixture_is_contract_error_and_never_queries(): void {
		$fixture = $this->fixture( 'seaai-generate-task-unexpected.json' );
		$legacy  = $this->fixture( 'seaai-query-legacy-evidence.json' );
		$client  = new SeaAIFakeHttpClient(
			array(
				$this->upload_response( 'customer.png' ),
				$this->upload_response( 'product.png' ),
				$this->json_response( 200, $fixture['response']['body'] ),
			)
		);

		try {
			$this->provider( $client, new SeaAIFakeStorage( $this->png_bytes() ) )->generate( $this->request() );
			self::fail( 'Expected provider_contract_error.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( $fixture['expected']['plugin_error_code'], $exception->provider_error()->code() );
			self::assertFalse( $exception->provider_error()->is_retryable() );
			self::assertSame( $fixture['response']['body']['task_id'], $exception->provider_error()->diagnostic_reference() );
		}

		self::assertCount( 3, $client->requests );
		foreach ( $client->requests as $request ) {
			self::assertStringNotContainsString( $legacy['request']['path'], $request->url() );
		}
	}

	/** Every malformed non-empty images shape is a permanent contract error. */
	public function test_rejects_malformed_images_shapes(): void {
		$shapes = array(
			array(),
			array( 'images' => array() ),
			array( 'images' => array( 42 ) ),
			array( 'images' => array( array( 'url' => '' ) ) ),
			array( 'images' => array( array( 'other' => 'https://images.example.com/result.png' ) ) ),
			array(
				'task_id' => 'task_with_images_is_still_async',
				'images'  => array( 'https://images.example.com/result.png' ),
			),
		);

		foreach ( $shapes as $shape ) {
			$client = new SeaAIFakeHttpClient(
				array(
					$this->upload_response( 'customer.png' ),
					$this->upload_response( 'product.png' ),
					$this->json_response( 200, $shape ),
				)
			);
			$error = $this->capture_error( $client );

			self::assertSame( 'provider_contract_error', $error->provider_error()->code() );
			self::assertFalse( $error->provider_error()->is_retryable() );
			self::assertCount( 3, $client->requests );
		}
	}

	/** Upload success without a safe download_url fails before the second upload. */
	public function test_requires_safe_upload_download_url(): void {
		$client = new SeaAIFakeHttpClient(
			array( $this->json_response( 200, array( 'file_name' => 'customer.png' ) ) )
		);

		$error = $this->capture_error( $client );
		self::assertSame( 'provider_contract_error', $error->provider_error()->code() );
		self::assertCount( 1, $client->requests );
	}

	/** Invalid JSON is permanent and distinct from valid JSON contract drift. */
	public function test_invalid_json_is_nonretryable_invalid_response(): void {
		$client = new SeaAIFakeHttpClient(
			array(
				$this->upload_response( 'customer.png' ),
				$this->upload_response( 'product.png' ),
				new HttpResponse( 200, array( 'Content-Type' => 'application/json' ), '{invalid' ),
			)
		);

		$error = $this->capture_error( $client );
		self::assertSame( 'provider_invalid_response', $error->provider_error()->code() );
		self::assertFalse( $error->provider_error()->is_retryable() );
	}

	/** An SSRF-shaped result URL is rejected before the downloader sends a GET. */
	public function test_rejects_ssrf_result_url_without_downloading(): void {
		$client = new SeaAIFakeHttpClient(
			array(
				$this->upload_response( 'customer.png' ),
				$this->upload_response( 'product.png' ),
				$this->json_response( 200, array( 'images' => array( 'https://127.0.0.1/private.png' ) ) ),
			)
		);

		$error = $this->capture_error( $client );
		self::assertSame( 'provider_contract_error', $error->provider_error()->code() );
		self::assertFalse( $error->provider_error()->is_retryable() );
		self::assertCount( 3, $client->requests );
	}

	/** Network transport failures are retry-eligible and never expose exception details. */
	public function test_network_failure_is_retryable_and_safe(): void {
		$client = new SeaAIFakeHttpClient(
			array( new TransportException( 'network_error', 'test-leaked https://secret.test/result', true ) )
		);

		$error = $this->capture_error( $client );
		self::assertSame( 'provider_network_failure', $error->provider_error()->code() );
		self::assertTrue( $error->provider_error()->is_retryable() );
		self::assertStringNotContainsString( 'test-leaked', $error->getMessage() );
		self::assertStringNotContainsString( 'secret.test', $error->getMessage() );
	}

	/** `auto` quality is normalized to the user-required SeaAI default `low`. */
	public function test_auto_quality_uses_low_default_and_mask_is_omitted(): void {
		$client = $this->client_for_generation(
			array( 'images' => array( 'https://images.example.com/result.png' ) ),
			'https://images.example.com/result.png'
		);
		$this->provider( $client, new SeaAIFakeStorage( $this->png_bytes() ) )->generate( $this->request( 'auto', 'auto' ) );
		$payload = json_decode( $client->requests[2]->body(), true );

		self::assertSame( 'low', $payload['quality'] );
		self::assertArrayNotHasKey( 'mask', $payload );
		self::assertSame( 'auto', $payload['resolution'] );
		self::assertSame( 'auto', $payload['size'] );
	}

	/** Production cannot enable cleartext localhost merely by passing a flag. */
	public function test_production_environment_rejects_insecure_local_root_even_when_flag_is_true(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->provider(
			new SeaAIFakeHttpClient( array() ),
			new SeaAIFakeStorage( $this->png_bytes() ),
			'http://127.22.33.44/site/wp-json/seaai/v1',
			true,
			static function (): string {
				return 'production';
			}
		);
	}

	/** Development explicitly allows the complete 127/8 loopback range. */
	public function test_development_environment_allows_explicit_127_loopback_root(): void {
		$provider = $this->provider(
			new SeaAIFakeHttpClient( array() ),
			new SeaAIFakeStorage( $this->png_bytes() ),
			'http://127.22.33.44/site/wp-json/seaai/v1',
			true,
			static function (): string {
				return 'development';
			}
		);

		self::assertInstanceOf( SeaAIProvider::class, $provider );
	}

	/** The configured URL must already be the exact versioned API root. */
	public function test_rejects_base_url_with_endpoint_appended(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->provider(
			new SeaAIFakeHttpClient( array() ),
			new SeaAIFakeStorage( $this->png_bytes() ),
			'https://gateway.example.test/wp-json/seaai/v1/forward/image/generate'
		);
	}

	/** Build the provider using one ordered HTTP queue. */
	private function provider(
		SeaAIFakeHttpClient $client,
		SeaAIFakeStorage $storage,
		string $base_url = 'https://gateway.example.test/wp-json/seaai/v1',
		bool $allow_insecure_local = false,
		?callable $environment = null
	): SeaAIProvider {
		$validator = new ImageValidator( 10485760, 4096, 16777216, false );
		$policy    = new UrlSafetyPolicy(
			$allow_insecure_local,
			static function ( string $host ): array {
				return 0 === strpos( $host, '127.' ) ? array( $host ) : array( '8.8.8.8' );
			}
		);
		$download = new RemoteImageDownloader( $client, $validator, $policy );

		return new SeaAIProvider(
			new ProviderTransport( $client ),
			$download,
			$storage,
			$base_url,
			$this->provider_key( 'test-safe-key' ),
			$validator,
			null,
			$allow_insecure_local,
			$environment,
			static function ( string $host ): array {
				return 0 === strpos( $host, '127.' ) ? array( $host ) : array( '8.8.8.8' );
			}
		);
	}

	/** Build a syntactically valid provider key from a non-sensitive suffix. */
	private function provider_key( string $suffix ): string {
		return implode( '-', array( 'sk', $suffix ) );
	}

	/** Build the normal provider request. */
	private function request( string $quality = 'low', string $size = 'auto' ): ProviderRequest {
		return new ProviderRequest(
			'job-test-1',
			self::CUSTOMER,
			self::PRODUCT,
			'Create a realistic virtual try-on while preserving identity and product details.',
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			$quality,
			$size
		);
	}

	/** Build two upload responses, generation response, and result download. */
	private function client_for_generation( array $generation_body, string $result_url ): SeaAIFakeHttpClient {
		return new SeaAIFakeHttpClient(
			array(
				$this->upload_response( 'customer.png' ),
				$this->upload_response( 'product.png' ),
				$this->json_response( 200, $generation_body ),
				new HttpResponse( 200, array( 'Content-Type' => 'image/png' ), $this->png_bytes() ),
			)
		);
	}

	/** Capture the normalized provider exception. */
	private function capture_error( SeaAIFakeHttpClient $client ): ProviderException {
		try {
			$this->provider( $client, new SeaAIFakeStorage( $this->png_bytes() ) )->generate( $this->request() );
			self::fail( 'Expected ProviderException.' );
		} catch ( ProviderException $exception ) {
			return $exception;
		}
	}

	/** Build one gateway upload response. */
	private function upload_response( string $name ): HttpResponse {
		return $this->json_response( 200, array( 'download_url' => 'https://gateway.example.test/uploads/' . $name ) );
	}

	/** Build a bounded JSON HTTP response. */
	private function json_response( int $status, array $body, array $headers = array() ): HttpResponse {
		$headers['Content-Type'] = 'application/json';

		return new HttpResponse( $status, $headers, (string) json_encode( $body ) );
	}

	/** Load one committed M0 fixture. */
	private function fixture( string $name ): array {
		$path    = dirname( __DIR__, 4 ) . '/sea-tryon-doc/m0/fixtures/' . $name;
		$decoded = json_decode( (string) file_get_contents( $path ), true );

		self::assertIsArray( $decoded );

		return $decoded;
	}

	/** Return a small valid PNG used for input and result validation. */
	private function png_bytes(): string {
		return (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6yAAAAAASUVORK5CYII=', true );
	}

	/** Return the relevant endpoint suffix from one absolute request URL. */
	private function path_suffix( string $url ): string {
		$path = (string) parse_url( $url, PHP_URL_PATH );

		return (string) substr( $path, strlen( '/wp-json/seaai/v1' ) );
	}
}

/** Ordered in-memory HTTP client for provider contract tests. */
final class SeaAIFakeHttpClient implements HttpClientInterface {

	/** @var array<HttpRequest> */
	public $requests = array();

	/** @var array<HttpResponse|TransportException> */
	private $responses;

	/** @param array<HttpResponse|TransportException> $responses Ordered outcomes. */
	public function __construct( array $responses ) {
		$this->responses = array_values( $responses );
	}

	/** {@inheritDoc} */
	public function request( HttpRequest $request ): HttpResponse {
		$this->requests[] = $request;
		$outcome          = array_shift( $this->responses );

		if ( $outcome instanceof TransportException ) {
			throw $outcome;
		}

		if ( ! $outcome instanceof HttpResponse ) {
			throw new TransportException( 'test_queue_empty', 'The fake HTTP queue is empty.' );
		}

		return $outcome;
	}
}

/** Minimal private storage double retaining same-scope result writes. */
final class SeaAIFakeStorage implements TemporaryStorageInterface {

	/** @var string */
	private $bytes;

	/** @var array<string,mixed> */
	public $last_write = array();

	public function __construct( string $bytes ) {
		$this->bytes = $bytes;
	}

	public function create_scope(): string {
		return SeaAIProviderTest::SCOPE;
	}

	public function write( string $scope_id, string $role, string $contents, string $extension ): string {
		$this->last_write = array(
			'scope'     => $scope_id,
			'role'      => $role,
			'contents'  => $contents,
			'extension' => $extension,
		);

		return $scope_id . '/result-cccccccccccccccccccccccccccccccc.' . $extension;
	}

	public function read( string $storage_id ): string {
		return $this->bytes;
	}

	public function absolute_path( string $storage_id ): string {
		return 'private/' . $storage_id;
	}

	public function delete( string $storage_id ): bool {
		return true;
	}

	public function delete_scope( string $scope_id ): bool {
		return true;
	}

	public function cleanup_expired(): int {
		return 0;
	}

	public function root_path(): string {
		return 'private';
	}
}
