<?php
/**
 * SeaAI error mapper tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider\SeaAI;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Provider\SeaAI\SeaAIErrorMapper;

defined( 'ABSPATH' ) || exit;

/** Verifies the complete M0 SeaAI HTTP fixture matrix. */
final class SeaAIErrorMapperTest extends TestCase {

	/** Every M0 status fixture maps to its accepted retry classification. */
	public function test_maps_all_m0_error_fixtures(): void {
		$fixture = $this->fixture( 'seaai-errors.json' );
		$mapper  = new SeaAIErrorMapper();

		self::assertIsArray( $fixture['cases'] );
		foreach ( $fixture['cases'] as $case ) {
			$response = $case['response'];
			$expected = $case['expected'];
			$error    = $mapper->from_http_response(
				(int) $response['http_status'],
				(string) json_encode( $response['body'] )
			);

			self::assertSame( $expected['plugin_error_code'], $error->code(), (string) $case['name'] );
			$retryable = isset( $expected['retry_eligible'] ) ? $expected['retry_eligible'] : $expected['retry'];
			self::assertSame( $retryable, $error->is_retryable(), (string) $case['name'] );
		}
	}

	/** A machine-safe 403 rate-limit code remains permanent by ADR decision. */
	public function test_disambiguates_rate_limit_code_without_using_message(): void {
		$mapper = new SeaAIErrorMapper();
		$error  = $mapper->from_http_response(
			403,
			'{"code":"rate_limit_exceeded","message":"secret URL https://private.test/result"}'
		);

		self::assertSame( 'provider_rate_limited', $error->code() );
		self::assertFalse( $error->is_retryable() );
		self::assertStringNotContainsString( 'private.test', $error->message() );
	}

	/** HTTP 401 has the ADR-specific missing-auth code and is permanent. */
	public function test_maps_unauthorized_status(): void {
		$error = ( new SeaAIErrorMapper() )->from_http_response( 401, '{"code":"unauthorized"}' );

		self::assertSame( 'provider_auth_missing', $error->code() );
		self::assertFalse( $error->is_retryable() );
	}

	/** Load one committed M0 fixture. */
	private function fixture( string $name ): array {
		$path    = dirname( __DIR__, 4 ) . '/sea-tryon-doc/m0/fixtures/' . $name;
		$decoded = json_decode( (string) file_get_contents( $path ), true );

		self::assertIsArray( $decoded );

		return $decoded;
	}
}
