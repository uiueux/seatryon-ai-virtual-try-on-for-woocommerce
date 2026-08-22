<?php
/**
 * Remote image downloader tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Image;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Image\ImageValidationException;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Image\UrlSafetyPolicy;
use SeaTryOn\Tests\Http\RecordingHttpClient;

defined( 'ABSPATH' ) || exit;

final class RemoteImageDownloaderTest extends TestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlFH4QAAAAASUVORK5CYII=';

	public function test_returns_validated_bytes_not_a_public_url(): void {
		$bytes      = (string) base64_decode( self::PNG, true );
		$client     = new RecordingHttpClient( new HttpResponse( 200, array( 'Content-Type' => 'image/png; charset=binary' ), $bytes ) );
		$downloader = new RemoteImageDownloader(
			$client,
			new ImageValidator( 1024, 4096, 16777216, false ),
			$this->public_policy()
		);

		$image = $downloader->download( 'https://cdn.example/result.png', 10, 1024 );

		self::assertSame( $bytes, $image->bytes() );
		self::assertSame( 'image/png', $image->mime() );
		self::assertSame( 2, $client->requests[0]->redirections() );
		self::assertSame( 1024, $client->requests[0]->max_response_bytes() );
	}

	public function test_rejects_non_image_content_type_before_decoding(): void {
		$client     = new RecordingHttpClient( new HttpResponse( 200, array( 'Content-Type' => 'text/html' ), '<html>no</html>' ) );
		$downloader = new RemoteImageDownloader( $client, new ImageValidator( 1024, 4096, 16777216, false ), $this->public_policy() );

		$this->expectException( ImageValidationException::class );
		$downloader->download( 'https://cdn.example/result.png' );
	}

	private function public_policy(): UrlSafetyPolicy {
		return new UrlSafetyPolicy(
			false,
			static function (): array {
				return array( '93.184.216.34' );
			}
		);
	}
}
