<?php
/**
 * WooCommerce product context resolver.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use WC_Product;
use WC_Product_Variation;
use SeaTryOn\Admin\Product\ProductFields;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Prompt\PromptComposer;
use SeaTryOn\Provider\WordPressAI\WordPressAIClient;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Upload\ImageProcessorInterface;
use SeaTryOn\Upload\UploadService;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Re-resolves all product data server-side; no client image URL is accepted. */
final class WordPressProductContextResolver implements ProductContextResolverInterface {
	/** @var SettingsRepository */ private $settings;
	/** @var PromptComposer */ private $prompts;
	/** @var ImageProcessorInterface */ private $images;
	/** @var TemporaryStorageInterface */ private $storage;
	/** @var SecretStore */ private $secrets;
	/** @var WordPressAIClientInterface */ private $wordpress_ai;

	public function __construct( SettingsRepository $settings, PromptComposer $prompts, ImageProcessorInterface $images, TemporaryStorageInterface $storage, ?SecretStore $secrets = null, ?WordPressAIClientInterface $wordpress_ai = null ) {
		$this->settings     = $settings;
		$this->prompts      = $prompts;
		$this->images       = $images;
		$this->storage      = $storage;
		$this->secrets      = $secrets ?? new SecretStore( $settings );
		$this->wordpress_ai = $wordpress_ai ?? new WordPressAIClient();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws RestException When configuration or product context is unavailable.
	 */
	public function resolve( int $product_id, ?int $variation_id, string $scope_id ): ProductContext {
		$provider = $this->settings->get_provider();
		if ( SettingsRepository::PROVIDER_SEAAI === $provider ) {
			if ( '' === $this->secrets->get_active_api_key() ) {
				throw new RestException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, array(), 'seaai_api_key_missing' );
			}
			if ( '' === $this->secrets->get_seaai_base_url() ) {
				throw new RestException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, array(), 'seaai_base_url_invalid' );
			}
		} elseif ( ! $this->wordpress_ai->supports_image_editing() ) {
			throw new RestException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, array(), 'wordpress_ai_image_editing_unavailable' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $this->settings->is_enabled() || ! $product instanceof WC_Product || 'yes' !== $product->get_meta( ProductFields::META_ENABLED, true ) || ! $product->is_purchasable() ) {
			$this->unavailable();
		}
		if ( $product->is_type( 'simple' ) && null !== $variation_id ) {
			$this->unavailable();
		}
		if ( $product->is_type( 'variable' ) && null === $variation_id ) {
			$this->unavailable();
		}
		if ( ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			$this->unavailable();
		}

		$image_product = $product;
		if ( $product->is_type( 'variable' ) ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || $variation->get_parent_id() !== $product_id || ! $variation->is_purchasable() ) {
				$this->unavailable();
			}
			if ( $variation->get_image_id( 'edit' ) > 0 ) {
				$image_product = $variation;
			}
		}

		$prompt_raw = trim( (string) $product->get_meta( ProductFields::META_PROMPT, true ) );
		$type_raw   = (string) $product->get_meta( ProductFields::META_EXPERIENCE_TYPE, true );
		if ( ! in_array( $type_raw, ExperienceType::values(), true ) ) {
			$this->unavailable();
		}

		$custom_image_id = absint( $product->get_meta( ProductFields::META_PRODUCT_IMAGE_ID, true ) );
		$path            = $this->readable_image_path( $custom_image_id );

		if ( null === $path ) {
			$path = $this->readable_image_path( (int) $image_product->get_image_id( 'edit' ) );
		}

		if ( null === $path ) {
			$this->unavailable();
		}

		$image     = $this->images->normalize( $path, basename( $path ), UploadService::MAX_BYTES );
		$reference = $this->storage->write( $scope_id, 'product', $image->bytes(), $image->extension() );
		$type      = ExperienceType::from_string( $type_raw );

		return new ProductContext( $product_id, $variation_id, $this->settings->get_provider(), $type, $this->prompts->compose( $type, $prompt_raw ), $reference );
	}

	/** Return a readable local image path for an attachment, or null. */
	private function readable_image_path( int $image_id ): ?string {
		if ( $image_id < 1 || ! wp_attachment_is_image( $image_id ) ) {
			return null;
		}

		$path = get_attached_file( $image_id );

		return is_string( $path ) && '' !== $path && is_readable( $path ) ? $path : null;
	}

	private function unavailable(): void {
		throw new RestException( 'tryon_not_enabled', __( 'Virtual Try-On is not available for this product.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 403 );
	}
}
