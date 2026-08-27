<?php
/**
 * Classic Product Editor fields.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Product;

use WC_Admin_Meta_Boxes;
use WC_Product;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves parent-product Virtual Try-On metadata.
 */
final class ProductFields {

	public const META_ENABLED          = '_sea_tryon_enabled';
	public const META_PROMPT           = '_sea_tryon_prompt';
	public const META_EXPERIENCE_TYPE  = '_sea_tryon_experience_type';
	public const META_PRODUCT_IMAGE_ID = '_sea_tryon_product_image_id';

	/**
	 * Product main-image checker.
	 *
	 * @var ProductImageCheckerInterface
	 */
	private $image_checker;

	/**
	 * Set up product fields.
	 *
	 * @param ProductImageCheckerInterface|null $image_checker Image checker, or the WordPress implementation by default.
	 */
	public function __construct( ?ProductImageCheckerInterface $image_checker = null ) {
		$this->image_checker = $image_checker ?? new WordPressProductImageChecker();
	}

	/**
	 * Register public WooCommerce hooks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_options_advanced', array( $this, 'render' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_selector' ) );
	}

	/**
	 * Render fields in the Advanced product-data panel.
	 */
	public function render(): void {
		global $post;

		$product = $post && isset( $post->ID ) ? wc_get_product( (int) $post->ID ) : false;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$enabled          = 'yes' === $product->get_meta( self::META_ENABLED, true ) ? 'yes' : 'no';
		$prompt           = (string) $product->get_meta( self::META_PROMPT, true );
		$experience_type  = (string) $product->get_meta( self::META_EXPERIENCE_TYPE, true );
		$product_image_id = absint( $product->get_meta( self::META_PRODUCT_IMAGE_ID, true ) );

		if ( ! in_array( $experience_type, ExperienceType::selectable_values(), true ) ) {
			$experience_type = ExperienceType::AUTO;
		}

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'              => self::META_ENABLED,
				'label'           => __( 'Enable Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'description'     => __( 'Allow customers to generate an AI preview for this product.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc_tip'        => true,
				'value'           => $enabled,
				'checked_value'   => 'yes',
				'unchecked_value' => 'no',
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => self::META_EXPERIENCE_TYPE,
				'label'       => __( 'Experience Type', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'description' => __( 'Controls the image instructions and prompt template shown to customers.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc_tip'    => true,
				'value'       => $experience_type,
				'options'     => self::experience_type_options(),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'                => self::META_PROMPT,
				'label'             => __( 'Virtual Try-On Prompt', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'description'       => __( 'Optionally describe how this product should appear in the generated preview.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc_tip'          => true,
				'value'             => $prompt,
				'class'             => 'short',
				'rows'              => 4,
				'custom_attributes' => array(
					'maxlength' => (string) ProductFieldSubmission::MAX_PROMPT_LENGTH,
				),
			)
		);

		$this->render_product_image_field( $product_image_id );

		echo '</div>';
	}

	/**
	 * Load the WordPress media frame and the plugin admin bundle on product screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_media_selector( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$asset   = defined( 'SEA_TRYON_PATH' ) ? SEA_TRYON_PATH . 'assets/build/admin.asset.php' : '';
		$meta    = is_readable( $asset ) ? require $asset : array();
		$deps    = isset( $meta['dependencies'] ) && is_array( $meta['dependencies'] ) ? $meta['dependencies'] : array();
		$version = isset( $meta['version'] ) ? (string) $meta['version'] : ( defined( 'SEA_TRYON_VERSION' ) ? SEA_TRYON_VERSION : false );
		$url     = defined( 'SEA_TRYON_URL' ) ? SEA_TRYON_URL : '';

		wp_enqueue_media();
		wp_enqueue_style( 'sea-tryon-admin', $url . 'assets/build/admin.css', array(), $version );
		wp_enqueue_script( 'sea-tryon-admin', $url . 'assets/build/admin.js', $deps, $version, true );
	}

	/**
	 * Validate and stage product metadata for WooCommerce CRUD persistence.
	 *
	 * WooCommerce has already checked this nonce and capability before firing
	 * the hook. Rechecking here makes direct or unexpected hook calls fail closed.
	 *
	 * @param WC_Product $product Product being saved, including variable parents.
	 */
	public function save( WC_Product $product ): void {
		$product_id = $product->get_id();

		if (
			! current_user_can( 'edit_post', $product_id ) ||
			! isset( $_POST['woocommerce_meta_nonce'] ) ||
			! is_string( $_POST['woocommerce_meta_nonce'] )
		) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::META_ENABLED ] ) ||
			! isset( $_POST[ self::META_PROMPT ] ) ||
			! isset( $_POST[ self::META_EXPERIENCE_TYPE ] ) ||
			! isset( $_POST[ self::META_PRODUCT_IMAGE_ID ] )
		) {
			return;
		}

		$enabled_raw    = isset( $_POST[ self::META_ENABLED ] ) && is_scalar( $_POST[ self::META_ENABLED ] )
			? sanitize_key( (string) wp_unslash( $_POST[ self::META_ENABLED ] ) )
			: '';
		$prompt_raw     = isset( $_POST[ self::META_PROMPT ] ) && is_string( $_POST[ self::META_PROMPT ] )
			? sanitize_textarea_field( wp_unslash( $_POST[ self::META_PROMPT ] ) )
			: '';
		$experience_raw = isset( $_POST[ self::META_EXPERIENCE_TYPE ] ) && is_scalar( $_POST[ self::META_EXPERIENCE_TYPE ] )
			? sanitize_key( (string) wp_unslash( $_POST[ self::META_EXPERIENCE_TYPE ] ) )
			: '';
		$image_id_raw   = isset( $_POST[ self::META_PRODUCT_IMAGE_ID ] ) && is_scalar( $_POST[ self::META_PRODUCT_IMAGE_ID ] )
			? sanitize_text_field( (string) wp_unslash( $_POST[ self::META_PRODUCT_IMAGE_ID ] ) )
			: null;

		if ( null === $image_id_raw ) {
			$this->add_error( ProductFieldValidationException::INVALID_PRODUCT_IMAGE );
			return;
		}

		$image_id_value = trim( (string) $image_id_raw );
		if ( '' !== $image_id_value && ! ctype_digit( $image_id_value ) ) {
			$this->add_error( ProductFieldValidationException::INVALID_PRODUCT_IMAGE );
			return;
		}

		$product_image_id = absint( $image_id_value );
		if ( $product_image_id > 0 && ( ! wp_attachment_is_image( $product_image_id ) || ! current_user_can( 'edit_post', $product_image_id ) ) ) {
			$this->add_error( ProductFieldValidationException::INVALID_PRODUCT_IMAGE );
			return;
		}

		try {
			$submission = ProductFieldSubmission::from_raw(
				$enabled_raw,
				$prompt_raw,
				$experience_raw,
				static function ( string $prompt ): string {
					return sanitize_textarea_field( $prompt );
				}
			);
		} catch ( ProductFieldValidationException $exception ) {
			$this->add_error( $exception->reason() );
			return;
		}

		if ( $submission->is_enabled() && ! $this->image_checker->has_readable_main_image( $product ) ) {
			$this->add_error( ProductFieldValidationException::MISSING_PRODUCT_IMAGE );
			return;
		}

		$product->update_meta_data( self::META_ENABLED, $submission->is_enabled() ? 'yes' : 'no' );
		$product->update_meta_data( self::META_PROMPT, $submission->prompt() );
		$product->update_meta_data( self::META_EXPERIENCE_TYPE, $submission->experience_type() );

		if ( $product_image_id > 0 ) {
			$product->update_meta_data( self::META_PRODUCT_IMAGE_ID, (string) $product_image_id );
		} else {
			$product->delete_meta_data( self::META_PRODUCT_IMAGE_ID );
		}
	}

