<?php
/**
 * Image byte validation.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Validates size, actual MIME, dimensions and decoder acceptance.
 */
final class ImageValidator {

	public const DEFAULT_MAX_BYTES     = 10485760;
	public const DEFAULT_MAX_DIMENSION = 4096;
	public const DEFAULT_MAX_PIXELS    = 16777216;

	/**
	 * Maximum encoded bytes.
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Maximum width or height.
	 *
	 * @var int
	 */
	private $max_dimension;

	/**
	 * Maximum decoded pixels.
	 *
	 * @var int
	 */
	private $max_pixels;

	/**
	 * Whether GD must fully decode the image.
	 *
	 * @var bool
	 */
	private $require_full_decode;

	/**
	 * Set up bounded image validation.
	 *
	 * @param int  $max_bytes          Maximum encoded bytes.
	 * @param int  $max_dimension      Maximum width or height.
	 * @param int  $max_pixels         Maximum decoded pixels.
	 * @param bool $require_full_decode Whether GD must fully decode the image.
	 * @throws InvalidArgumentException When limits are invalid.
	 */
	public function __construct(
		int $max_bytes = self::DEFAULT_MAX_BYTES,
		int $max_dimension = self::DEFAULT_MAX_DIMENSION,
		int $max_pixels = self::DEFAULT_MAX_PIXELS,
		bool $require_full_decode = true
	) {
		if ( $max_bytes < 1 || $max_bytes > 52428800 || $max_dimension < 1 || $max_pixels < 1 ) {
			throw new InvalidArgumentException( 'Image validator limits are invalid.' );
		}

		$this->max_bytes           = $max_bytes;
		$this->max_dimension       = $max_dimension;
		$this->max_pixels          = $max_pixels;
		$this->require_full_decode = $require_full_decode;
	}

	/**
	 * Validate bytes independently of an extension supplied by a browser/provider.
	 *
	 * @param string $bytes         Image bytes.
	 * @param string $expected_mime Optional required MIME type.
	 * @throws ImageValidationException When bytes are unsafe or invalid.
	 */
	public function validate( string $bytes, string $expected_mime = '' ): ValidatedImage {
		$length = strlen( $bytes );
		if ( 0 === $length ) {
			throw new ImageValidationException( 'image_empty', 'The image is empty.' );
		}

		if ( $length > $this->max_bytes ) {
			throw new ImageValidationException( 'image_too_large', 'The image exceeds the allowed file size.' );
		}

		if ( ! function_exists( 'getimagesizefromstring' ) ) {
			throw new ImageValidationException( 'image_decoder_unavailable', 'Image inspection is unavailable on this server.' );
		}

		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input is an expected validation outcome.
		if ( false === $info ) {
			throw new ImageValidationException( 'image_not_decodable', 'The image could not be decoded.' );
		}

		$types = array(
			IMAGETYPE_JPEG => array( 'image/jpeg', 'jpg' ),
			IMAGETYPE_PNG  => array( 'image/png', 'png' ),
			IMAGETYPE_WEBP => array( 'image/webp', 'webp' ),
		);
		$type  = (int) $info[2];
		if ( ! isset( $types[ $type ] ) ) {
			throw new ImageValidationException( 'image_type_not_allowed', 'Only JPEG, PNG and WebP images are allowed.' );
		}

		$mime   = $types[ $type ][0];
		$width  = (int) $info[0];
		$height = (int) $info[1];
		if ( '' !== $expected_mime && strtolower( trim( $expected_mime ) ) !== $mime ) {
			throw new ImageValidationException( 'image_mime_mismatch', 'The image MIME type does not match the expected type.' );
		}

		if (
			$width < 1
			|| $height < 1
			|| $width > $this->max_dimension
			|| $height > $this->max_dimension
			|| $width > (int) floor( $this->max_pixels / $height )
		) {
			throw new ImageValidationException( 'image_dimensions_invalid', 'The image dimensions are outside the allowed processing limits.' );
		}

		if ( $this->require_full_decode ) {
			if ( ! function_exists( 'imagecreatefromstring' ) ) {
				throw new ImageValidationException( 'image_decoder_unavailable', 'Full image decoding is unavailable on this server.' );
			}

			$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input is an expected validation outcome.
			if ( false === $image ) {
				throw new ImageValidationException( 'image_not_decodable', 'The image could not be fully decoded.' );
			}

			imagedestroy( $image );
		}

		return new ValidatedImage( $bytes, $mime, $width, $height, $types[ $type ][1] );
	}
}
