<?php
/**
 * OpenAI provider contract tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider\OpenAI;

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
use SeaTryOn\Provider\OpenAI\OpenAIProvider;
use SeaTryOn\Storage\StorageException;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

// Retained only as historical coverage for the removed direct provider adapter.
return;

abstract class OpenAIProviderTest extends TestCase {

	private const SCOPE           = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const CUSTOMER_REF    = self::SCOPE . '/customer-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png';
	private const PRODUCT_REF     = self::SCOPE . '/product-cccccccccccccccccccccccccccccccc.png';
	private const PNG_BASE64      = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlFH4QAAAAASUVORK5CYII=';
	private const SYNTHETIC_KEY   = 'test-openai-contract-only';

	public function test_builds_frozen_ordered_edit_request_and_persists_private_png(): void {
		$fixture  = $this->fixture( 'openai-success.json' );
		$response = $this->fixture_response( $fixture );
		$client   = new OpenAIFakeHttpClient( $response );
		$storage  = $this->storage();
		$result   = $this->provider( $client, $storage )->generate( $this->request( 'high' ) );

		self::assertNotNull( $client->last_request );
		self::assertSame( 'POST', $client->last_request->method() );
		self::assertSame( OpenAIProvider::ENDPOINT, $client->last_request->url() );
		self::assertSame( 120, $client->last_request->timeout() );
		self::assertSame( 15728640, $client->last_request->max_response_bytes() );
		self::assertSame( 0, $client->last_request->redirections() );
		self::assertSame( 'Bearer ' . self::SYNTHETIC_KEY, $client->last_request->headers()['Authorization'] );

		$body = $client->last_request->body();
		self::assertSame( 2, substr_count( $body, 'name="image[]"' ) );
		self::assertLessThan( strpos( $body, 'filename="product.png"' ), strpos( $body, 'filename="customer.png"' ) );
		foreach (
			array(
				'name="model"' . "\r\n\r\n" . 'gpt-image-2',
				'name="n"' . "\r\n\r\n" . '1',
				'name="size"' . "\r\n\r\n" . 'auto',
				'name="quality"' . "\r\n\r\n" . 'high',
				'name="output_format"' . "\r\n\r\n" . 'png',
				'name="background"' . "\r\n\r\n" . 'auto',
			) as $required
		) {
			self::assertStringContainsString( $required, $body );
		}

		foreach ( array( 'input_fidelity', 'name="mask"', 'name="stream"', 'partial_images' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $body );
		}

		self::assertSame( self::SCOPE . '/result-dddddddddddddddddddddddddddddddd.png', $result->result_reference() );
		self::assertSame( 'image/png', $result->mime_type() );
		self::assertSame( strlen( base64_decode( self::PNG_BASE64, true ) ), $result->byte_size() );
		self::assertSame( 'req_mock_openai_success_001', $result->provider_request_id() );
		self::assertSame( self::SCOPE, $storage->last_write_scope );
		self::assertSame( 'result', $storage->last_write_role );
		self::assertSame( 'png', $storage->last_write_extension );
	}

	/**
	 * @dataProvider provideFixtureErrors
	 */
	public function test_maps_frozen_error_fixtures( string $filename, string $expected_code, bool $retryable, ?int $retry_after ): void {
		$client = new OpenAIFakeHttpClient( $this->fixture_response( $this->fixture( $filename ) ) );

		try {
			$this->provider( $client, $this->storage() )->generate( $this->request() );
			self::fail( 'The provider should throw.' );
		} catch ( ProviderException $exception ) {
			$error = $exception->provider_error();
			self::assertSame( $expected_code, $error->code() );
			self::assertSame( $retryable, $error->is_retryable() );
			self::assertSame( $retry_after, $error->retry_after_seconds() );
			self::assertStringNotContainsString( 'Revise the prompt', $exception->getMessage() );
		}
	}

	/** @return array<string,array{string,string,bool,int|null}> */
	public function provideFixtureErrors(): array {
		return array(
			'user error'   => array( 'openai-user-error.json', 'openai_image_user_error', false, null ),
			'rate limit'   => array( 'openai-rate-limit.json', 'openai_rate_limited', true, 12 ),
			'server error' => array( 'openai-server-error.json', 'openai_service_unavailable', true, null ),
		);
	}

	/**
	 * @dataProvider provideHttpErrors
	 */
	public function test_maps_http_status_matrix( int $status, string $code, string $type, string $expected, bool $retryable ): void {
		$body   = (string) json_encode( array( 'error' => array( 'code' => $code, 'type' => $type, 'message' => self::SYNTHETIC_KEY ) ) );
		$client = new OpenAIFakeHttpClient( new HttpResponse( $status, array(), $body ) );

		try {
			$this->provider( $client, $this->storage() )->generate( $this->request() );
			self::fail( 'The provider should throw.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( $expected, $exception->provider_error()->code() );
			self::assertSame( $retryable, $exception->provider_error()->is_retryable() );
			self::assertStringNotContainsString( self::SYNTHETIC_KEY, $exception->getMessage() );
		}
	}

	/** @return array<string,array{int,string,string,string,bool}> */
	public function provideHttpErrors(): array {
		return array(
			'401'              => array( 401, 'invalid_api_key', 'authentication_error', 'openai_authentication_failed', false ),
			'403'              => array( 403, 'access_denied', 'permission_error', 'openai_access_denied', false ),
			'credit 429'       => array( 429, 'billing_hard_limit_reached', 'rate_limit_error', 'openai_quota_exhausted', false ),
			'moderation'       => array( 400, 'content_policy_violation', 'image_generation_user_error', 'openai_moderation_blocked', false ),
			'422'              => array( 422, 'invalid_value', 'invalid_request_error', 'openai_invalid_request', false ),
			'ordinary 429'     => array( 429, 'rate_limit_exceeded', 'rate_limit_error', 'openai_rate_limited', true ),
			'500'              => array( 500, 'server_error', 'server_error', 'openai_service_unavailable', true ),
		);
	}

	/**
	 * @dataProvider provideMalformedSuccessBodies
	 */
	public function test_rejects_malformed_success_without_writing( string $body ): void {
		$storage = $this->storage();
		$client  = new OpenAIFakeHttpClient( new HttpResponse( 200, array(), $body ) );

		try {
			$this->provider( $client, $storage )->generate( $this->request() );
			self::fail( 'The provider should throw.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( 'openai_invalid_response', $exception->provider_error()->code() );
			self::assertTrue( $exception->provider_error()->is_retryable() );
			self::assertNull( $storage->last_write_scope );
		}
	}

	/** @return array<string,array{string}> */
	public function provideMalformedSuccessBodies(): array {
		$jpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAFH/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=';

		return array(
			'non-json'        => array( '<html>error</html>' ),
			'missing data'    => array( '{}' ),
			'empty data'      => array( '{"data":[]}' ),
			'invalid base64'  => array( '{"data":[{"b64_json":"not base64!"}]}' ),
			'non-png image'   => array( (string) json_encode( array( 'data' => array( array( 'b64_json' => $jpeg ) ) ) ) ),
			'oversized bytes' => array( (string) json_encode( array( 'data' => array( array( 'b64_json' => str_repeat( 'A', 13981020 ) ) ) ) ) ),
		);
	}

	/**
	 * @dataProvider provideTransportErrors
	 */
	public function test_maps_transport_errors( string $reason, bool $retryable, string $expected ): void {
		$client            = new OpenAIFakeHttpClient( new HttpResponse( 200, array(), '{}' ) );
		$client->exception = new TransportException( $reason, 'must not contain request bytes', $retryable );

		try {
			$this->provider( $client, $this->storage() )->generate( $this->request() );
			self::fail( 'The provider should throw.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( $expected, $exception->provider_error()->code() );
		}
	}

	/** @return array<string,array{string,bool,string}> */
	public function provideTransportErrors(): array {
		return array(
			'timeout'            => array( 'timeout', true, 'openai_timeout' ),
			'network'            => array( 'network_error', true, 'openai_network_error' ),
			'oversized response' => array( 'response_too_large', false, 'openai_invalid_response' ),
			'unsafe request'     => array( 'http_unavailable', false, 'openai_transport_error' ),
		);
	}

	public function test_discards_unsafe_request_id(): void {
		$body     = (string) json_encode( array( 'data' => array( array( 'b64_json' => self::PNG_BASE64 ) ) ) );
		$response = new HttpResponse( 200, array( 'x-request-id' => "req-ok\r\nAuthorization: secret" ), $body );
		$result   = $this->provider( new OpenAIFakeHttpClient( $response ), $this->storage() )->generate( $this->request() );

		self::assertNull( $result->provider_request_id() );
	}

	private function provider( OpenAIFakeHttpClient $client, OpenAIMemoryStorage $storage ): OpenAIProvider {
		return new OpenAIProvider(
			new ProviderTransport( $client ),
			$storage,
			self::SYNTHETIC_KEY,
			new ImageValidator( 10485760, 4096, 16777216, false )
		);
	}

	private function request( string $quality = 'auto' ): ProviderRequest {
		return new ProviderRequest(
			'job-0000000000000001',
			self::CUSTOMER_REF,
			self::PRODUCT_REF,
			'Use image 1 as the customer and image 2 as the product.',
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			$quality,
			'1536x1024'
		);
	}

	private function storage(): OpenAIMemoryStorage {
		$png = base64_decode( self::PNG_BASE64, true );
		self::assertIsString( $png );

		return new OpenAIMemoryStorage(
			array(
				self::CUSTOMER_REF => $png,
				self::PRODUCT_REF  => $png,
			)
		);
	}

	/** @return array<mixed> */
	private function fixture( string $filename ): array {
		$path    = dirname( __DIR__, 4 ) . '/sea-tryon-doc/m0/fixtures/' . $filename;
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		self::assertIsArray( $decoded );

		return $decoded;
	}

	/** @param array<mixed> $fixture */
	private function fixture_response( array $fixture ): HttpResponse {
		/** @var array{status:int,headers:array<string,string>,body:array<mixed>} $response */
		$response = $fixture['response'];

		return new HttpResponse( $response['status'], $response['headers'], (string) json_encode( $response['body'] ) );
	}
}

final class OpenAIFakeHttpClient implements HttpClientInterface {

	/** @var HttpRequest|null */
	public $last_request;

	/** @var TransportException|null */
	public $exception;

	/** @var HttpResponse */
	private $response;

	public function __construct( HttpResponse $response ) {
		$this->response     = $response;
		$this->last_request = null;
		$this->exception    = null;
	}

	public function request( HttpRequest $request ): HttpResponse {
		$this->last_request = $request;
		if ( null !== $this->exception ) {
			throw $this->exception;
		}

		return $this->response;
	}
}

final class OpenAIMemoryStorage implements TemporaryStorageInterface {

	/** @var array<string,string> */
	private $files;

	/** @var string|null */
	public $last_write_scope;

	/** @var string|null */
	public $last_write_role;

	/** @var string|null */
	public $last_write_extension;

	/** @param array<string,string> $files */
	public function __construct( array $files ) {
		$this->files                = $files;
		$this->last_write_scope     = null;
		$this->last_write_role      = null;
		$this->last_write_extension = null;
	}

	public function create_scope(): string {
		return 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	}

	public function write( string $scope_id, string $role, string $contents, string $extension ): string {
		$this->last_write_scope     = $scope_id;
		$this->last_write_role      = $role;
		$this->last_write_extension = $extension;
		$reference                  = $scope_id . '/result-dddddddddddddddddddddddddddddddd.' . $extension;
		$this->files[ $reference ]  = $contents;

		return $reference;
	}

	public function read( string $storage_id ): string {
		if ( ! isset( $this->files[ $storage_id ] ) ) {
			throw new StorageException( 'Missing.' );
		}

		return $this->files[ $storage_id ];
	}

	public function absolute_path( string $storage_id ): string {
		return 'private/' . $storage_id;
	}

	public function delete( string $storage_id ): bool {
		unset( $this->files[ $storage_id ] );
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
