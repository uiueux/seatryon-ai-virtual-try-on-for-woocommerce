<?php
/**
 * WordPress-backed product image checker.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Product;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Validates a product's main image through public WooCommerce/WordPress APIs.
 */
final class WordPressProductImageChecker implements ProductImageCheckerInterface {

	/**
	 * Determine whether the main image is a readable local image attachment.
	 *
	 * @param WC_Product $product Parent product being configured.
	 */
	public function has_readable_main_image( WC_Product $product ): bool {
		$image_id = absint( $product->get_image_id( 'edit' ) );

		if ( 0 === $image_id || ! wp_attachment_is_image( $image_id ) ) {
			return false;
		}

		$file_path = get_attached_file( $image_id );

		return is_string( $file_path ) && '' !== $file_path && is_file( $file_path ) && is_readable( $file_path );
	}
}
