<?php
/**
 * WooCommerce Products settings section.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Products > Virtual Try-On using WooCommerce's public settings filters.
 */
final class SettingsPage {

	public const SECTION_ID = 'sea_tryon';

	private const SEAAI_PROFILE_URL = 'https://theminitech.net/profile/';

	/**
	 * Typed settings access.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Provider credential persistence.
	 *
	 * @var SecretStore
	 */
	private $secrets;

	/**
	 * Settings input sanitizer.
	 *
	 * @var SettingsSanitizer
	 */
	private $sanitizer;

	/**
	 * Usage statistics controller.
	 *
	 * @var StatisticsController
	 */
	private $statistics;

	/**
	 * Set up the Products settings section.
	 *
	 * @param SettingsRepository|null   $settings   Settings repository.
	 * @param SecretStore|null          $secrets    Secret persistence service.
	 * @param SettingsSanitizer|null    $sanitizer  Settings sanitizer.
	 * @param StatisticsController|null $statistics Statistics controller.
	 */
	public function __construct(
		?SettingsRepository $settings = null,
		?SecretStore $secrets = null,
		?SettingsSanitizer $sanitizer = null,
		?StatisticsController $statistics = null
	) {
		$this->settings   = $settings ?? new SettingsRepository();
		$this->secrets    = $secrets ?? new SecretStore( $this->settings );
		$this->sanitizer  = $sanitizer ?? new SettingsSanitizer( $this->settings, $this->secrets );
		$this->statistics = $statistics ?? new StatisticsController( $this->settings );
	}

	/** Register the section, fields, sanitizers, statistics, and conditional UI. */
	public function register_hooks(): void {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'get_settings' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_conditional_fields' ) );

		$this->sanitizer->register_hooks();
		$this->statistics->register_hooks();
	}

	/**
	 * Add the Virtual Try-On section label.
	 *
	 * @param array<string,string> $sections Existing Products sections.
	 * @return array<string,string>
	 */
	public function add_section( array $sections ): array {
		$sections[ self::SECTION_ID ] = __( 'Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' );

		return $sections;
	}

