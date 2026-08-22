<?php
/**
 * Frontend visibility and renderer tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {

	if ( ! class_exists( 'WC_Product', false ) ) {
		/** Minimal WooCommerce product double for isolated frontend tests. */
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

			/** @return array<string,string> */
			public function metadata(): array {
				return $this->metadata;
			}
		}
	}
}

namespace SeaTryOn\Frontend {

	/** Test single-product request state. */
	function is_product(): bool {
		return (bool) $GLOBALS['sea_tryon_frontend_is_product'];
	}

	/** Test authentication state. */
	function is_user_logged_in(): bool {
		return (bool) $GLOBALS['sea_tryon_frontend_logged_in'];
	}

	/** @return mixed */
	function apply_filters( string $hook, $value ) {
		if ( 'sea_tryon_trigger_visible' === $hook && isset( $GLOBALS['sea_tryon_frontend_visible_filter'] ) ) {
			return $GLOBALS['sea_tryon_frontend_visible_filter'];
		}

		return $value;
	}

	function __( string $text, string $domain ): string {
		unset( $domain );
		return $text;
	}

	function sanitize_html_class( string $class ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $class );
	}

	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html_e( string $text, string $domain ): void {
		echo esc_html( __( $text, $domain ) );
	}

	function esc_attr_e( string $text, string $domain ): void {
		echo esc_attr( __( $text, $domain ) );
	}

	/** Test action sink. */
	function do_action( string $hook ): void {
		$GLOBALS['sea_tryon_frontend_actions'][] = $hook;
	}

	/** Test hook collector. */
	function add_action( string $hook, callable $callback, int $priority = 10 ): void {
		$GLOBALS['sea_tryon_frontend_registered_hooks'][ $hook ] = array( $callback, $priority );
	}

	/** @return array<int,array<string,mixed>> */
	function parse_blocks( string $content ): array {
		unset( $content );
		return $GLOBALS['sea_tryon_frontend_parsed_blocks'];
	}
}

namespace SeaTryOn\Tests\Frontend {

	use PHPUnit\Framework\TestCase;
	use WC_Product;
	use SeaTryOn\Admin\Product\ProductFields;
	use SeaTryOn\Domain\ExperienceType;
	use SeaTryOn\Frontend\ModalRenderer;
	use SeaTryOn\Frontend\FrontendController;
	use SeaTryOn\Frontend\ManualBlockDetector;
	use SeaTryOn\Frontend\ProductImageReaderInterface;
	use SeaTryOn\Frontend\RenderContext;
	use SeaTryOn\Frontend\TriggerRenderer;
	use SeaTryOn\Frontend\VisibilityRules;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;

	defined( 'ABSPATH' ) || exit;

	/** Verifies the authoritative server visibility and shared output contract. */
	final class FrontendRendererTest extends TestCase {

		/** Reset request doubles. */
		protected function setUp(): void {
			parent::setUp();
			$GLOBALS['sea_tryon_frontend_is_product']    = true;
			$GLOBALS['sea_tryon_frontend_logged_in']     = false;
			$GLOBALS['sea_tryon_frontend_actions']       = array();
			$GLOBALS['sea_tryon_frontend_visible_filter'] = true;
			$GLOBALS['sea_tryon_frontend_registered_hooks'] = array();
			$GLOBALS['sea_tryon_frontend_parsed_blocks'] = array();
		}

		/** Every frozen visibility requirement is enforced server-side. */
		public function test_visibility_rules_fail_closed(): void {
			$store = new FrontendMemoryStore(
				array(
					SettingsRepository::OPTION_ENABLED      => 'yes',
					SettingsRepository::OPTION_ALLOW_GUESTS => 'yes',
				)
			);
			$rules = new VisibilityRules( new SettingsRepository( $store ), new FixedImageReader( true ) );
			$product = new FrontendProduct(
				91,
				array(
					ProductFields::META_ENABLED         => 'yes',
					ProductFields::META_PROMPT          => '',
					ProductFields::META_EXPERIENCE_TYPE => 'clothing',
				)
			);

			self::assertTrue( $rules->allows( $product, true, false ) );
			self::assertFalse( $rules->allows( $product, false, false ) );

			$store->update( SettingsRepository::OPTION_ALLOW_GUESTS, 'no' );
			// Guests still see the entry and receive a login prompt; REST rejects creation.
			self::assertTrue( $rules->allows( $product, true, false ) );
			self::assertTrue( $rules->allows( $product, true, true ) );

			$product->set_purchasable( false );
			self::assertFalse( $rules->allows( $product, true, true ) );
		}

