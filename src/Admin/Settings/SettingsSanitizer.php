<?php
/**
 * WooCommerce settings sanitization.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

use InvalidArgumentException;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Validates every merchant-controlled global setting.
 */
final class SettingsSanitizer {

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
	 * Shared SeaAI gateway URL policy.
	 *
	 * @var SeaAIBaseUrlValidator
	 */
	private $seaai_urls;

	/**
	 * Set up settings input sanitization.
	 *
	 * @param SettingsRepository|null    $settings    Settings repository.
	 * @param SecretStore|null           $secrets     Secret persistence service.
	 * @param SeaAIBaseUrlValidator|null $seaai_urls SeaAI gateway URL policy.
	 */
	public function __construct(
		?SettingsRepository $settings = null,
		?SecretStore $secrets = null,
		?SeaAIBaseUrlValidator $seaai_urls = null
	) {
		$this->settings   = $settings ?? new SettingsRepository();
		$this->secrets    = $secrets ?? new SecretStore( $this->settings );
		$this->seaai_urls = $seaai_urls ?? new SeaAIBaseUrlValidator();
	}

	/**
	 * Register option-specific WooCommerce sanitizers.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_PROVIDER, array( $this, 'sanitize_provider' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_SEAAI_API_KEY, array( $this, 'sanitize_seaai_api_key' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_SEAAI_BASE_URL, array( $this, 'sanitize_seaai_base_url' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_SEAAI_QUALITY, array( $this, 'sanitize_seaai_quality' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_LOGGED_IN_DAILY_LIMIT, array( $this, 'sanitize_daily_limit' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_GUEST_DAILY_LIMIT, array( $this, 'sanitize_daily_limit' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT, array( $this, 'sanitize_trigger_height' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_TRIGGER_MOBILE_HEIGHT, array( $this, 'sanitize_trigger_height' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH, array( $this, 'sanitize_trigger_border_width' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS, array( $this, 'sanitize_trigger_border_radius' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_TRIGGER_FONT_SIZE, array( $this, 'sanitize_trigger_font_size' ), 10, 1 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . SettingsRepository::OPTION_PANEL_RADIUS, array( $this, 'sanitize_panel_radius' ), 10, 1 );

		foreach ( $this->appearance_color_options() as $option ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . $option, array( $this, 'sanitize_color' ), 10, 1 );
		}
	}

	/**
	 * Restrict provider selection to the two supported adapters.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 * @return string
	 */
	public function sanitize_provider( $value ): string {
		return SettingsRepository::PROVIDER_SEAAI === $value
			? SettingsRepository::PROVIDER_SEAAI
			: SettingsRepository::PROVIDER_WORDPRESS_AI;
	}

	/**
	 * Preserve or replace the SeaAI key through SecretStore.
	 *
	 * @param mixed $value     Sanitized WooCommerce value.
	 * @param mixed $option    Field definition.
	 * @param mixed $raw_value Raw submitted value.
	 * @return string
	 */
	public function sanitize_seaai_api_key( $value, $option = null, $raw_value = null ): string {
		unset( $value, $option );

		return $this->sanitize_secret( SettingsRepository::OPTION_SEAAI_API_KEY, $raw_value );
	}

	/**
	 * Require an HTTPS gateway root, with a loopback-only local HTTP exception.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 * @return string
	 */
	public function sanitize_seaai_base_url( $value ): string {
		$url = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $url ) {
			return '';
		}