	/**
	 * Return our fields only for the Virtual Try-On section.
	 *
	 * @param array<int,array<string,mixed>> $settings Existing settings.
	 * @param string                         $current_section Current section ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_settings( array $settings, string $current_section ): array {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		return $this->build_settings();
	}

	/**
	 * Build WooCommerce settings fields with frozen MVP defaults.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function build_settings(): array {
		return array(
			array(
				'title' => __( 'Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'  => __( 'Configure AI-generated try-on and product placement previews.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'sea_tryon_general_options',
			),
			array(
				'title'   => __( 'Enable Virtual Try-On', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'    => __( 'Allow enabled products to offer AI previews.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'id'      => SettingsRepository::OPTION_ENABLED,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => __( 'AI provider', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'     => __( 'WordPress AI uses the provider and credentials configured under Settings > Connectors.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => SettingsRepository::OPTION_PROVIDER,
				'type'     => 'select',
				'class'    => 'wc-enhanced-select sea-tryon-provider-selector',
				'default'  => SettingsRepository::PROVIDER_WORDPRESS_AI,
				'options'  => array(
					SettingsRepository::PROVIDER_WORDPRESS_AI => __( 'WordPress AI Client (site connector)', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					SettingsRepository::PROVIDER_SEAAI => __( 'SeaAI Universal X', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				),
			),
			array(
				'title'       => __( 'SeaAI Base URL', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'        => __( 'Gateway root ending at /wp-json/seaai/v1. Use HTTPS; HTTP loopback is accepted only for local development.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc_tip'    => true,
				'id'          => SettingsRepository::OPTION_SEAAI_BASE_URL,
				'type'        => 'url',
				'default'     => SettingsRepository::DEFAULT_SEAAI_BASE_URL,
				'placeholder' => SettingsRepository::DEFAULT_SEAAI_BASE_URL,
				'row_class'   => 'sea-tryon-provider-seaai',
				'autoload'    => false,
			),
			$this->secret_field(
				SettingsRepository::OPTION_SEAAI_API_KEY,
				__( 'SeaAI API key', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Use the SeaAI user key, not the upstream Universal X provider key.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'sea-tryon-provider-seaai',
				true
			),
			array(
				'title'   => __( 'Allow guests', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'    => __( 'Allow visitors to generate previews without signing in.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'id'      => SettingsRepository::OPTION_ALLOW_GUESTS,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			$this->limit_field(
				SettingsRepository::OPTION_LOGGED_IN_DAILY_LIMIT,
				__( 'Logged-in daily limit', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Maximum provider dispatches per signed-in user each day.', 'seatryon-ai-virtual-try-on-for-woocommerce' )
			),
			$this->limit_field(
				SettingsRepository::OPTION_GUEST_DAILY_LIMIT,
				__( 'Guest daily limit', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Maximum provider dispatches per anonymous session each day.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'sea-tryon-guests-only'
			),
			array(
				'title'   => __( 'Debug mode', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'    => __( 'Write sanitized diagnostic events to the WooCommerce log.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'id'      => SettingsRepository::OPTION_DEBUG_MODE,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sea_tryon_general_options',
			),
			array(
				'title' => __( 'Try-On button appearance', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'  => __( 'Customize the storefront button shown on eligible product pages.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'sea_tryon_button_appearance_options',
			),
			array(
				'title'    => __( 'Button width', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'     => __( 'Automatic (fit content)', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'id'       => SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			),
			array(
				'title'    => __( 'Show button icon', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'     => __( 'Display the SVG icon before the button text.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'id'       => SettingsRepository::OPTION_TRIGGER_SHOW_ICON,
				'type'     => 'checkbox',
				'default'  => 'yes',
				'autoload' => false,
			),
			$this->trigger_pixel_field(
				SettingsRepository::OPTION_TRIGGER_FONT_SIZE,
				__( 'Button font size', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Font size in pixels from 10 to 48. The icon scales with this value.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_FONT_SIZE,
				SettingsRepository::MIN_TRIGGER_FONT_SIZE,
				SettingsRepository::MAX_TRIGGER_FONT_SIZE
			),
			$this->trigger_height_field(
				SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT,
				__( 'Desktop button height', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_DESKTOP_HEIGHT
			),
			$this->trigger_height_field(
				SettingsRepository::OPTION_TRIGGER_MOBILE_HEIGHT,
				__( 'Mobile button height', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_MOBILE_HEIGHT
			),
			$this->trigger_pixel_field(
				SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH,
				__( 'Button border width', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Border width in pixels from 0 to 10. Use 0 for no border.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_BORDER_WIDTH,
				SettingsRepository::MIN_TRIGGER_BORDER_WIDTH,
				SettingsRepository::MAX_TRIGGER_BORDER_WIDTH
			),
			$this->trigger_pixel_field(
				SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS,
				__( 'Button corner radius', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Corner radius in pixels from 0 to 100.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_BORDER_RADIUS,
				SettingsRepository::MIN_TRIGGER_BORDER_RADIUS,
				SettingsRepository::MAX_TRIGGER_BORDER_RADIUS
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_TEXT_COLOR,
				__( 'Button text color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_TEXT_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_BACKGROUND_COLOR,
				__( 'Button background color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_BACKGROUND_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_BORDER_COLOR,
				__( 'Button border color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_BORDER_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_HOVER_TEXT_COLOR,
				__( 'Hover text color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_HOVER_TEXT_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR,
				__( 'Hover background color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_HOVER_BACKGROUND_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_TRIGGER_HOVER_BORDER_COLOR,
				__( 'Hover border color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_TRIGGER_HOVER_BORDER_COLOR
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sea_tryon_button_appearance_options',
			),
			array(
				'title' => __( 'Try-On panel appearance', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'desc'  => __( 'Customize the main colors and corner radius of the Try-On workflow panel.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'sea_tryon_panel_appearance_options',
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_ACCENT_COLOR,
				__( 'Panel accent color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_ACCENT_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_BORDER_COLOR,
				__( 'Panel border color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_BORDER_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_SURFACE_COLOR,
				__( 'Panel surface color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_SURFACE_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR,
				__( 'Upload area background color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_UPLOAD_BACKGROUND_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_TEXT_COLOR,
				__( 'Panel text color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_TEXT_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_MUTED_COLOR,
				__( 'Panel muted text color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_MUTED_COLOR
			),
			$this->color_field(
				SettingsRepository::OPTION_PANEL_ERROR_COLOR,
				__( 'Panel error color', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_ERROR_COLOR
			),
			$this->trigger_pixel_field(
				SettingsRepository::OPTION_PANEL_RADIUS,
				__( 'Panel corner radius', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				__( 'Corner radius in pixels from 0 to 100.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				SettingsRepository::DEFAULT_PANEL_RADIUS,
				SettingsRepository::MIN_PANEL_RADIUS,
				SettingsRepository::MAX_PANEL_RADIUS
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sea_tryon_panel_appearance_options',
			),
			array(
				'title' => __( 'Usage statistics', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'sea_tryon_statistics_options',
			),
			array(
				'title'     => __( 'Successful generations', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'type'      => 'sea_tryon_statistics',
				'id'        => 'sea_tryon_statistics_display',
				'is_option' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sea_tryon_statistics_options',
			),
		);
	}

	/**
	 * Enqueue the existing admin bundle and add progressive conditional rows.
	 * Hidden fields remain enabled so switching provider never clears settings.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_conditional_fields( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix || ! self::is_current_section() ) {
			return;
		}

		$asset   = defined( 'SEA_TRYON_PATH' ) ? SEA_TRYON_PATH . 'assets/build/admin.asset.php' : '';
		$meta    = is_readable( $asset ) ? require $asset : array();
		$deps    = isset( $meta['dependencies'] ) && is_array( $meta['dependencies'] ) ? $meta['dependencies'] : array();
		$version = isset( $meta['version'] ) ? (string) $meta['version'] : ( defined( 'SEA_TRYON_VERSION' ) ? SEA_TRYON_VERSION : false );
		$url     = defined( 'SEA_TRYON_URL' ) ? SEA_TRYON_URL : '';

		wp_enqueue_style( 'sea-tryon-admin', $url . 'assets/build/admin.css', array(), $version );
		wp_enqueue_script( 'sea-tryon-admin', $url . 'assets/build/admin.js', $deps, $version, true );
		wp_localize_script(
			'sea-tryon-admin',
			'sea_tryon_seaai_connection',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => SeaAIConnectionController::AJAX_ACTION,
				'nonce'     => wp_create_nonce( SeaAIConnectionController::NONCE_ACTION ),
				'getKeyUrl' => self::SEAAI_PROFILE_URL,
				'messages'  => array(
					'button'  => __( 'Test connection', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					'getKey'  => __( 'Get a key for free', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					'testing' => __( 'Testing connection…', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					'failed'  => __( 'The connection test failed. Please try again.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				),
			)
		);
		wp_add_inline_script( 'sea-tryon-admin', $this->conditional_fields_script() );
	}

	/**
	 * Determine whether the current request is our Products settings section.
	 */
	public static function is_current_section(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'wc-settings' === $page && 'products' === $tab && self::SECTION_ID === $section;
	}