		/** An eligible product with an empty optional prompt receives one non-submit button. */
		public function test_trigger_renders_once_and_is_filterable_only_after_eligibility(): void {
			$rules    = $this->enabled_rules();
			$product  = $this->eligible_product( 101 );
			$renderer = new TriggerRenderer( $rules );

			$markup = $renderer->render( $product, RenderContext::automatic() );

			self::assertStringContainsString( '<button type="button"', $markup );
			self::assertStringContainsString( 'Virtual Try-On', $markup );
			self::assertStringContainsString( 'sea-tryon-trigger__icon', $markup );
			self::assertStringContainsString( 'aria-hidden="true"', $markup );
			self::assertStringNotContainsString( '<svg', $markup );
			self::assertStringNotContainsString( 'dashicons-star-filled', $markup );
			self::assertStringContainsString( 'sea-tryon-trigger__label', $markup );
			self::assertStringNotContainsString( '_sea_tryon_prompt', $markup );
			self::assertSame( 1, preg_match( '/aria-controls="([^"]+)"/', $markup, $controls ) );
			$modal = ( new ModalRenderer() )->render( $renderer->rendered_products() );
			self::assertStringContainsString( 'id="' . $controls[1] . '"', $modal );
			self::assertSame( '', $renderer->render( $product, RenderContext::block() ) );
			self::assertSame( array( 101 => 'clothing' ), $renderer->rendered_products() );
		}

		/** Disabled guest generation remains discoverable so the modal can prompt login. */
		public function test_guest_disabled_still_renders_login_entry(): void {
			$store = new FrontendMemoryStore(
				array(
					SettingsRepository::OPTION_ENABLED      => 'yes',
					SettingsRepository::OPTION_ALLOW_GUESTS => 'no',
				)
			);
			$rules = new VisibilityRules( new SettingsRepository( $store ), new FixedImageReader( true ) );
			$GLOBALS['sea_tryon_frontend_visible_filter'] = true;

			self::assertStringContainsString( 'data-sea-tryon-open', ( new TriggerRenderer( $rules ) )->render( $this->eligible_product( 102 ), RenderContext::automatic() ) );
		}

		/** The footer root is a single accessible dialog with experience copy. */
		public function test_modal_is_once_only_and_uses_scene_copy(): void {
			$renderer = new ModalRenderer();
			$markup   = $renderer->render( array( 55 => 'furniture' ) );

			self::assertStringContainsString( 'role="dialog"', $markup );
			self::assertStringContainsString( 'aria-modal="true"', $markup );
			self::assertStringContainsString( 'Upload your room or scene', $markup );
			self::assertStringContainsString( 'data-sea-tryon-camera-open', $markup );
			self::assertStringContainsString( 'Take a photo', $markup );
			self::assertStringContainsString( 'data-sea-tryon-camera-video', $markup );
			self::assertStringContainsString( 'playsinline', $markup );
			self::assertStringContainsString( 'Capture photo', $markup );
			self::assertStringContainsString( 'aria-live="polite"', $markup );
			self::assertStringContainsString( 'data-sea-tryon-generate disabled', $markup );
			self::assertStringContainsString( 'data-sea-tryon-zoom', $markup );
			self::assertStringContainsString( 'View full-size preview', $markup );
			self::assertSame( '', $renderer->render( array( 55 => 'furniture' ) ) );
		}

		/** Specialized jewelry modes request a person photo in the modal. */
		public function test_modal_uses_person_copy_for_specialized_jewelry_types(): void {
			$markup = ( new ModalRenderer() )->render( array( 56 => ExperienceType::BELLY_BUTTON_RINGS ) );

			self::assertStringContainsString( 'data-experience-mode="person"', $markup );
			self::assertStringContainsString( 'Upload your photo', $markup );
			self::assertStringContainsString( 'relevant body area visible', $markup );
		}

		/** Automatic placement uses only the accepted public hook and priority. */
		public function test_frontend_controller_registers_public_mount_and_footer_hooks(): void {
			$rules      = $this->enabled_rules();
			$controller = new FrontendController(
				new TriggerRenderer( $rules ),
				new ModalRenderer(),
				new ManualBlockDetector(),
				$rules
			);

			$controller->register_hooks();

			self::assertSame( 20, $GLOBALS['sea_tryon_frontend_registered_hooks']['woocommerce_after_add_to_cart_form'][1] );
			self::assertArrayNotHasKey( 'woocommerce_single_product_summary', $GLOBALS['sea_tryon_frontend_registered_hooks'] );
			self::assertSame( 20, $GLOBALS['sea_tryon_frontend_registered_hooks']['wp_footer'][1] );
		}

