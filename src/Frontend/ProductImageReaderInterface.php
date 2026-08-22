<?php
/**
 * Product image readability contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a testable boundary around WordPress attachment storage.
 */
interface ProductImageReaderInterface {

	/**
	 * Whether the product has a locally readable main image.
	 *
	 * @param WC_Product $product Product to inspect.
	 */
	public function has_readable_image( WC_Product $product ): bool;
}
