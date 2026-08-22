<?php
/**
 * Product image checker contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Product;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a product has a usable parent-product main image.
 */
interface ProductImageCheckerInterface {

	/**
	 * Determine whether the main image is a readable local image attachment.
	 *
	 * @param WC_Product $product Parent product being configured.
	 */
	public function has_readable_main_image( WC_Product $product ): bool;
}
