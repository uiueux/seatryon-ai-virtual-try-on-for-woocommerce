<?php
/**
 * Classic Product Editor field integration tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {

	if ( ! class_exists( 'WC_Product', false ) ) {
		/** Minimal WooCommerce product double. */
		class WC_Product {

			/** @var int */
			private $id;

			/** @var array<string,string> */
			private $metadata;

			/**
			 * @param int                  $id       Product ID.
			 * @param array<string,string> $metadata Initial metadata.
			 */
			public function __construct( int $id, array $metadata = array() ) {
				$this->id       = $id;
				$this->metadata = $metadata;
			}

			/** Return the product ID. */
			public function get_id(): int {
				return $this->id;
			}

			/**
			 * @param string $key    Metadata key.
			 * @param bool   $single Ignored single-value flag.
			 */
			public function get_meta( string $key, bool $single = true ): string {
				unset( $single );
				return isset( $this->metadata[ $key ] ) ? $this->metadata[ $key ] : '';
			}

			/**
			 * @param string $key   Metadata key.
			 * @param string $value Metadata value.
			 */
			public function update_meta_data( string $key, string $value ): void {
				$this->metadata[ $key ] = $value;
			}

			/** Delete one metadata value. */
			public function delete_meta_data( string $key ): void {
				unset( $this->metadata[ $key ] );
			}

			/** @return array<string,string> */
			public function metadata(): array {
				return $this->metadata;
			}
		}
	}

	if ( ! class_exists( 'WC_Admin_Meta_Boxes', false ) ) {
		/** Minimal WooCommerce error collector double. */
		class WC_Admin_Meta_Boxes {

			/** @var string[] */
			public static $errors = array();

			/** @param string $message Error text. */
			public static function add_error( string $message ): void {
				self::$errors[] = $message;
			}
		}
	}

}

namespace SeaTryOn\Admin\Product {

	/** @return mixed */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}

	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}

	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) );
	}

	function current_user_can( string $capability, int $object_id ): bool {
		unset( $capability, $object_id );
		return (bool) $GLOBALS['sea_tryon_test_can_edit'];
	}

	function wp_verify_nonce( string $nonce, string $action ): bool {
		return (bool) $GLOBALS['sea_tryon_test_nonce_valid'] && 'valid-nonce' === $nonce && 'woocommerce_save_data' === $action;
	}

	function sanitize_textarea_field( string $value ): string {
		return trim( strip_tags( str_replace( "\r\n", "\n", $value ) ) );
	}

	/** @param mixed $value Value to normalize. */
	function absint( $value ): int {
		return abs( (int) $value );
	}

	function wp_attachment_is_image( int $attachment_id ): bool {
		return in_array( $attachment_id, $GLOBALS['sea_tryon_test_image_ids'], true );
	}

	/** @param array<string,string> $attributes Image attributes. */
	function wp_get_attachment_image( int $attachment_id, string $size, bool $icon, array $attributes ): string {
		unset( $size, $icon, $attributes );
		return '<img src="image-' . $attachment_id . '.jpg" alt="">';
	}

	function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( string $text, string $domain ): string { unset( $domain ); return esc_attr( $text ); }
	function esc_html__( string $text, string $domain ): string { unset( $domain ); return esc_html( $text ); }

	function __( string $text, string $domain ): string {
		unset( $domain );
		return $text;
	}

	/** @return \WC_Product|false */
	function wc_get_product( int $product_id ) {
		$product = $GLOBALS['sea_tryon_test_product'];
		return $product instanceof \WC_Product && $product->get_id() === $product_id ? $product : false;
	}

	/** @param array<string,mixed> $field Field definition. */
	function woocommerce_wp_checkbox( array $field ): void {
		$GLOBALS['sea_tryon_test_rendered_fields']['checkbox'] = $field;
		$GLOBALS['sea_tryon_test_render_order'][]               = 'checkbox';
	}

	/** @param array<string,mixed> $field Field definition. */
	function woocommerce_wp_textarea_input( array $field ): void {
		$GLOBALS['sea_tryon_test_rendered_fields']['textarea'] = $field;
		$GLOBALS['sea_tryon_test_render_order'][]               = 'textarea';
	}

	/** @param array<string,mixed> $field Field definition. */
	function woocommerce_wp_select( array $field ): void {
		$GLOBALS['sea_tryon_test_rendered_fields']['select'] = $field;
		$GLOBALS['sea_tryon_test_render_order'][]             = 'select';
	}

	/** @param callable $callback Hook callback. */
	function add_action( string $hook, $callback ): void {
		$GLOBALS['sea_tryon_test_hooks'][ $hook ] = $callback;
	}
}

namespace SeaTryOn\Tests\Admin\Product {

