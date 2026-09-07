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

	/** Safety rejections remain permanent even when the gateway wraps them as a 502. */
	public function test_maps_moderation_blocked_as_a_nonretryable_safety_rejection(): void {
		$error = ( new SeaAIErrorMapper() )->from_http_response(
			502,
			'{"error":{"message":"unsafe image","type":"image_generation_user_error","code":"moderation_blocked"}}'
		);

		self::assertSame( 'provider_safety_rejected', $error->code() );
		self::assertFalse( $error->is_retryable() );
		self::assertSame( 502, $error->http_status() );
	}

	/** The upstream base_resp safety message remains terminal after a 502 wrapper. */
	public function test_maps_base_response_safety_rejection_as_nonretryable(): void {
		$error = ( new SeaAIErrorMapper() )->from_http_response(
			502,
			'{"created":0,"trace_id":"06ed9e75f9b90e97311379f3d9bf3664","base_resp":{"status_code":400,"status_msg":"Your request was rejected by the safety system. If you believe this is an error, contact us at help.openai.com"}}'
		);

		self::assertSame( 'provider_safety_rejected', $error->code() );
		self::assertFalse( $error->is_retryable() );
		self::assertSame( 502, $error->http_status() );
	}

	/** A WordPress REST error wrapper must not turn a safety rejection into a retry. */
	public function test_maps_wrapped_safety_message_as_nonretryable(): void {
		$error = ( new SeaAIErrorMapper() )->from_http_response(
			502,
			'{"code":"image_error","message":"Your request was rejected by the safety system. If you believe this is an error, contact us at help.openai.com","data":{"status":502}}'
		);

		self::assertSame( 'provider_safety_rejected', $error->code() );
		self::assertFalse( $error->is_retryable() );
		self::assertSame( 502, $error->http_status() );
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