	/**
	 * Add a safe, translatable error to WooCommerce's product editor notices.
	 *
	 * @param string $reason Stable validation reason.
	 */
	private function add_error( string $reason ): void {
		$messages = array(
			ProductFieldValidationException::INVALID_ENABLED => __( 'Virtual Try-On settings were not saved because the enable value is invalid.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ProductFieldValidationException::INVALID_UTF8 => __( 'Virtual Try-On settings were not saved because the prompt contains invalid text encoding.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ProductFieldValidationException::PROMPT_TOO_LONG => sprintf(
				/* translators: %d: Maximum prompt length in characters. */
				__( 'Virtual Try-On settings were not saved because the prompt exceeds %d characters.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				ProductFieldSubmission::MAX_PROMPT_LENGTH
			),
			ProductFieldValidationException::INVALID_EXPERIENCE => __( 'Virtual Try-On settings were not saved because the experience type is invalid.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ProductFieldValidationException::INVALID_PRODUCT_IMAGE => __( 'Virtual Try-On settings were not saved because the selected product image is invalid.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ProductFieldValidationException::MISSING_PRODUCT_IMAGE => __( 'Add a readable product image before enabling Virtual Try-On.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
		);

		WC_Admin_Meta_Boxes::add_error(
			isset( $messages[ $reason ] ) ? $messages[ $reason ] : __( 'Virtual Try-On settings could not be saved.', 'seatryon-ai-virtual-try-on-for-woocommerce' )
		);
	}

	/**
	 * Render an optional WordPress Media Library selector below the prompt.
	 *
	 * @param int $image_id Stored attachment ID.
	 */
	private function render_product_image_field( int $image_id ): void {
		if ( $image_id > 0 && ! wp_attachment_is_image( $image_id ) ) {
			$image_id = 0;
		}

		$preview = $image_id > 0
			? wp_get_attachment_image(
				$image_id,
				'thumbnail',
				false,
				array(
					'class' => 'sea-tryon-product-image__thumbnail',
					'alt'   => '',
				)
			)
			: '';

		printf(
			'<p class="form-field %1$s_field"><label for="%1$s">%2$s</label><span class="sea-tryon-product-image-field" data-sea-tryon-product-image data-select-label="%3$s" data-change-label="%4$s" data-media-title="%5$s" data-media-button="%6$s"><input type="hidden" id="%1$s" name="%1$s" value="%7$s"><span class="sea-tryon-product-image__preview" data-sea-tryon-image-preview>%8$s</span><span class="sea-tryon-product-image__actions"><button type="button" class="button" data-sea-tryon-image-select>%9$s</button><button type="button" class="button-link-delete" data-sea-tryon-image-remove%10$s>%11$s</button></span><span class="description">%12$s</span></span></p>',
			esc_attr( self::META_PRODUCT_IMAGE_ID ),
			esc_html__( 'Try-On Product Image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_attr__( 'Select image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_attr__( 'Change image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_attr__( 'Select a Try-On Product Image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_attr__( 'Use this image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_attr( $image_id > 0 ? (string) $image_id : '' ),
			$preview, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by wp_get_attachment_image().
			esc_html( $image_id > 0 ? __( 'Change image', 'seatryon-ai-virtual-try-on-for-woocommerce' ) : __( 'Select image', 'seatryon-ai-virtual-try-on-for-woocommerce' ) ),
			$image_id > 0 ? '' : ' hidden',
			esc_html__( 'Remove image', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_html__( "Optional. Select the product image sent to the AI provider. If empty, the product's main image is used.", 'seatryon-ai-virtual-try-on-for-woocommerce' )
		);
	}

	/**
	 * Return translated experience options keyed by stable stored values.
	 *
	 * @return array<string,string>
	 */
	private static function experience_type_options(): array {
		return array(
			ExperienceType::AUTO               => __( 'Auto', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::CLOTHING           => __( 'Clothing', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::HATS               => __( 'Hats', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::SHOES              => __( 'Shoes', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::HANDBAGS           => __( 'Handbags', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::EARRINGS           => __( 'Earrings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::RINGS              => __( 'Rings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::NECKLACES          => __( 'Necklaces', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::BRACELETS          => __( 'Bracelets', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::NOSE_RINGS         => __( 'Nose Rings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::BELLY_BUTTON_RINGS => __( 'Belly Button Rings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::HAIR_ACCESSORIES   => __( 'Hair Accessories', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::ANKLETS            => __( 'Anklets', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::BROOCHES_PINS      => __( 'Brooches & Pins', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::LIP_RINGS          => __( 'Lip Rings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::TONGUE_RINGS       => __( 'Tongue Rings', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::BODY_CHAINS        => __( 'Body Chains', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::GLASSES            => __( 'Glasses', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::WIG                => __( 'Wig', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::FURNITURE          => __( 'Furniture', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			ExperienceType::PRODUCT_PLACEMENT  => __( 'Product Placement', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
		);
	}
}
