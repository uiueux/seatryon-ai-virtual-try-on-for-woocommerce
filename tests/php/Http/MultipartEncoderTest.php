<?php
/**
 * Multipart encoder tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Http\MultipartEncoder;
use SeaTryOn\Http\MultipartFile;

defined( 'ABSPATH' ) || exit;

final class MultipartEncoderTest extends TestCase {

	public function test_repeated_image_parts_keep_their_order_and_boundaries_are_unpredictable(): void {
		$encoder = new MultipartEncoder();
		$files   = array(
			new MultipartFile( 'image[]', 'subject.png', 'image/png', 'SUBJECT_BYTES' ),
			new MultipartFile( 'image[]', 'product.jpg', 'image/jpeg', 'PRODUCT_BYTES' ),
		);

		$first  = $encoder->encode( array( 'model' => 'gpt-image-2' ), $files );
		$second = $encoder->encode( array( 'model' => 'gpt-image-2' ), $files );

		self::assertNotSame( $first->boundary(), $second->boundary() );
		self::assertMatchesRegularExpression( '/^sea_tryon_[a-f0-9]{48}$/', $first->boundary() );
		self::assertLessThan( strpos( $first->body(), 'PRODUCT_BYTES' ), strpos( $first->body(), 'SUBJECT_BYTES' ) );
		self::assertSame( 2, substr_count( $first->body(), 'name="image[]"' ) );
		self::assertStringEndsWith( '--' . $first->boundary() . "--\r\n", $first->body() );
	}

	/** @dataProvider provideHeaderInjection */
	public function test_file_metadata_rejects_header_injection( string $field, string $filename, string $mime ): void {
		$this->expectException( InvalidArgumentException::class );
		new MultipartFile( $field, $filename, $mime, 'bytes' );
	}

	/** @return array<string,array{string,string,string}> */
	public function provideHeaderInjection(): array {
		return array(
			'field newline'    => array( "image[]\r\nX-Evil", 'safe.png', 'image/png' ),
			'filename newline' => array( 'image[]', "safe.png\r\nX-Evil: yes", 'image/png' ),
			'filename quote'   => array( 'image[]', 'safe".png', 'image/png' ),
			'mime newline'     => array( 'image[]', 'safe.png', "image/png\r\nX-Evil: yes" ),
		);
	}
}
