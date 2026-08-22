<?php
/**
 * Frontend runtime configuration contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the non-secret, product-bound REST bootstrap consumed by the UI.
 */
interface FrontendConfigProviderInterface {

	/**
	 * Build the runtime configuration for one product page.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public function for_product( int $product_id ): array;
}