	/**
	 * Build a masked password field.
	 *
	 * @param string $id             Option ID.
	 * @param string $title          Field label.
	 * @param string $desc           Help text.
	 * @param string $row_class      Conditional row class.
	 * @param bool   $connection_test Add the SeaAI connection-test component marker.
	 * @return array<string,mixed>
	 */
	private function secret_field( string $id, string $title, string $desc, string $row_class, bool $connection_test = false ): array {
		$stored            = (string) $this->settings->options()->get( $id, '' );
		$custom_attributes = array(
			'autocomplete' => 'new-password',
			'aria-label'   => $title,
		);

		if ( $connection_test ) {
			$custom_attributes['data-sea-tryon-seaai-key'] = 'true';
		}

		return array(
			'title'             => $title,
			'desc'              => $desc,
			'desc_tip'          => true,
			'id'                => $id,
			'type'              => 'password',
			'value'             => $this->secrets->mask( $stored ),
			'row_class'         => $row_class,
			'autoload'          => false,
			'custom_attributes' => $custom_attributes,
		);
	}

	/**
	 * Build a bounded daily limit field.
	 *
	 * @param string $id        Option ID.
	 * @param string $title     Field label.
	 * @param string $desc      Help text.
	 * @param string $row_class Optional conditional row class.
	 * @return array<string,mixed>
	 */
	private function limit_field( string $id, string $title, string $desc, string $row_class = '' ): array {
		return array(
			'title'             => $title,
			'desc'              => $desc,
			'desc_tip'          => true,
			'id'                => $id,
			'type'              => 'number',
			'default'           => '3',
			'row_class'         => $row_class,
			'custom_attributes' => array(
				'min'  => '1',
				'max'  => '100',
				'step' => '1',
			),
		);
	}

