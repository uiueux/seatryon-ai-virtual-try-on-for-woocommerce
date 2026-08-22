<?php
/**
 * Server-side image normalization contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

interface ImageProcessorInterface {
	/** Decode, orient, resize and re-encode a local JPEG, PNG or WebP. */
	public function normalize( string $path, string $original_name, int $maximum_bytes ): NormalizedImage;
}
