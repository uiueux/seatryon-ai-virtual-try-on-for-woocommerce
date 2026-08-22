<?php
/**
 * Validated image value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

defined( 'ABSPATH' ) || exit;

/**
 * Carries bytes and metadata established from the bytes themselves.
 */
final class ValidatedImage {

	/**
	 * Validated image bytes.
	 *
	 * @var string
	 */
	private $bytes;

	/**
	 * Detected MIME type.
	 *
	 * @var string
	 */
	private $mime;

	/**
	 * Width in pixels.
	 *
	 * @var int
	 */
	private $width;

	/**
	 * Height in pixels.
	 *
	 * @var int
	 */
	private $height;

	/**
	 * Safe extension.
	 *
	 * @var string
	 */
	private $extension;

	/**
	 * Set up validated image data.
	 *
	 * @param string $bytes     Image bytes.
	 * @param string $mime      Detected MIME type.
	 * @param int    $width     Width in pixels.
	 * @param int    $height    Height in pixels.
	 * @param string $extension Safe extension.
	 */
	public function __construct( string $bytes, string $mime, int $width, int $height, string $extension ) {
		$this->bytes     = $bytes;
		$this->mime      = $mime;
		$this->width     = $width;
		$this->height    = $height;
		$this->extension = $extension;
	}

	/** Return the validated bytes. */
	public function bytes(): string {
		return $this->bytes;
	}

	/** Return the detected MIME type. */
	public function mime(): string {
		return $this->mime;
	}

	/** Return the width in pixels. */
	public function width(): int {
		return $this->width;
	}

	/** Return the height in pixels. */
	public function height(): int {
		return $this->height;
	}

	/** Return the safe extension. */
	public function extension(): string {
		return $this->extension;
	}
}
