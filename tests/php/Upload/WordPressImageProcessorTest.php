<?php
/**
 * Decode-budget image processor tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Upload {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) { function __( string $text, string $domain ): string { unset( $domain ); return $text; } }
	function sanitize_file_name( string $name ): string { return $name; }
	/** @return array<string,string> */ function wp_check_filetype_and_ext( string $path, string $name, array $mimes ): array { unset( $path, $name, $mimes ); return array( 'ext' => 'png', 'type' => 'image/png' ); }
	function wp_get_image_mime( string $path ): string { unset( $path ); return 'image/png'; }
	/** @return array<int,int> */ function wp_getimagesize( string $path ): array { unset( $path ); return $GLOBALS['sea_tryon_test_dimensions']; }
	/** @return mixed */ function wp_get_image_editor( string $path ) { unset( $path ); ++$GLOBALS['sea_tryon_editor_calls']; throw new \RuntimeException( 'Editor must not be reached.' ); }
}

namespace SeaTryOn\Tests\Upload {
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Upload\UploadException;
	use SeaTryOn\Upload\WordPressImageProcessor;

	final class WordPressImageProcessorTest extends TestCase {
		/**
		 * Public REST requests must lazily load the WordPress file API.
		 *
		 * @runInSeparateProcess
		 * @preserveGlobalState disabled
		 */
		public function test_loads_file_api_before_creating_temporary_path(): void {
			$this->assertFalse( function_exists( 'wp_tempnam' ) );
			$fixture = tempnam( sys_get_temp_dir(), 'sea-tryon-file-api-' );
			$this->assertIsString( $fixture );
			file_put_contents(
				$fixture,
				"<?php\nfunction wp_tempnam( \$filename ) { return 'loaded-' . \$filename; }\n"
			);

			try {
				$processor = new WordPressImageProcessor(
					static function () use ( $fixture ): string {
						return $fixture;
					}
				);
				$method = new \ReflectionMethod( $processor, 'temporary_path' );
				$method->setAccessible( true );

				$this->assertSame( 'loaded-sea-tryon-normalized.png', $method->invoke( $processor, 'png' ) );
				$this->assertTrue( function_exists( 'wp_tempnam' ) );
			} finally {
				unlink( $fixture );
			}
		}

		/** @dataProvider unsafe_dimensions_provider */
		public function test_unsafe_dimensions_are_rejected_before_decode( int $width, int $height ): void {
			$GLOBALS['sea_tryon_test_dimensions'] = array( $width, $height );
			$GLOBALS['sea_tryon_editor_calls']    = 0;
			try {
				( new WordPressImageProcessor() )->normalize( __FILE__, 'image.png', 10485760 );
				$this->fail( 'Expected a decode-budget rejection.' );
			} catch ( UploadException $exception ) {
				$this->assertSame( 'file_too_large', $exception->error_code() );
				$this->assertSame( 0, $GLOBALS['sea_tryon_editor_calls'] );
			}
		}

		/** @return array<string,array{int,int}> */
		public function unsafe_dimensions_provider(): array {
			return array(
				'source dimension cap' => array( 20000, 100 ),
				'pixel bomb'           => array( 5000, 5000 ),
			);
		}
	}
}
