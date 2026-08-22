<?php
/**
 * WordPress image validation and normalization.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Uses WordPress file, image editor and EXIF-orientation APIs and fails closed. */
final class WordPressImageProcessor implements ImageProcessorInterface {

	private const MAX_DIMENSION        = 4096;
	private const MAX_SOURCE_DIMENSION = 8192;
	private const MAX_SOURCE_PIXELS    = 16777216;

	/** @var callable */
	private $file_api_path;

	/**
	 * Set up the image processor.
	 *
	 * @param callable|null $file_api_path Resolves the trusted WordPress file API path.
	 */
	public function __construct( ?callable $file_api_path = null ) {
		$this->file_api_path = $file_api_path ?? static function (): string {
			return defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/file.php' : '';
		};
	}

	/** {@inheritDoc} */
	public function normalize( string $path, string $original_name, int $maximum_bytes ): NormalizedImage {
		if ( ! is_file( $path ) || ! is_readable( $path ) || $maximum_bytes < 1 ) {
			$this->invalid();
		}

		$source_bytes = filesize( $path );
		if ( false === $source_bytes || $source_bytes < 1 || $source_bytes > $maximum_bytes ) {
			throw new UploadException( 'file_too_large', __( 'This image is too large. Please choose a smaller file.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 413 );
		}

		$mimes   = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		);
		$checked = wp_check_filetype_and_ext( $path, sanitize_file_name( $original_name ), $mimes );
		$mime    = wp_get_image_mime( $path );
		if ( ! is_array( $checked ) || empty( $checked['ext'] ) || empty( $checked['type'] ) || ! is_string( $mime ) || $mime !== $checked['type'] ) {
			$this->invalid();
		}

		$extension = $this->extension_for_mime( $mime );
		$size      = wp_getimagesize( $path );
		if ( null === $extension || ! is_array( $size ) || ! isset( $size[0], $size[1] ) || (int) $size[0] < 1 || (int) $size[1] < 1 ) {
			$this->invalid();
		}
		$source_width  = (int) $size[0];
		$source_height = (int) $size[1];
		if (
			$source_width > self::MAX_SOURCE_DIMENSION ||
			$source_height > self::MAX_SOURCE_DIMENSION ||
			$source_width > intdiv( self::MAX_SOURCE_PIXELS, $source_height )
		) {
			throw new UploadException( 'file_too_large', __( 'This image is too large. Please choose a smaller file.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 413 );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			$this->invalid();
		}

		$rotated = $editor->maybe_exif_rotate();
		if ( is_wp_error( $rotated ) ) {
			$this->invalid();
		}

		$current_size = $editor->get_size();
		if ( ! is_array( $current_size ) || empty( $current_size['width'] ) || empty( $current_size['height'] ) ) {
			$this->invalid();
		}
		if ( (int) $current_size['width'] > self::MAX_DIMENSION || (int) $current_size['height'] > self::MAX_DIMENSION ) {
			$resized = $editor->resize( self::MAX_DIMENSION, self::MAX_DIMENSION, false );
			if ( is_wp_error( $resized ) ) {
				$this->invalid();
			}
		}

		$temp = $this->temporary_path( $extension );
		if ( ! is_string( $temp ) || '' === $temp ) {
			throw new UploadException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, 'image_temporary_file_unavailable' );
		}

		$saved_path = $temp;
		try {
			$saved = $editor->save( $temp, $mime );
			if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
				$this->invalid();
			}
			$saved_path = $saved['path'];
			$file_size  = filesize( $saved_path );
			if ( false === $file_size || $file_size > $maximum_bytes ) {
				throw new UploadException( 'file_too_large', __( 'This image is too large. Please choose a smaller file.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 413 );
			}
			$bytes = file_get_contents( $saved_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local, editor-created private temp file.
			$final = wp_getimagesize( $saved_path );
			if ( false === $bytes || '' === $bytes || ! is_array( $final ) || ! isset( $final[0], $final[1] ) ) {
				$this->invalid();
			}

			return new NormalizedImage( $bytes, $mime, $extension, (int) $final[0], (int) $final[1] );
		} finally {
			if ( is_file( $saved_path ) ) {
				wp_delete_file( $saved_path );
			}
			if ( $saved_path !== $temp && is_file( $temp ) ) {
				wp_delete_file( $temp );
			}
		}
	}

	/** Convert a validated MIME to a fixed extension. */
	private function extension_for_mime( string $mime ): ?string {
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);

		return isset( $extensions[ $mime ] ) ? $extensions[ $mime ] : null;
	}

	/**
	 * Load the WordPress file API on public REST requests and create a temporary path.
	 *
	 * @return string|false
	 */
	private function temporary_path( string $extension ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			$file_api = (string) call_user_func( $this->file_api_path );
			if ( '' === $file_api || ! is_readable( $file_api ) ) {
				return false;
			}

			require_once $file_api;
		}

		return function_exists( 'wp_tempnam' ) ? \wp_tempnam( 'sea-tryon-normalized.' . $extension ) : false;
	}

	/** Throw the stable invalid-image response. */
	private function invalid(): void {
		throw new UploadException( 'invalid_upload', __( 'Please upload a valid JPEG, PNG, or WebP image.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
	}
}
