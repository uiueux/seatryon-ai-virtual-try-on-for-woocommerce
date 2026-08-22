<?php
/**
 * Dynamic Virtual Try-On block render template.
 *
 * @package SeaTryOn
 *
 * @var array<string,mixed> $attributes Block attributes.
 * @var string              $content    Saved content (unused for dynamic block).
 * @var WP_Block            $block      Block instance and context.
 */

use SeaTryOn\Frontend\RenderContext;
use SeaTryOn\Frontend\TriggerRenderer;

defined( 'ABSPATH' ) || exit;

$sea_tryon_product_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : 0;
$sea_tryon_product    = $sea_tryon_product_id > 0 ? wc_get_product( $sea_tryon_product_id ) : false;

if ( ! $sea_tryon_product instanceof WC_Product || ! is_product() ) {
	return '';
}

$sea_tryon_trigger = TriggerRenderer::instance()->render( $sea_tryon_product, RenderContext::block() );

if ( '' === $sea_tryon_trigger ) {
	return '';
}

$sea_tryon_wrapper = get_block_wrapper_attributes( array( 'class' => 'sea-tryon-block' ) );

return sprintf(
	'<div %1$s>%2$s</div>',
	$sea_tryon_wrapper,
	$sea_tryon_trigger
);