		$normalized = $this->seaai_urls->normalize( $url );
		if ( '' === $normalized ) {
			$this->add_error( __( 'SeaAI Base URL must end in /wp-json/seaai/v1 and use HTTPS. HTTP is allowed only for a loopback gateway during local development.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) );
			return $this->settings->get_stored_seaai_base_url();
		}

		return $normalized;
	}

	/**
	 * Restrict SeaAI quality to Universal X values.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_seaai_quality( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		return in_array( $value, array( 'low', 'medium', 'high' ), true ) ? $value : 'low';
	}

	/**
	 * Bound both daily limits to 1-100.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 * @return string WooCommerce stores scalar settings as strings.
	 */
	public function sanitize_daily_limit( $value ): string {
		$limit = is_numeric( $value ) ? (int) $value : 3;

		return (string) min( 100, max( 1, $limit ) );
	}

	/**
	 * Bound trigger heights to a usable pixel range.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 * @return string WooCommerce stores scalar settings as strings.
	 */
	public function sanitize_trigger_height( $value ): string {
		$height = is_numeric( $value ) ? (int) $value : SettingsRepository::DEFAULT_TRIGGER_DESKTOP_HEIGHT;

		return (string) min( SettingsRepository::MAX_TRIGGER_HEIGHT, max( SettingsRepository::MIN_TRIGGER_HEIGHT, $height ) );
	}

	/**
	 * Bound trigger border width to its supported pixel range.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_trigger_border_width( $value ): string {
		$width = is_numeric( $value ) ? (int) $value : SettingsRepository::DEFAULT_TRIGGER_BORDER_WIDTH;

		return (string) min( SettingsRepository::MAX_TRIGGER_BORDER_WIDTH, max( SettingsRepository::MIN_TRIGGER_BORDER_WIDTH, $width ) );
	}

	/**
	 * Bound trigger corner radius to its supported pixel range.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_trigger_border_radius( $value ): string {
		$radius = is_numeric( $value ) ? (int) $value : SettingsRepository::DEFAULT_TRIGGER_BORDER_RADIUS;

		return (string) min( SettingsRepository::MAX_TRIGGER_BORDER_RADIUS, max( SettingsRepository::MIN_TRIGGER_BORDER_RADIUS, $radius ) );
	}

	/**
	 * Bound trigger font size to its supported pixel range.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_trigger_font_size( $value ): string {
		$size = is_numeric( $value ) ? (int) $value : SettingsRepository::DEFAULT_TRIGGER_FONT_SIZE;

		return (string) min( SettingsRepository::MAX_TRIGGER_FONT_SIZE, max( SettingsRepository::MIN_TRIGGER_FONT_SIZE, $size ) );
	}

	/**
	 * Bound panel corner radius to its supported pixel range.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_panel_radius( $value ): string {
		$radius = is_numeric( $value ) ? (int) $value : SettingsRepository::DEFAULT_PANEL_RADIUS;

		return (string) min( SettingsRepository::MAX_PANEL_RADIUS, max( SettingsRepository::MIN_PANEL_RADIUS, $radius ) );
	}

	/**
	 * Accept a three- or six-digit hexadecimal color, or an empty reset value.
	 *
	 * @param mixed $value Sanitized WooCommerce value.
	 */
	public function sanitize_color( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		if ( '' === $value ) {
			return '';
		}

		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
	}

	/**
	 * Preserve or replace a provider credential.
	 *
	 * @param string $option    Option holding the secret.
	 * @param mixed  $raw_value Raw submitted value.
	 */
	private function sanitize_secret( string $option, $raw_value ): string {
		$submitted = is_scalar( $raw_value ) ? (string) $raw_value : '';

		try {
			$this->secrets->save_seaai_api_key( $submitted );
		} catch ( InvalidArgumentException $exception ) {
			unset( $exception );
			$this->add_error( __( 'The API key contains no usable characters.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) );
		}

		return (string) $this->settings->options()->get( $option, '' );
	}

	/**
	 * Return every merchant-controlled appearance color option.
	 *
	 * @return array<string>
	 */
	private function appearance_color_options(): array {
		return array(
			SettingsRepository::OPTION_TRIGGER_TEXT_COLOR,
			SettingsRepository::OPTION_TRIGGER_BACKGROUND_COLOR,
			SettingsRepository::OPTION_TRIGGER_BORDER_COLOR,
			SettingsRepository::OPTION_TRIGGER_HOVER_TEXT_COLOR,
			SettingsRepository::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR,
			SettingsRepository::OPTION_TRIGGER_HOVER_BORDER_COLOR,
			SettingsRepository::OPTION_PANEL_ACCENT_COLOR,
			SettingsRepository::OPTION_PANEL_BORDER_COLOR,
			SettingsRepository::OPTION_PANEL_SURFACE_COLOR,
			SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR,
			SettingsRepository::OPTION_PANEL_TEXT_COLOR,
			SettingsRepository::OPTION_PANEL_MUTED_COLOR,
			SettingsRepository::OPTION_PANEL_ERROR_COLOR,
		);
	}

	/**
	 * Add a WooCommerce settings error when its public helper is loaded.
	 *
	 * @param string $message Safe translated error message.
	 */
	private function add_error( string $message ): void {
		if ( class_exists( 'WC_Admin_Settings' ) ) {
			\WC_Admin_Settings::add_error( $message );
		}
	}
}
