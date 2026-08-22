<?php
/**
 * Trusted WooCommerce product context resolver.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

interface ProductContextResolverInterface {
	/** Resolve product configuration and stage its trusted image into a private scope. */
	public function resolve( int $product_id, ?int $variation_id, string $scope_id ): ProductContext;
}
