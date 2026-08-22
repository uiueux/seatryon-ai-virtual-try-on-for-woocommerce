<?php
/**
 * Decoded and normalized image bytes.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop

final class NormalizedImage {
	/** @var string */ private $bytes;
	/** @var string */ private $mime_type;
	/** @var string */ private $extension;
	/** @var int */ private $width;
	/** @var int */ private $height;

	/** @param string $bytes Image bytes. @param string $mime_type MIME. @param string $extension Extension. @param int $width Width. @param int $height Height. */
	public function __construct( string $bytes, string $mime_type, string $extension, int $width, int $height ) {
		if ( '' === $bytes || ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) || ! in_array( $extension, array( 'jpg', 'png', 'webp' ), true ) || $width < 1 || $height < 1 ) {
			throw new InvalidArgumentException( 'Normalized image metadata is invalid.' );
		}
		$this->bytes     = $bytes;
		$this->mime_type = $mime_type;
		$this->extension = $extension;
		$this->width     = $width;
		$this->height    = $height;
	}

	public function bytes(): string {
		return $this->bytes; }
	public function mime_type(): string {
		return $this->mime_type; }
	public function extension(): string {
		return $this->extension; }
	public function width(): int {
		return $this->width; }
	public function height(): int {
		return $this->height; }
}
