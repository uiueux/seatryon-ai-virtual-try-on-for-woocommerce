<?php
/**
 * Redactor tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Security;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SeaTryOn\Security\Redactor;

defined( 'ABSPATH' ) || exit;

final class RedactorTest extends TestCase {

	public function test_redacts_known_secret_bearer_json_and_url_query_values(): void {
		$redactor = new Redactor( array( 'known-private-value' ) );
		$message  = 'known-private-value Bearer abc.def https://x.test/image?api_key=query-secret&size=1 '
			. '{"token":"json-secret"}';
		$result   = $redactor->redact( $message );

		self::assertStringNotContainsString( 'known-private-value', $result );
		self::assertStringNotContainsString( 'abc.def', $result );
		self::assertStringNotContainsString( 'query-secret', $result );
		self::assertStringNotContainsString( 'json-secret', $result );
		self::assertStringContainsString( 'size=1', $result );
	}

	public function test_nested_context_and_throwables_are_safely_redacted(): void {
		$redactor = new Redactor( array( 'provider-secret' ) );
		$context  = array(
			'request' => array(
				'headers' => array( 'Authorization' => 'Bearer provider-secret' ),
				'url'     => 'https://x.test/run?token=url-secret&mode=low',
				'nested'  => array( 'b64_json' => 'very-large-image' ),
			),
			'error'   => new RuntimeException( 'failed with provider-secret' ),
			'ip'      => '192.168.10.42',
		);
		$result   = $redactor->redact_context( $context );
		$encoded  = json_encode( $result );

		self::assertIsString( $encoded );
		self::assertStringNotContainsString( 'provider-secret', $encoded );
		self::assertStringNotContainsString( 'url-secret', $encoded );
		self::assertStringNotContainsString( 'very-large-image', $encoded );
		self::assertStringNotContainsString( '192.168.10.42', $encoded );
		self::assertStringContainsString( 'mode=low', $encoded );
	}

	public function test_data_image_payload_is_removed(): void {
		$redactor = new Redactor();
		$result   = $redactor->redact( 'image=data:image/png;base64,iVBORw0KGgoAAA==' );

		self::assertStringNotContainsString( 'iVBORw0KGgoAAA', $result );
	}

	public function test_cookie_session_signature_and_result_urls_are_redacted_by_key(): void {
		$redactor = new Redactor();
		$result   = $redactor->redact_context(
			array(
				'cookie'       => 'guest-session=private-cookie',
				'session_id'   => 'private-session',
				'signature'    => 'private-signature',
				'result_url'   => 'https://files.example/result.png?token=private',
				'download_url' => 'https://files.example/download.png',
			)
		);

		self::assertSame( '[REDACTED]', $result['cookie'] );
		self::assertSame( '[REDACTED]', $result['session_id'] );
		self::assertSame( '[REDACTED]', $result['signature'] );
		self::assertSame( '[REDACTED]', $result['result_url'] );
		self::assertSame( '[REDACTED]', $result['download_url'] );
	}

	public function test_valid_ipv6_addresses_are_redacted_without_changing_other_colon_text(): void {
		$redactor = new Redactor();
		$result   = $redactor->redact( 'clients=2001:db8:85a3::8a2e:370:7334 and ::1; time=12:30' );

		self::assertStringNotContainsString( '2001:db8:85a3::8a2e:370:7334', $result );
		self::assertStringNotContainsString( '::1', $result );
		self::assertStringContainsString( 'time=12:30', $result );
	}
}
