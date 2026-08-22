<?php
/**
 * Shared Virtual Try-On trigger renderer.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use WC_Product;
use SeaTryOn\Admin\Product\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the same safe button for the hook and dynamic-block adapters.
 */
final class TriggerRenderer {

	/**
	 * Request-scoped production instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Authoritative visibility rules.
	 *
	 * @var VisibilityRules
	 */
	private $rules;

	/**
	 * Product IDs and their experience type.
	 *
	 * @var array<int,string>
	 */
	private $rendered = array();

	/**
	 * Set up the shared renderer.
	 *
	 * @param VisibilityRules|null $rules Visibility rules.
	 */
	public function __construct( ?VisibilityRules $rules = null ) {
		$this->rules = $rules ?? new VisibilityRules();
	}

	/** Return the request-scoped production renderer. */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Render an eligible product once per request.
	 *
	 * @param WC_Product    $product Product resolved by the adapter.
	 * @param RenderContext $context Adapter context.
	 */
	public function render( WC_Product $product, RenderContext $context ): string {
		$product_id = $product->get_id();

		if ( isset( $this->rendered[ $product_id ] ) ) {
			return '';
		}

		if ( ! $this->rules->allows( $product, is_product(), is_user_logged_in() ) ) {
			return '';
		}

		/**
		 * Filters whether an otherwise eligible trigger should be displayed.
		 *
		 * This filter can hide an eligible trigger but cannot bypass the server
		 * authorization and product checks above.
		 *
		 * @param bool          $visible Whether the trigger remains visible.
		 * @param WC_Product    $product Product being rendered.
		 * @param RenderContext $context Rendering adapter context.
		 */
		$visible = (bool) apply_filters( 'sea_tryon_trigger_visible', true, $product, $context );

		if ( ! $visible ) {
			return '';
		}

		/**
		 * Filters the customer-facing trigger label.
		 *
		 * @param string        $label   Default translated label.
		 * @param WC_Product    $product Product being rendered.
		 * @param RenderContext $context Rendering adapter context.
		 */
		$default_label = __( 'Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		$label         = trim(
			(string) apply_filters(
				'sea_tryon_button_label',
				$default_label,
				$product,
				$context
			)
		);

		if ( '' === $label ) {
			$label = $default_label;
		}

		$classes = array( 'seatryon-ai-virtual-try-on-for-woocommerce', 'sea-tryon-trigger' );

		/**
		 * Filters non-security presentation classes on the trigger wrapper.
		 *
		 * @param string[]      $classes Wrapper classes.
		 * @param WC_Product    $product Product being rendered.
		 * @param RenderContext $context Rendering adapter context.
		 */
		$classes = (array) apply_filters( 'sea_tryon_trigger_classes', $classes, $product, $context );
		$classes = array_values(
			array_filter(
				array_map(
					static function ( $class_name ): string {
						return sanitize_html_class( (string) $class_name );
					},
					$classes
				)
			)
		);

		$experience                    = (string) $product->get_meta( ProductFields::META_EXPERIENCE_TYPE, true );
		$this->rendered[ $product_id ] = $experience;
		$dialog_id                     = 'sea-tryon-dialog-' . $product_id;

		$markup = sprintf(
			'<div class="%1$s" data-sea-tryon-trigger-container data-product-id="%2$d"><button type="button" class="sea-tryon__button" data-sea-tryon-open data-product-id="%2$d" aria-haspopup="dialog" aria-controls="%3$s"><span class="sea-tryon-trigger__icon" aria-hidden="true">✦</span><span class="sea-tryon-trigger__label">%4$s</span></button></div>',
			esc_attr( implode( ' ', $classes ) ),
			$product_id,
			esc_attr( $dialog_id ),
			esc_html( $label )
		);

		/**
		 * Fires after a trigger has been rendered.
		 *
		 * @param WC_Product    $product Product that was rendered.
		 * @param RenderContext $context Rendering adapter context.
		 */
		do_action( 'sea_tryon_after_trigger_rendered', $product, $context );

		return $markup;
	}

	/** Whether at least one trigger was emitted in this request. */
	public function has_rendered_trigger(): bool {
		return array() !== $this->rendered;
	}

	/**
	 * Return rendered product context for the footer mount.
	 *
	 * @return array<int,string>
	 */
	public function rendered_products(): array {
		return $this->rendered;
	}
}
