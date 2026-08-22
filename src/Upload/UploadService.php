<?php
/**
 * Customer upload pipeline.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Validates PHP upload metadata and writes only to private temporary storage. */
final class UploadService {

	public const MAX_BYTES = 10485760;

	/** @var ImageProcessorInterface */ private $images;
	/** @var TemporaryStorageInterface */ private $storage;
	/** @var callable */ private $upload_limit;

	/** @param ImageProcessorInterface $images Image processor. @param TemporaryStorageInterface $storage Private storage. @param callable|null $upload_limit Effective WordPress limit. */
	public function __construct( ImageProcessorInterface $images, TemporaryStorageInterface $storage, ?callable $upload_limit = null ) {
		$this->images       = $images;
		$this->storage      = $storage;
		$this->upload_limit = $upload_limit ?? 'wp_max_upload_size';
	}

	/** @param array<string,mixed> $file One explicit WP_REST_Request file parameter. */
	public function store_customer( array $file ): StoredUpload {
		if ( ! isset( $file['error'], $file['size'], $file['tmp_name'], $file['name'] ) || UPLOAD_ERR_OK !== $file['error'] || ( ! is_int( $file['size'] ) && ! ctype_digit( (string) $file['size'] ) ) ) {
			$this->invalid();
		}
		$size = (int) $file['size'];
		$max  = min( self::MAX_BYTES, max( 0, (int) call_user_func( $this->upload_limit ) ) );
		if ( $max < 1 ) {
			throw new UploadException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, 'server_upload_limit_unavailable' );
		}
		if ( $size < 1 || $size > $max ) {
			throw new UploadException( 'file_too_large', __( 'This image is too large. Please choose a smaller file.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 413 );
		}
		if ( ! is_string( $file['tmp_name'] ) || ! is_string( $file['name'] ) || '' === $file['tmp_name'] || '' === $file['name'] ) {
			$this->invalid();
		}

		$image = $this->images->normalize( $file['tmp_name'], $file['name'], $max );
		$scope = $this->storage->create_scope();
		try {
			$reference = $this->storage->write( $scope, 'customer', $image->bytes(), $image->extension() );
		} catch ( \Throwable $exception ) {
			$this->storage->delete_scope( $scope );
			throw $exception;
		}

		return new StoredUpload( $scope, $reference, $image->mime_type() );
	}

	private function invalid(): void {
		throw new UploadException( 'invalid_upload', __( 'Please upload a valid JPEG, PNG, or WebP image.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
	}
}