	use PHPUnit\Framework\TestCase;
	use WC_Admin_Meta_Boxes;
	use WC_Product;
	use SeaTryOn\Admin\Product\ProductFields;
	use SeaTryOn\Admin\Product\ProductImageCheckerInterface;
	use SeaTryOn\Domain\ExperienceType;

	defined( 'ABSPATH' ) || exit;

	final class ProductFieldsTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['sea_tryon_test_can_edit']        = true;
			$GLOBALS['sea_tryon_test_nonce_valid']     = true;
			$GLOBALS['sea_tryon_test_product']         = null;
			$GLOBALS['sea_tryon_test_rendered_fields'] = array();
			$GLOBALS['sea_tryon_test_render_order']    = array();
			$GLOBALS['sea_tryon_test_hooks']           = array();
			$GLOBALS['sea_tryon_test_image_ids']       = array();
			WC_Admin_Meta_Boxes::$errors               = array();
			$_POST                                      = array();
		}

		/** Public WooCommerce hooks are registered with the expected callbacks. */
		public function test_registers_classic_editor_hooks(): void {
			$fields = new ProductFields();
			$fields->register_hooks();

			self::assertSame( array( $fields, 'render' ), $GLOBALS['sea_tryon_test_hooks']['woocommerce_product_options_advanced'] );
			self::assertSame( array( $fields, 'save' ), $GLOBALS['sea_tryon_test_hooks']['woocommerce_admin_process_product_object'] );
			self::assertSame( array( $fields, 'enqueue_media_selector' ), $GLOBALS['sea_tryon_test_hooks']['admin_enqueue_scripts'] );
		}

		/** Rendering uses stored values and the frozen defaults. */
		public function test_renders_escaped_woocommerce_field_definitions(): void {
			$product                                = new WC_Product( 101 );
			$GLOBALS['sea_tryon_test_product']      = $product;
			$GLOBALS['post']                        = (object) array( 'ID' => 101 );

			ob_start();
			( new ProductFields() )->render();
			$wrapper = ob_get_clean();

			self::assertStringStartsWith( '<div class="options_group">', $wrapper );
			self::assertStringContainsString( 'data-sea-tryon-product-image', $wrapper );
			self::assertStringContainsString( 'name="_sea_tryon_product_image_id" value=""', $wrapper );
			self::assertStringContainsString( 'If empty, the product&#039;s main image is used.', $wrapper );
			self::assertStringEndsWith( '</div>', $wrapper );
			self::assertSame( 'no', $GLOBALS['sea_tryon_test_rendered_fields']['checkbox']['value'] );
			self::assertSame( 'no', $GLOBALS['sea_tryon_test_rendered_fields']['checkbox']['unchecked_value'] );
			self::assertSame( '2000', $GLOBALS['sea_tryon_test_rendered_fields']['textarea']['custom_attributes']['maxlength'] );
			self::assertSame( 'auto', $GLOBALS['sea_tryon_test_rendered_fields']['select']['value'] );
			$options = $GLOBALS['sea_tryon_test_rendered_fields']['select']['options'];
			self::assertCount( 20, $options );
			self::assertSame( 'Earrings', $options[ ExperienceType::EARRINGS ] );
			self::assertSame( 'Hats', $options[ ExperienceType::HATS ] );
			self::assertSame( 'Shoes', $options[ ExperienceType::SHOES ] );
			self::assertSame( 'Rings', $options[ ExperienceType::RINGS ] );
			self::assertSame( 'Necklaces', $options[ ExperienceType::NECKLACES ] );
			self::assertSame( 'Bracelets', $options[ ExperienceType::BRACELETS ] );
			self::assertSame( 'Nose Rings', $options[ ExperienceType::NOSE_RINGS ] );
			self::assertSame( 'Belly Button Rings', $options[ ExperienceType::BELLY_BUTTON_RINGS ] );
			self::assertSame( 'Hair Accessories', $options[ ExperienceType::HAIR_ACCESSORIES ] );
			self::assertSame( 'Anklets', $options[ ExperienceType::ANKLETS ] );
			self::assertSame( 'Brooches & Pins', $options[ ExperienceType::BROOCHES_PINS ] );
			self::assertSame( 'Lip Rings', $options[ ExperienceType::LIP_RINGS ] );
			self::assertSame( 'Tongue Rings', $options[ ExperienceType::TONGUE_RINGS ] );
			self::assertSame( 'Body Chains', $options[ ExperienceType::BODY_CHAINS ] );
			self::assertArrayNotHasKey( ExperienceType::JEWELRY, $options );
			self::assertSame( array( 'checkbox', 'select', 'textarea' ), $GLOBALS['sea_tryon_test_render_order'] );
		}

		/** Rendering restores a valid optional attachment and its thumbnail. */
		public function test_renders_saved_tryon_product_image(): void {
			$GLOBALS['sea_tryon_test_image_ids']  = array( 88 );
			$GLOBALS['sea_tryon_test_product']    = new WC_Product(
				103,
				array( ProductFields::META_PRODUCT_IMAGE_ID => '88' )
			);
			$GLOBALS['post'] = (object) array( 'ID' => 103 );

			ob_start();
			( new ProductFields() )->render();
			$wrapper = ob_get_clean();

			self::assertStringContainsString( 'value="88"', $wrapper );
			self::assertStringContainsString( 'image-88.jpg', $wrapper );
			self::assertStringContainsString( '>Change image</button>', $wrapper );
		}

		/** Legacy jewelry products remain usable but default to Auto when edited. */
		public function test_legacy_jewelry_value_is_not_shown_as_a_new_selectable_option(): void {
			$GLOBALS['sea_tryon_test_product'] = new WC_Product(
				102,
				array( ProductFields::META_EXPERIENCE_TYPE => ExperienceType::JEWELRY )
			);
			$GLOBALS['post'] = (object) array( 'ID' => 102 );

			ob_start();
			( new ProductFields() )->render();
			ob_end_clean();

			self::assertSame( ExperienceType::AUTO, $GLOBALS['sea_tryon_test_rendered_fields']['select']['value'] );
			self::assertArrayNotHasKey( ExperienceType::JEWELRY, $GLOBALS['sea_tryon_test_rendered_fields']['select']['options'] );
		}

		/** A valid main image allows an enabled parent product to save atomically. */
		public function test_valid_main_image_allows_enabled_parent_product(): void {
			$product = new WC_Product( 202 );
			$checker = new FixedProductImageChecker( true );
			$_POST   = array(
				'woocommerce_meta_nonce'                    => 'valid-nonce',
				ProductFields::META_ENABLED                  => 'yes',
				ProductFields::META_PROMPT                   => 'Keep the <strong>blue</strong> frames.',
				ProductFields::META_EXPERIENCE_TYPE          => 'glasses',
				ProductFields::META_PRODUCT_IMAGE_ID         => '88',
			);
			$GLOBALS['sea_tryon_test_image_ids'] = array( 88 );

			( new ProductFields( $checker ) )->save( $product );

			self::assertSame(
				array(
					ProductFields::META_ENABLED         => 'yes',
					ProductFields::META_PROMPT          => 'Keep the blue frames.',
					ProductFields::META_EXPERIENCE_TYPE => 'glasses',
					ProductFields::META_PRODUCT_IMAGE_ID => '88',
				),
				$product->metadata()
			);
			self::assertSame( array(), WC_Admin_Meta_Boxes::$errors );
			self::assertSame( 1, $checker->calls() );
		}

		/** The WooCommerce nonce is sanitized before verification. */
		public function test_product_nonce_is_sanitized_before_verification(): void {
			$product = new WC_Product( 205 );
			$_POST   = array(
				'woocommerce_meta_nonce'            => '  valid-nonce  ',
				ProductFields::META_ENABLED          => 'no',
				ProductFields::META_PROMPT           => '',
				ProductFields::META_EXPERIENCE_TYPE  => 'auto',
				ProductFields::META_PRODUCT_IMAGE_ID => '',
			);

			( new ProductFields( new FixedProductImageChecker( false ) ) )->save( $product );

			self::assertSame( 'no', $product->metadata()[ ProductFields::META_ENABLED ] );
		}

		/** Enabling without a readable parent-product main image is rejected. */
		public function test_missing_main_image_rejects_enabled_product(): void {
			$product = new WC_Product( 203 );
			$checker = new FixedProductImageChecker( false );
			$_POST   = array(
				'woocommerce_meta_nonce'           => 'valid-nonce',
				ProductFields::META_ENABLED         => 'yes',
				ProductFields::META_PROMPT          => 'Place the chair naturally in the room.',
				ProductFields::META_EXPERIENCE_TYPE => 'furniture',
				ProductFields::META_PRODUCT_IMAGE_ID => '',
			);

			( new ProductFields( $checker ) )->save( $product );

			self::assertSame( array(), $product->metadata() );
			self::assertSame( array( 'Add a readable product image before enabling Virtual Try-On.' ), WC_Admin_Meta_Boxes::$errors );
			self::assertSame( 1, $checker->calls() );
		}

		/** A disabled product may save without a main image. */
		public function test_disabled_product_does_not_require_main_image(): void {
			$product = new WC_Product( 204 );
			$checker = new FixedProductImageChecker( false );
			$_POST   = array(
				'woocommerce_meta_nonce'           => 'valid-nonce',
				ProductFields::META_ENABLED         => 'no',
				ProductFields::META_PROMPT          => '',
				ProductFields::META_EXPERIENCE_TYPE => 'auto',
				ProductFields::META_PRODUCT_IMAGE_ID => '',
			);

			( new ProductFields( $checker ) )->save( $product );

			self::assertSame(
				array(
					ProductFields::META_ENABLED         => 'no',
					ProductFields::META_PROMPT          => '',
					ProductFields::META_EXPERIENCE_TYPE => 'auto',
				),
				$product->metadata()
			);
			self::assertSame( array(), WC_Admin_Meta_Boxes::$errors );
			self::assertSame( 0, $checker->calls() );
		}

		/** An empty optional prompt clears the previously saved prompt. */
		public function test_empty_prompt_clears_previous_value_for_enabled_product(): void {
			$original = array(
				ProductFields::META_ENABLED         => 'no',
				ProductFields::META_PROMPT          => 'Existing prompt',
				ProductFields::META_EXPERIENCE_TYPE => 'auto',
				ProductFields::META_PRODUCT_IMAGE_ID => '77',
			);
			$product  = new WC_Product( 303, $original );
			$_POST    = array(
				'woocommerce_meta_nonce'           => 'valid-nonce',
				ProductFields::META_ENABLED         => 'yes',
				ProductFields::META_PROMPT          => '',
				ProductFields::META_EXPERIENCE_TYPE => 'clothing',
				ProductFields::META_PRODUCT_IMAGE_ID => '',
			);

			$checker = new FixedProductImageChecker( true );
			( new ProductFields( $checker ) )->save( $product );

			self::assertSame(
				array(
					ProductFields::META_ENABLED         => 'yes',
					ProductFields::META_PROMPT          => '',
					ProductFields::META_EXPERIENCE_TYPE => 'clothing',
				),
				$product->metadata()
			);
			self::assertSame( array(), WC_Admin_Meta_Boxes::$errors );
			self::assertSame( 1, $checker->calls() );
		}

		/** An invalid optional attachment aborts the whole metadata update. */
		public function test_invalid_tryon_product_image_is_rejected_atomically(): void {
			$product = new WC_Product(
				304,
				array( ProductFields::META_PROMPT => 'Existing prompt' )
			);
			$_POST   = array(
				'woocommerce_meta_nonce'            => 'valid-nonce',
				ProductFields::META_ENABLED          => 'yes',
				ProductFields::META_PROMPT           => 'Replacement prompt',
				ProductFields::META_EXPERIENCE_TYPE  => 'clothing',
				ProductFields::META_PRODUCT_IMAGE_ID => '999',
			);

			( new ProductFields( new FixedProductImageChecker( true ) ) )->save( $product );

			self::assertSame( array( ProductFields::META_PROMPT => 'Existing prompt' ), $product->metadata() );
			self::assertSame( array( 'Virtual Try-On settings were not saved because the selected product image is invalid.' ), WC_Admin_Meta_Boxes::$errors );
		}

		/** Invalid authorization, nonce, or incomplete form data fails closed. */
		public function test_save_requires_capability_core_nonce_and_all_explicit_keys(): void {
			$product = new WC_Product( 404 );
			$_POST   = array(
				'woocommerce_meta_nonce'           => 'valid-nonce',
				ProductFields::META_ENABLED         => 'no',
				ProductFields::META_PROMPT          => 'Draft prompt',
				ProductFields::META_EXPERIENCE_TYPE => 'auto',
				ProductFields::META_PRODUCT_IMAGE_ID => '',
			);

			$GLOBALS['sea_tryon_test_can_edit'] = false;
			( new ProductFields() )->save( $product );
			self::assertSame( array(), $product->metadata() );

			$GLOBALS['sea_tryon_test_can_edit']    = true;
			$GLOBALS['sea_tryon_test_nonce_valid'] = false;
			( new ProductFields() )->save( $product );
			self::assertSame( array(), $product->metadata() );

			$GLOBALS['sea_tryon_test_nonce_valid'] = true;
			unset( $_POST[ ProductFields::META_PROMPT ] );
			( new ProductFields() )->save( $product );
			self::assertSame( array(), $product->metadata() );
		}
	}

	/** Deterministic image availability double. */
	final class FixedProductImageChecker implements ProductImageCheckerInterface {

		/** @var bool */
		private $readable;

		/** @var int */
		private $calls = 0;

		public function __construct( bool $readable ) {
			$this->readable = $readable;
		}

		public function has_readable_main_image( WC_Product $product ): bool {
			unset( $product );
			++$this->calls;
			return $this->readable;
		}

		public function calls(): int {
			return $this->calls;
		}
	}
}
