<?php
/**
 * WordPress AI Client adapter tests.
 *
 * @package SeaTryOn\Tests
 */

namespace WordPress\AiClient\Files\Enums {
	if ( ! class_exists( FileTypeEnum::class, false ) ) {
		final class FileTypeEnum {
			public static function inline(): self { return new self(); }
		}
	}
}

namespace WordPress\AiClient\Files\DTO {
	if ( ! class_exists( File::class, false ) ) {
		final class File {
			/** @var string */ private $base64;
			/** @var string */ private $mime;
			public function __construct( string $base64, ?string $mime = null ) { $this->base64 = $base64; $this->mime = (string) $mime; }
			public function isInline(): bool { return true; }
			public function isImage(): bool { return 0 === strpos( $this->mime, 'image/' ); }
			public function getBase64Data(): ?string { return $this->base64; }
			public function getMimeType(): string { return $this->mime; }
		}
	}
}

namespace {
	if ( ! class_exists( 'WP_Error', false ) ) {
		final class WP_Error {
			/** @var string */ private $code;
			/** @var mixed */ private $data;
			/** @var string */ private $message;
			/** @param mixed $data Error data. */
			public function __construct( string $code, string $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
			public function get_error_code(): string { return $this->code; }
			public function get_error_message(): string { return $this->message; }
			/** @return mixed */ public function get_error_data() { return $this->data; }
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $value ): bool { return $value instanceof WP_Error || ( is_object( $value ) && method_exists( $value, 'get_error_code' ) && method_exists( $value, 'get_error_message' ) ); }
	}

	if ( ! function_exists( 'wp_supports_ai' ) ) {
		function wp_supports_ai(): bool { return true; }
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		function wp_ai_client_prompt() { return $GLOBALS['sea_tryon_wp_ai_builder']; }
	}
}

namespace SeaTryOn\Tests\Provider\WordPressAI {
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Domain\ProviderException;
	use SeaTryOn\Image\ValidatedImage;
	use SeaTryOn\Provider\WordPressAI\WordPressAIClient;
	use WordPress\AiClient\Files\DTO\File;

	defined( 'ABSPATH' ) || exit;

	final class WordPressAIClientTest extends TestCase {
		public function test_builds_an_ordered_inline_two_image_prompt(): void {
			$builder = new RecordingPromptBuilder( true, new File( base64_encode( 'generated' ), 'image/png' ) );
			$client  = new WordPressAIClient( static function () use ( $builder ): RecordingPromptBuilder { return $builder; } );

			$result = $client->generate_image(
				'Keep both subjects accurate.',
				array(
					new ValidatedImage( 'customer', 'image/png', 1, 1, 'png' ),
					new ValidatedImage( 'product', 'image/jpeg', 1, 1, 'jpg' ),
				)
			);

			self::assertSame( 'Keep both subjects accurate.', $builder->text );
			self::assertCount( 2, $builder->files );
			self::assertSame( base64_encode( 'customer' ), $builder->files[0]->getBase64Data() );
			self::assertSame( base64_encode( 'product' ), $builder->files[1]->getBase64Data() );
			self::assertTrue( $builder->inline_output );
			self::assertSame( 'generated', $result->bytes() );
		}

		public function test_maps_connector_rate_limits_to_retryable_provider_error(): void {
			$builder = new RecordingPromptBuilder( true, new \WP_Error( 'prompt_client_error', '', array( 'status' => 429 ) ) );
			$client  = new WordPressAIClient( static function () use ( $builder ): RecordingPromptBuilder { return $builder; } );

			try {
				$client->generate_image(
					'Edit.',
					array(
						new ValidatedImage( 'a', 'image/png', 1, 1, 'png' ),
						new ValidatedImage( 'b', 'image/png', 1, 1, 'png' ),
					)
				);
				self::fail( 'Expected a provider exception.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( 'provider_unavailable', $exception->provider_error()->code() );
				self::assertTrue( $exception->provider_error()->is_retryable() );
			}
		}
	}

	final class RecordingPromptBuilder {
		/** @var bool */ private $supported;
		/** @var mixed */ private $result;
		/** @var string */ public $text = '';
		/** @var File[] */ public $files = array();
		/** @var bool */ public $inline_output = false;
		/** @param mixed $result Generation result. */
		public function __construct( bool $supported, $result ) { $this->supported = $supported; $this->result = $result; }
		public function with_text( string $text ): self { $this->text = $text; return $this; }
		public function with_file( File $file ): self { $this->files[] = $file; return $this; }
		public function as_output_file_type( $type ): self { unset( $type ); $this->inline_output = true; return $this; }
		public function is_supported_for_image_generation(): bool { return $this->supported; }
		/** @return mixed */ public function generate_image() { return $this->result; }
	}
}
