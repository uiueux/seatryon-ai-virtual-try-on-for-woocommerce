<?php
/**
 * WordPress product image reader.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the parent product image without exposing its path to the browser.
 */
final class WordPressProductImageReader implements ProductImageReaderInterface {

	/**
	 * Determine whether the product's main attachment is readable.
	 *
	 * @param WC_Product $product Product to inspect.
	 */
	public function has_readable_image( WC_Product $product ): bool {
		$image_id = (int) $product->get_image_id();

		if ( $image_id < 1 ) {
			return false;
		}

		$path = get_attached_file( $image_id, true );

		return is_string( $path ) && '' !== $path && is_readable( $path );
	}
}
