<?php
/**
 * Image validator tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Image;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Image\ImageValidationException;
use SeaTryOn\Image\ImageValidator;

defined( 'ABSPATH' ) || exit;

final class ImageValidatorTest extends TestCase {

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlFH4QAAAAASUVORK5CYII=';

	public function test_detects_png_from_bytes_and_returns_metadata(): void {
		$image = ( new ImageValidator( 1024, 4096, 16777216, false ) )->validate( $this->png(), 'image/png' );

		self::assertSame( 'image/png', $image->mime() );
		self::assertSame( 'png', $image->extension() );
		self::assertSame( 1, $image->width() );
		self::assertSame( 1, $image->height() );
		self::assertSame( $this->png(), $image->bytes() );
	}

	public function test_rejects_mime_mismatch_invalid_bytes_and_size_overflow(): void {
		$validator = new ImageValidator( 1024, 4096, 16777216, false );

		foreach ( array( 'mismatch', 'invalid', 'oversized' ) as $case ) {
			try {
				if ( 'mismatch' === $case ) {
					$validator->validate( $this->png(), 'image/jpeg' );
				} elseif ( 'invalid' === $case ) {
					$validator->validate( 'not an image' );
				} else {
					( new ImageValidator( 4, 4096, 16777216, false ) )->validate( $this->png() );
				}
				self::fail( 'Expected image validation to fail.' );
			} catch ( ImageValidationException $exception ) {
				self::assertContains( $exception->reason(), array( 'image_mime_mismatch', 'image_not_decodable', 'image_too_large' ) );
			}
		}
	}

	private function png(): string {
		$bytes = base64_decode( self::PNG, true );
		self::assertIsString( $bytes );

		return $bytes;
	}
}
