<?php
/**
 * Product-page frontend adapters.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use WC_Product;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the shared renderer to public WooCommerce and WordPress hooks.
 */
final class FrontendController {

	/**
	 * Shared trigger renderer.
	 *
	 * @var TriggerRenderer
	 */
	private $renderer;

	/**
	 * Form-independent modal renderer.
	 *
	 * @var ModalRenderer
	 */
	private $modal;

	/**
	 * Manual fallback block detector.
	 *
	 * @var ManualBlockDetector
	 */
	private $blocks;

	/**
	 * Server-side visibility rules.
	 *
	 * @var VisibilityRules
	 */
	private $rules;

	/**
	 * Non-secret REST bootstrap provider.
	 *
	 * @var FrontendConfigProviderInterface
	 */
	private $config;

	/**
	 * Typed plugin settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Set up the public frontend adapters.
	 *
	 * @param TriggerRenderer|null                 $renderer Shared trigger renderer.
	 * @param ModalRenderer|null                   $modal    Modal renderer.
	 * @param ManualBlockDetector|null             $blocks   Manual-block detector.
	 * @param VisibilityRules|null                 $rules  Visibility rules for assets.
	 * @param FrontendConfigProviderInterface|null $config Runtime configuration provider.
	 * @param SettingsRepository|null              $settings Typed plugin settings.
	 */
	public function __construct(
		?TriggerRenderer $renderer = null,
		?ModalRenderer $modal = null,
		?ManualBlockDetector $blocks = null,
		?VisibilityRules $rules = null,
		?FrontendConfigProviderInterface $config = null,
		?SettingsRepository $settings = null
	) {
		$this->renderer = $renderer ?? TriggerRenderer::instance();
		$this->modal    = $modal ?? new ModalRenderer();
		$this->blocks   = $blocks ?? new ManualBlockDetector();
		$this->rules    = $rules ?? new VisibilityRules();
		$this->config   = $config ?? new WordPressFrontendConfigProvider();
		$this->settings = $settings ?? new SettingsRepository();
	}

	/** Register the frontend and block adapters. */
	public function register_hooks(): void {
		add_action( 'init', array( new BlockRegistrar(), 'register' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_automatic_trigger' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_modal_root' ), 20 );
	}

