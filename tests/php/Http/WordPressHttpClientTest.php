<?php
/**
 * WordPress HTTP client tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\TransportException;
use SeaTryOn\Http\WordPressHttpClient;

defined( 'ABSPATH' ) || exit;

final class WordPressHttpClientTest extends TestCase {

	public function test_exposes_only_bounded_safe_wordpress_options(): void {
		$captured = array();
		$client   = new WordPressHttpClient(
			static function ( string $url, array $args ) use ( &$captured ): array {
				$captured = array( $url, $args );

				return array(
					'response' => array( 'code' => 201 ),
					'headers'  => array( 'X-Request-ID' => 'safe-id' ),
					'body'     => '{}',
				);
			}
		);

		$response = $client->request( new HttpRequest( 'POST', 'https://api.example/edit', array(), '{}', 60, 1024, 1 ) );

		self::assertSame( 'https://api.example/edit', $captured[0] );
		self::assertTrue( $captured[1]['reject_unsafe_urls'] );
		self::assertFalse( $captured[1]['stream'] );
		self::assertSame( 1024, $captured[1]['limit_response_size'] );
		self::assertSame( 1, $captured[1]['redirection'] );
		self::assertSame( 201, $response->status() );
		self::assertSame( 'safe-id', $response->header( 'x-request-id' ) );
	}

	public function test_rechecks_response_body_size_after_wordpress(): void {
		$client = new WordPressHttpClient(
			static function (): array {
				return array(
					'response' => array( 'code' => 200 ),
					'headers'  => array(),
					'body'     => '12345',
				);
			}
		);

		$this->expectException( TransportException::class );
		$this->expectExceptionMessage( 'exceeded' );
		$client->request( new HttpRequest( 'GET', 'https://api.example/image', array(), '', 30, 4 ) );
	}

	public function test_timeout_is_classified_without_exposing_wordpress_error_details(): void {
		$error  = new class() {
			public function get_error_code(): string {
				return 'http_request_failed';
			}

			public function get_error_message(): string {
				return 'cURL error 28: timed out fetching https://secret.example/?key=do-not-leak';
			}
		};
		$client = new WordPressHttpClient(
			static function () use ( $error ) {
				return $error;
			}
		);

		try {
			$client->request( new HttpRequest( 'GET', 'https://api.example/image' ) );
			self::fail( 'Expected a transport failure.' );
		} catch ( TransportException $exception ) {
			self::assertSame( 'timeout', $exception->reason() );
			self::assertTrue( $exception->is_retryable() );
			self::assertStringNotContainsString( 'do-not-leak', $exception->getMessage() );
		}
	}

	public function test_headers_reject_crlf_injection(): void {
		$this->expectException( InvalidArgumentException::class );
		new HttpRequest( 'GET', 'https://api.example/image', array( 'X-Test' => "safe\r\nX-Evil: yes" ) );
	}

	public function test_development_loopback_uses_explicit_unsafe_requester_only(): void {
		$safe_calls   = 0;
		$unsafe_calls = 0;
		$response     = array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '{}' );
		$client       = new WordPressHttpClient(
			static function () use ( &$safe_calls, $response ): array {
				++$safe_calls;
				return $response;
			},
			true,
			static function (): string {
				return 'development';
			},
			static function ( string $url, array $args ) use ( &$unsafe_calls, $response ): array {
				unset( $url );
				++$unsafe_calls;
				self::assertFalse( $args['reject_unsafe_urls'] );
				self::assertSame( 0, $args['redirection'] );
				return $response;
			}
		);

		$client->request( new HttpRequest( 'POST', 'http://127.0.0.1/endpoint' ) );
		self::assertSame( 0, $safe_calls );
		self::assertSame( 1, $unsafe_calls );
	}

	public function test_production_rejects_cleartext_and_https_public_uses_safe_requester(): void {
		$safe_calls   = 0;
		$unsafe_calls = 0;
		$response     = array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '{}' );
		$client       = new WordPressHttpClient(
			static function ( string $url, array $args ) use ( &$safe_calls, $response ): array {
				unset( $url );
				++$safe_calls;
				self::assertTrue( $args['reject_unsafe_urls'] );
				return $response;
			},
			true,
			static function (): string {
				return 'production';
			},
			static function () use ( &$unsafe_calls, $response ): array {
				++$unsafe_calls;
				return $response;
			}
		);

		try {
			$client->request( new HttpRequest( 'POST', 'http://127.0.0.1/endpoint' ) );
			self::fail( 'Production must reject cleartext loopback.' );
		} catch ( TransportException $exception ) {
			self::assertSame( 'insecure_url', $exception->reason() );
		}
		$client->request( new HttpRequest( 'GET', 'https://cdn.example/image.png' ) );
		self::assertSame( 1, $safe_calls );
		self::assertSame( 0, $unsafe_calls );
	}

	/** @dataProvider provideFakeLoopbackHosts */
	public function test_development_fake_loopback_hostnames_never_use_unsafe_requester( string $url ): void {
		$safe_calls   = 0;
		$unsafe_calls = 0;
		$response     = array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '{}' );
		$client       = new WordPressHttpClient(
			static function () use ( &$safe_calls, $response ): array {
				++$safe_calls;
				return $response;
			},
			true,
			static function (): string {
				return 'development';
			},
			static function () use ( &$unsafe_calls, $response ): array {
				++$unsafe_calls;
				return $response;
			}
		);

		try {
			$client->request( new HttpRequest( 'GET', $url ) );
			self::fail( 'Fake loopback hosts must not receive cleartext access.' );
		} catch ( TransportException $exception ) {
			self::assertSame( 'insecure_url', $exception->reason() );
		}
		self::assertSame( 0, $safe_calls );
		self::assertSame( 0, $unsafe_calls );
	}

	/** @return array<string,array{string}> */
	public function provideFakeLoopbackHosts(): array {
		return array(
			'hostname prefix' => array( 'http://127.evil.example/image.png' ),
			'invalid IP text' => array( 'http://127.999.1.1/image.png' ),
		);
	}
}
