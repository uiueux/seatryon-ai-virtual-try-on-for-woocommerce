<?php
/**
 * OpenAI error mapper tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider\OpenAI;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Provider\OpenAI\OpenAIErrorMapper;

// Retained only as historical coverage for the removed direct provider adapter.
return;

defined( 'ABSPATH' ) || exit;

abstract class OpenAIErrorMapperTest extends TestCase {

	/**
	 * @dataProvider provideErrors
	 *
	 * @param int      $status      HTTP status.
	 * @param string   $code        Provider code.
	 * @param string   $type        Provider type.
	 * @param string   $expected    Stable plugin code.
	 * @param bool     $retryable   Expected retry flag.
	 * @param int|null $retry_after Retry delay.
	 */
	public function test_maps_stable_errors(
		int $status,
		string $code,
		string $type,
		string $expected,
		bool $retryable,
		?int $retry_after
	): void {
		$body  = (string) json_encode(
			array(
				'error' => array(
					'code'    => $code,
					'type'    => $type,
					'message' => 'secret prompt and image details must not escape',
				),
			)
		);
		$error = ( new OpenAIErrorMapper() )->from_http_response( $status, $body, $retry_after );

		self::assertSame( $expected, $error->code() );
		self::assertSame( $retryable, $error->is_retryable() );
		self::assertSame( $retry_after, $error->retry_after_seconds() );
		self::assertSame( $status, $error->http_status() );
		self::assertStringNotContainsString( 'secret', $error->message() );
	}

	/**
	 * @return array<string,array{int,string,string,string,bool,int|null}>
	 */
	public function provideErrors(): array {
		return array(
			'image user error' => array( 400, 'image_generation_user_error', 'image_generation_user_error', 'openai_image_user_error', false, null ),
			'moderation'       => array( 400, 'content_policy_violation', 'invalid_request_error', 'openai_moderation_blocked', false, null ),
			'invalid request'  => array( 422, 'invalid_value', 'invalid_request_error', 'openai_invalid_request', false, null ),
			'authentication'   => array( 401, 'invalid_api_key', 'authentication_error', 'openai_authentication_failed', false, null ),
			'access denied'    => array( 403, 'access_denied', 'permission_error', 'openai_access_denied', false, null ),
			'credit limit'     => array( 429, 'insufficient_quota', 'rate_limit_error', 'openai_quota_exhausted', false, null ),
			'rate limit'       => array( 429, 'rate_limit_exceeded', 'rate_limit_error', 'openai_rate_limited', true, 12 ),
			'timeout status'   => array( 408, '', '', 'openai_timeout', true, 3 ),
			'server error'     => array( 503, 'service_unavailable', 'server_error', 'openai_service_unavailable', true, null ),
			'unexpected'       => array( 404, 'not_found', 'invalid_request_error', 'openai_provider_error', false, null ),
		);
	}

	public function test_malformed_error_body_is_not_reflected(): void {
		$error = ( new OpenAIErrorMapper() )->from_http_response( 400, '{customer-bytes}' );

		self::assertSame( 'openai_invalid_request', $error->code() );
		self::assertStringNotContainsString( 'customer-bytes', $error->message() );
	}
}