	/**
	 * Build a WooCommerce color-picker field for the storefront trigger.
	 *
	 * @param string $id      Option ID.
	 * @param string $title   Field label.
	 * @param string $default_color Default hexadecimal color.
	 * @return array<string,mixed>
	 */
	private function color_field( string $id, string $title, string $default_color ): array {
		return array(
			'title'    => $title,
			'id'       => $id,
			'type'     => 'color',
			'default'  => $default_color,
			'autoload' => false,
		);
	}

	/**
	 * Build a bounded pixel-height field for the storefront trigger.
	 *
	 * @param string $id             Option ID.
	 * @param string $title          Field label.
	 * @param int    $default_height Default height in pixels.
	 * @return array<string,mixed>
	 */
	private function trigger_height_field( string $id, string $title, int $default_height ): array {
		return $this->trigger_pixel_field(
			$id,
			$title,
			__( 'Height in pixels from 30 to 120.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			$default_height,
			SettingsRepository::MIN_TRIGGER_HEIGHT,
			SettingsRepository::MAX_TRIGGER_HEIGHT
		);
	}

	/**
	 * Build a bounded pixel-value field for the storefront trigger.
	 *
	 * @param string $id            Option ID.
	 * @param string $title         Field label.
	 * @param string $description   Field description.
	 * @param int    $default_value Default value in pixels.
	 * @param int    $minimum       Minimum value in pixels.
	 * @param int    $maximum       Maximum value in pixels.
	 * @return array<string,mixed>
	 */
	private function trigger_pixel_field(
		string $id,
		string $title,
		string $description,
		int $default_value,
		int $minimum,
		int $maximum
	): array {
		return array(
			'title'             => $title,
			'desc'              => $description,
			'desc_tip'          => true,
			'id'                => $id,
			'type'              => 'number',
			'default'           => (string) $default_value,
			'autoload'          => false,
			'custom_attributes' => array(
				'min'  => (string) $minimum,
				'max'  => (string) $maximum,
				'step' => '1',
			),
		);
	}

	/**
	 * Return dependency-free JavaScript for provider/guest conditional rows.
	 */
	private function conditional_fields_script(): string {
		return <<<'JS'
( function () {
	'use strict';
	var provider = document.getElementById( 'sea_tryon_provider' );
	var guests = document.getElementById( 'sea_tryon_allow_guests' );
	function setRows( selector, visible ) {
		document.querySelectorAll( selector ).forEach( function ( row ) {
			row.hidden = ! visible;
			row.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );
		} );
	}
	function update() {
		var selected = provider ? provider.value : 'openai';
		setRows( '.wc-settings-row-sea-tryon-provider-seaai', selected === 'seaai' );
		setRows( '.wc-settings-row-sea-tryon-guests-only', ! guests || guests.checked );
	}
	if ( provider ) {
		provider.addEventListener( 'change', update );
		if ( window.jQuery ) {
			window.jQuery( provider ).on( 'change.seaTryOn', update );
		}
	}
	if ( guests ) {
		guests.addEventListener( 'change', update );
	}
	update();
}() );
JS;
	}
}