		public function test_frontend_controller_outputs_sanitized_trigger_appearance_properties(): void {
			$store = new FrontendMemoryStore(
				array(
					SettingsRepository::OPTION_TRIGGER_TEXT_COLOR             => '#123456',
					SettingsRepository::OPTION_TRIGGER_BACKGROUND_COLOR       => '#abcdef',
					SettingsRepository::OPTION_TRIGGER_BORDER_COLOR           => '#222',
					SettingsRepository::OPTION_TRIGGER_HOVER_TEXT_COLOR       => '#fff',
					SettingsRepository::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR => '#000',
					SettingsRepository::OPTION_TRIGGER_HOVER_BORDER_COLOR     => '#111',
					SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT         => 72,
					SettingsRepository::OPTION_TRIGGER_MOBILE_HEIGHT          => 48,
					SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH             => 'yes',
					SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH           => 3,
					SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS          => 24,
					SettingsRepository::OPTION_TRIGGER_SHOW_ICON              => 'no',
					SettingsRepository::OPTION_TRIGGER_FONT_SIZE              => 28,
					SettingsRepository::OPTION_PANEL_ACCENT_COLOR              => '#102030',
					SettingsRepository::OPTION_PANEL_BORDER_COLOR              => '#203040',
					SettingsRepository::OPTION_PANEL_SURFACE_COLOR             => '#304050',
					SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR   => '#354555',
					SettingsRepository::OPTION_PANEL_TEXT_COLOR                => '#405060',
					SettingsRepository::OPTION_PANEL_MUTED_COLOR               => '#506070',
					SettingsRepository::OPTION_PANEL_ERROR_COLOR               => '#607080',
					SettingsRepository::OPTION_PANEL_RADIUS                    => 18,
				)
			);
			$settings   = new SettingsRepository( $store );
			$controller = new FrontendController( null, null, null, null, null, $settings );
			$method     = new \ReflectionMethod( FrontendController::class, 'trigger_button_css' );
			$method->setAccessible( true );
			$css = (string) $method->invoke( $controller );

			self::assertStringContainsString( '--sea-tryon-trigger-text-color:#123456', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-background-color:#abcdef', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-hover-border-color:#111', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-desktop-height:72px', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-mobile-height:48px', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-width:auto', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-border-width:3px', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-border-radius:24px', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-icon-display:none', $css );
			self::assertStringContainsString( '--sea-tryon-trigger-font-size:28px', $css );
			self::assertStringContainsString( '.sea-tryon-modal{--sea-tryon-accent:#102030', $css );
			self::assertStringContainsString( '--sea-tryon-border:#203040', $css );
			self::assertStringContainsString( '--sea-tryon-surface:#304050', $css );
			self::assertStringContainsString( '--sea-tryon-text:#405060', $css );
			self::assertStringContainsString( '--sea-tryon-muted:#506070', $css );
			self::assertStringContainsString( '--sea-tryon-error:#607080', $css );
			self::assertStringContainsString( '--sea-tryon-radius:18px', $css );
			self::assertStringContainsString( '--sea-tryon-upload-background:#354555', $css );
		}

		/** Manual fallback detection walks nested parsed blocks. */
		public function test_manual_block_detector_finds_nested_fallback(): void {
			$GLOBALS['sea_tryon_frontend_parsed_blocks'] = array(
				array(
					'blockName'   => 'core/group',
					'attrs'       => array(),
					'innerBlocks' => array(
						array(
							'blockName'   => ManualBlockDetector::BLOCK_NAME,
							'attrs'       => array(),
							'innerBlocks' => array(),
						),
					),
				),
			);

			self::assertTrue( ( new ManualBlockDetector() )->content_has_block( 'serialized blocks' ) );
		}

		/** Build enabled test rules. */
		private function enabled_rules(): VisibilityRules {
			$store = new FrontendMemoryStore(
				array(
					SettingsRepository::OPTION_ENABLED      => 'yes',
					SettingsRepository::OPTION_ALLOW_GUESTS => 'yes',
				)
			);

			return new VisibilityRules( new SettingsRepository( $store ), new FixedImageReader( true ) );
		}

		/** Build an eligible product double. */
		private function eligible_product( int $id ): FrontendProduct {
			return new FrontendProduct(
				$id,
				array(
					ProductFields::META_ENABLED         => 'yes',
					ProductFields::META_PROMPT          => '',
					ProductFields::META_EXPERIENCE_TYPE => 'clothing',
				)
			);
		}
	}

	/** Product test double with the storefront methods used by the renderer. */
	final class FrontendProduct extends WC_Product {

		/** @var bool */
		private $purchasable = true;

		/** Set whether this product may be purchased. */
		public function set_purchasable( bool $purchasable ): void {
			$this->purchasable = $purchasable;
		}

		/** Whether the product is purchasable. */
		public function is_purchasable(): bool {
			return $this->purchasable;
		}

		/** Return a deterministic attachment ID. */
		public function get_image_id( string $context = 'view' ): int {
			unset( $context );
			return 11;
		}
	}

	/** Fixed image check for visibility tests. */
	final class FixedImageReader implements ProductImageReaderInterface {

		/** @var bool */
		private $readable;

		public function __construct( bool $readable ) {
			$this->readable = $readable;
		}

		public function has_readable_image( WC_Product $product ): bool {
			unset( $product );
			return $this->readable;
		}
	}

	/** In-memory settings adapter. */
	final class FrontendMemoryStore implements OptionsStoreInterface {

		/** @var array<string,mixed> */
		private $values;

		/** @param array<string,mixed> $values Initial options. */
		public function __construct( array $values ) {
			$this->values = $values;
		}

		public function get( string $name, $default = null ) {
			return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : $default;
		}

		public function update( string $name, $value, bool $autoload = false ): bool {
			unset( $autoload );
			$this->values[ $name ] = $value;
			return true;
		}
	}
}
