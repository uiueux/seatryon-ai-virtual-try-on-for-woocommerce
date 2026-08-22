<?php
/**
 * SeaAI settings connection test service tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! function_exists( '__' ) ) {
		/** Test translation fallback. */
		function __( string $text, string $domain = 'default' ): string {
			unset( $domain );

			return $text;
		}
	}
}

namespace SeaTryOn\Tests\Admin\Settings {

use PHPUnit\Framework\TestCase;
use SeaTryOn\Admin\Settings\SeaAIConnectionTester;
use SeaTryOn\Http\HttpClientInterface;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Http\TransportException;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\OptionsStoreInterface;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/** Verifies safe credential resolution and the probe response contract. */
final class SeaAIConnectionTesterTest extends TestCase {

	public function test_uses_unsaved_url_and_key_for_a_successful_probe(): void {
		$http   = new SeaAIConnectionFakeHttpClient( new HttpResponse( 200, array(), '{"authenticated":true}' ) );
		$tester = $this->tester( $http );

		$result = $tester->test( 'https://gateway.example/wp-json/seaai/v1/', $this->provider_key( 'test-new-key' ) );

		self::assertTrue( $result->is_success() );
		self::assertSame( 'connection_ok', $result->code() );
		self::assertCount( 1, $http->requests );
		self::assertSame( 'https://gateway.example/wp-json/seaai/v1/connection-test', $http->requests[0]->url() );
		self::assertSame( 'Bearer ' . $this->provider_key( 'test-new-key' ), $http->requests[0]->headers()['Authorization'] );
		self::assertSame( 15, $http->requests[0]->timeout() );
		self::assertSame( 65536, $http->requests[0]->max_response_bytes() );
	}

	public function test_mask_uses_saved_key_even_when_openai_is_the_saved_provider(): void {
		$http   = new SeaAIConnectionFakeHttpClient( new HttpResponse( 200, array(), '{"data":{"authenticated":true}}' ) );
		$tester = $this->tester(
			$http,
			array(
				SettingsRepository::OPTION_PROVIDER      => SettingsRepository::PROVIDER_WORDPRESS_AI,
				SettingsRepository::OPTION_SEAAI_API_KEY => $this->provider_key( 'test-saved-key' ),
			)
		);

		$result = $tester->test( 'https://gateway.example/wp-json/seaai/v1', SecretStore::MASK );

		self::assertTrue( $result->is_success() );
		self::assertSame( 'Bearer ' . $this->provider_key( 'test-saved-key' ), $http->requests[0]->headers()['Authorization'] );
	}

	public function test_falls_back_to_a_non_uploading_auth_probe_for_older_gateways(): void {
		$http = new SeaAIConnectionFakeHttpClient(
			array(
				new HttpResponse( 404, array(), '{"code":"rest_no_route"}' ),
				new HttpResponse( 400, array(), '{"code":"missing_file"}' ),
			)
		);

		$result = $this->tester( $http )->test( 'https://gateway.example/wp-json/seaai/v1', $this->provider_key( 'test-key' ) );

		self::assertTrue( $result->is_success() );
		self::assertCount( 2, $http->requests );
		self::assertSame( 'https://gateway.example/wp-json/seaai/v1/connection-test', $http->requests[0]->url() );
		self::assertSame( 'https://gateway.example/wp-json/seaai/v1/forward/image/upload', $http->requests[1]->url() );
		self::assertSame( '{}', $http->requests[1]->body() );
	}

	public function test_invalid_input_never_reaches_the_network(): void {
		$http   = new SeaAIConnectionFakeHttpClient( new HttpResponse( 200, array(), '{"authenticated":true}' ) );
		$tester = $this->tester( $http );

		self::assertSame( 'invalid_url', $tester->test( 'http://public.example/wp-json/seaai/v1', 'test-key' )->code() );
		self::assertSame( 'invalid_key', $tester->test( 'https://gateway.example/wp-json/seaai/v1', "test-key\r\nInjected: yes" )->code() );
		self::assertCount( 0, $http->requests );
	}

	public function test_maps_authentication_and_contract_failures_to_safe_codes(): void {
		$unauthorized = $this->tester( new SeaAIConnectionFakeHttpClient( new HttpResponse( 401, array(), '{"secret":"must-not-surface"}' ) ) )
			->test( 'https://gateway.example/wp-json/seaai/v1', $this->provider_key( 'test-key' ) );
		$invalid       = $this->tester( new SeaAIConnectionFakeHttpClient( new HttpResponse( 200, array(), '{"message":"ok"}' ) ) )
			->test( 'https://gateway.example/wp-json/seaai/v1', $this->provider_key( 'test-key' ) );

		self::assertSame( 'authentication_failed', $unauthorized->code() );
		self::assertStringNotContainsString( 'must-not-surface', $unauthorized->message() );
		self::assertSame( 'invalid_response', $invalid->code() );
	}

	public function test_maps_transport_timeout_without_exposing_exception_details(): void {
		$http   = new SeaAIConnectionFakeHttpClient( new TransportException( 'timeout', 'contains sensitive URL', true ) );
		$result = $this->tester( $http )->test( 'https://gateway.example/wp-json/seaai/v1', $this->provider_key( 'test-key' ) );

		self::assertSame( 'timeout', $result->code() );
		self::assertStringNotContainsString( 'sensitive', $result->message() );
	}

	/** Build a syntactically valid provider key from a non-sensitive suffix. */
	private function provider_key( string $suffix ): string {
		return implode( '-', array( 'sk', $suffix ) );
	}

	/**
	 * @param array<string,mixed> $values Stored settings.
	 */
	private function tester( SeaAIConnectionFakeHttpClient $http, array $values = array() ): SeaAIConnectionTester {
		$settings = new SettingsRepository( new SeaAIConnectionMemoryStore( $values ) );
		$secrets  = new SecretStore( $settings );
		$urls     = new SeaAIBaseUrlValidator(
			static function (): string {
				return 'production';
			},
			static function (): string {
				return 'https://shop.example/';
			}
		);

		return new SeaAIConnectionTester( $secrets, $urls, $http );
	}
}

/** Injectable HTTP fake. */
final class SeaAIConnectionFakeHttpClient implements HttpClientInterface {

	/** @var array<int,HttpResponse|TransportException> */
	private $results;

	/** @var array<int,HttpRequest> */
	public $requests = array();

	/** @param HttpResponse|TransportException|array<int,HttpResponse|TransportException> $result Response or failure. */
	public function __construct( $result ) {
		$this->results = is_array( $result ) ? array_values( $result ) : array( $result );
	}

	public function request( HttpRequest $request ): HttpResponse {
		$this->requests[] = $request;
		$result           = array_shift( $this->results );
		if ( $result instanceof TransportException ) {
			throw $result;
		}

		if ( ! $result instanceof HttpResponse ) {
			throw new \RuntimeException( 'The fake HTTP response queue is empty.' );
		}

		return $result;
	}
}

/** In-memory settings store. */
final class SeaAIConnectionMemoryStore implements OptionsStoreInterface {

	/** @var array<string,mixed> */
	private $values;

	/** @param array<string,mixed> $values Initial values. */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function get( string $name, $default = null ) {
		return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : $default;
	}

	public function update( string $name, $value, bool $autoload = false ): bool {
		unset( $autoload );
		$this->values[ $name ] = $value;

		return true;
	}
}
}