	/** Render the default automatic button near the purchase form. */
	public function render_automatic_trigger(): void {
		if ( ! $this->automatic_mount_enabled() ) {
			return;
		}

		$product = $this->current_product();

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Markup is escaped at the point of construction by TriggerRenderer.
		echo $this->renderer->render( $product, RenderContext::automatic() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Emit the form-independent modal root once in the footer. */
	public function render_modal_root(): void {
		if ( ! $this->renderer->has_rendered_trigger() ) {
			return;
		}

		// Markup and all dynamic values are escaped by ModalRenderer.
		echo $this->modal->render( $this->renderer->rendered_products() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->render_lightbox_template();
	}

	/** Enqueue the production bundle only on an eligible single-product page. */
	public function enqueue_assets(): void {
		$product = $this->current_product();

		if ( ! $product instanceof WC_Product || ! $this->rules->allows( $product, is_product(), is_user_logged_in() ) ) {
			return;
		}

		if ( ! $this->automatic_mount_enabled() && ! $this->blocks->current_request_has_block() ) {
			return;
		}

		$plugin_path = defined( 'SEA_TRYON_PATH' ) ? (string) constant( 'SEA_TRYON_PATH' ) : '';
		$plugin_url  = defined( 'SEA_TRYON_URL' ) ? (string) constant( 'SEA_TRYON_URL' ) : '';

		if ( '' === $plugin_path || '' === $plugin_url ) {
			return;
		}

		$asset_path = $plugin_path . 'assets/build/frontend.asset.php';
		$asset      = is_readable( $asset_path ) ? require $asset_path : array();
		$asset      = is_array( $asset ) ? $asset : array();
		$version    = isset( $asset['version'] ) ? (string) $asset['version'] : ( defined( 'SEA_TRYON_VERSION' ) ? (string) constant( 'SEA_TRYON_VERSION' ) : '1.1.2' );

		wp_enqueue_style( 'photoswipe-default-skin' );
		wp_enqueue_style( 'sea-tryon-frontend', $plugin_url . 'assets/build/frontend.css', array( 'dashicons' ), $version );
		wp_add_inline_style( 'sea-tryon-frontend', $this->trigger_button_css() );
		wp_enqueue_script(
			'sea-tryon-frontend',
			$plugin_url . 'assets/build/frontend.js',
			array_values(
				array_unique(
					array_merge(
						isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
						array( 'wp-i18n', 'wc-photoswipe-ui-default' )
					)
				)
			),
			$version,
			true
		);
		wp_set_script_translations( 'sea-tryon-frontend', 'seatryon-ai-virtual-try-on-for-woocommerce', $plugin_path . 'languages' );

		$config = $this->config->for_product( $product->get_id() );
		$json   = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		if ( is_string( $json ) ) {
			wp_add_inline_script( 'sea-tryon-frontend', 'window.SeaTryOnConfig = ' . $json . ';', 'before' );
		}
	}

	/**
	 * Build scoped CSS custom properties for merchant-selected trigger appearance.
	 */
	private function trigger_button_css(): string {
		$colors  = $this->settings->get_trigger_colors();
		$heights = $this->settings->get_trigger_heights();
		$width   = $this->settings->trigger_uses_auto_width() ? 'auto' : '100%';
		$border  = $this->settings->get_trigger_border_dimensions();
		$icon    = $this->settings->trigger_shows_icon() ? 'inline-flex' : 'none';
		$font    = $this->settings->get_trigger_font_size();
		$panel   = $this->settings->get_panel_appearance();

		$trigger_css = sprintf(
			'.sea-tryon-trigger{--sea-tryon-trigger-text-color:%1$s;--sea-tryon-trigger-background-color:%2$s;--sea-tryon-trigger-border-color:%3$s;--sea-tryon-trigger-hover-text-color:%4$s;--sea-tryon-trigger-hover-background-color:%5$s;--sea-tryon-trigger-hover-border-color:%6$s;--sea-tryon-trigger-desktop-height:%7$dpx;--sea-tryon-trigger-mobile-height:%8$dpx;--sea-tryon-trigger-width:%9$s;--sea-tryon-trigger-border-width:%10$dpx;--sea-tryon-trigger-border-radius:%11$dpx;--sea-tryon-trigger-icon-display:%12$s;--sea-tryon-trigger-font-size:%13$dpx;}',
			$colors['text'],
			$colors['background'],
			$colors['border'],
			$colors['hover_text'],
			$colors['hover_background'],
			$colors['hover_border'],
			$heights['desktop'],
			$heights['mobile'],
			$width,
			$border['width'],
			$border['radius'],
			$icon,
			$font
		);

		$panel_css = sprintf(
			'.sea-tryon-modal{--sea-tryon-accent:%1$s;--sea-tryon-border:%2$s;--sea-tryon-surface:%3$s;--sea-tryon-text:%4$s;--sea-tryon-muted:%5$s;--sea-tryon-error:%6$s;--sea-tryon-radius:%7$dpx;--sea-tryon-upload-background:%8$s;}',
			$panel['accent'],
			$panel['border'],
			$panel['surface'],
			$panel['text'],
			$panel['muted'],
			$panel['error'],
			$panel['radius'],
			$panel['upload_background']
		);

		return $trigger_css . $panel_css;
	}

	/** Render WooCommerce's PhotoSwipe shell when the active theme has not already added it. */
	private function render_lightbox_template(): void {
		if ( function_exists( 'has_action' ) && false !== has_action( 'wp_footer', 'woocommerce_photoswipe' ) ) {
			return;
		}

		if ( function_exists( 'wc_get_template' ) ) {
			wc_get_template( 'single-product/photoswipe.php' );
		}
	}

	/**
	 * Whether the standard action adapter is active for this request.
	 *
	 * A manual block is checked first so it retains the merchant-selected
	 * position even when its render occurs after the add-to-cart block.
	 */
	private function automatic_mount_enabled(): bool {
		if ( $this->blocks->current_request_has_block() ) {
			return false;
		}

		/**
		 * Filters whether automatic mounting is active for the current request.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'sea_tryon_automatic_mount_enabled', true );
	}

	/** Resolve the authoritative queried product. */
	private function current_product(): ?WC_Product {
		if ( ! is_product() ) {
			return null;
		}

		$product = wc_get_product( get_queried_object_id() );

		return $product instanceof WC_Product ? $product : null;
	}
}
