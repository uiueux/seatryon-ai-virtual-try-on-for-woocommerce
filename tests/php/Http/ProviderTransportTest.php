<?php
/**
 * Provider transport tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Http;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Http\MultipartFile;
use SeaTryOn\Http\ProviderTransport;

defined( 'ABSPATH' ) || exit;

final class ProviderTransportTest extends TestCase {

	public function test_json_requests_are_bounded_and_encoded(): void {
		$client    = new RecordingHttpClient( new HttpResponse( 200, array(), '{}' ) );
		$transport = new ProviderTransport( $client );

		$transport->post_json(
			'https://provider.example/generate',
			array( 'Authorization' => 'Bearer secret' ),
			array( 'n' => 1, 'prompt' => 'Safe prompt.' ),
			45,
			2048
		);

		$request = $client->requests[0];
		self::assertSame( 'POST', $request->method() );
		self::assertSame( 'application/json', $request->headers()['Content-Type'] );
		self::assertSame( 45, $request->timeout() );
		self::assertSame( 2048, $request->max_response_bytes() );
		self::assertSame( array( 'n' => 1, 'prompt' => 'Safe prompt.' ), json_decode( $request->body(), true ) );
	}

	public function test_multipart_transport_preserves_ordered_repeated_files(): void {
		$client    = new RecordingHttpClient( new HttpResponse( 200, array(), '{}' ) );
		$transport = new ProviderTransport( $client );

		$transport->post_multipart(
			'https://provider.example/edits',
			array(),
			array( 'n' => 1 ),
			array(
				new MultipartFile( 'image[]', 'subject.png', 'image/png', 'FIRST' ),
				new MultipartFile( 'image[]', 'product.png', 'image/png', 'SECOND' ),
			)
		);

		$body = $client->requests[0]->body();
		self::assertLessThan( strpos( $body, 'SECOND' ), strpos( $body, 'FIRST' ) );
		self::assertSame( 2, substr_count( $body, 'name="image[]"' ) );
	}
}
