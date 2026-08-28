<?php
/**
 * Typed access to plugin settings.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes persisted values and supplies the frozen MVP defaults.
 */
final class SettingsRepository {

	/** Existing stored value retained while the implementation uses WordPress AI. */
	public const PROVIDER_WORDPRESS_AI                  = 'openai';
	public const PROVIDER_SEAAI                         = 'seaai';
	public const DEFAULT_SEAAI_BASE_URL                 = 'https://theminitech.net/wp-json/seaai/v1';
	public const DEFAULT_TRIGGER_TEXT_COLOR             = '#171717';
	public const DEFAULT_TRIGGER_BACKGROUND_COLOR       = '#ffffff';
	public const DEFAULT_TRIGGER_BORDER_COLOR           = '#4a4a4a';
	public const DEFAULT_TRIGGER_HOVER_TEXT_COLOR       = '#ffffff';
	public const DEFAULT_TRIGGER_HOVER_BACKGROUND_COLOR = '#171717';
	public const DEFAULT_TRIGGER_HOVER_BORDER_COLOR     = '#171717';
	public const DEFAULT_TRIGGER_DESKTOP_HEIGHT         = 56;
	public const DEFAULT_TRIGGER_MOBILE_HEIGHT          = 56;
	public const MIN_TRIGGER_HEIGHT                     = 30;
	public const MAX_TRIGGER_HEIGHT                     = 120;
	public const DEFAULT_TRIGGER_BORDER_WIDTH           = 1;
	public const MIN_TRIGGER_BORDER_WIDTH               = 0;
	public const MAX_TRIGGER_BORDER_WIDTH               = 10;
	public const DEFAULT_TRIGGER_BORDER_RADIUS          = 7;
	public const MIN_TRIGGER_BORDER_RADIUS              = 0;
	public const MAX_TRIGGER_BORDER_RADIUS              = 100;
	public const DEFAULT_TRIGGER_FONT_SIZE              = 20;
	public const MIN_TRIGGER_FONT_SIZE                  = 10;
	public const MAX_TRIGGER_FONT_SIZE                  = 48;
	public const DEFAULT_PANEL_ACCENT_COLOR             = '#5b55f7';
	public const DEFAULT_PANEL_BORDER_COLOR             = '#d9dde7';
	public const DEFAULT_PANEL_SURFACE_COLOR            = '#ffffff';
	public const DEFAULT_PANEL_UPLOAD_BACKGROUND_COLOR  = '#fbfcfe';
	public const DEFAULT_PANEL_TEXT_COLOR               = '#151827';
	public const DEFAULT_PANEL_MUTED_COLOR              = '#64708a';
	public const DEFAULT_PANEL_ERROR_COLOR              = '#b42318';
	public const DEFAULT_PANEL_RADIUS                   = 10;
	public const MIN_PANEL_RADIUS                       = 0;
	public const MAX_PANEL_RADIUS                       = 100;

	public const OPTION_ENABLED                        = 'sea_tryon_enabled';
	public const OPTION_PROVIDER                       = 'sea_tryon_provider';
	public const OPTION_SEAAI_BASE_URL                 = 'sea_tryon_seaai_base_url';
	public const OPTION_SEAAI_API_KEY                  = 'sea_tryon_seaai_api_key';
	public const OPTION_SEAAI_QUALITY                  = 'sea_tryon_seaai_quality';
	public const OPTION_ALLOW_GUESTS                   = 'sea_tryon_allow_guests';
	public const OPTION_LOGGED_IN_DAILY_LIMIT          = 'sea_tryon_logged_in_daily_limit';
	public const OPTION_GUEST_DAILY_LIMIT              = 'sea_tryon_guest_daily_limit';
	public const OPTION_SITE_DAILY_LIMIT               = 'sea_tryon_site_daily_limit';
	public const OPTION_DEBUG_MODE                     = 'sea_tryon_debug_mode';
	public const OPTION_SUCCESS_COUNT                  = 'sea_tryon_success_count';
	public const OPTION_TRIGGER_TEXT_COLOR             = 'sea_tryon_trigger_text_color';
	public const OPTION_TRIGGER_BACKGROUND_COLOR       = 'sea_tryon_trigger_background_color';
	public const OPTION_TRIGGER_BORDER_COLOR           = 'sea_tryon_trigger_border_color';
	public const OPTION_TRIGGER_HOVER_TEXT_COLOR       = 'sea_tryon_trigger_hover_text_color';
	public const OPTION_TRIGGER_HOVER_BACKGROUND_COLOR = 'sea_tryon_trigger_hover_background_color';
	public const OPTION_TRIGGER_HOVER_BORDER_COLOR     = 'sea_tryon_trigger_hover_border_color';
	public const OPTION_TRIGGER_DESKTOP_HEIGHT         = 'sea_tryon_trigger_desktop_height';
	public const OPTION_TRIGGER_MOBILE_HEIGHT          = 'sea_tryon_trigger_mobile_height';
	public const OPTION_TRIGGER_AUTO_WIDTH             = 'sea_tryon_trigger_auto_width';
	public const OPTION_TRIGGER_BORDER_WIDTH           = 'sea_tryon_trigger_border_width';
	public const OPTION_TRIGGER_BORDER_RADIUS          = 'sea_tryon_trigger_border_radius';
	public const OPTION_TRIGGER_SHOW_ICON              = 'sea_tryon_trigger_show_icon';
	public const OPTION_TRIGGER_FONT_SIZE              = 'sea_tryon_trigger_font_size';
	public const OPTION_PANEL_ACCENT_COLOR             = 'sea_tryon_panel_accent_color';
	public const OPTION_PANEL_BORDER_COLOR             = 'sea_tryon_panel_border_color';
	public const OPTION_PANEL_SURFACE_COLOR            = 'sea_tryon_panel_surface_color';
	public const OPTION_PANEL_UPLOAD_BACKGROUND_COLOR  = 'sea_tryon_panel_upload_background_color';
	public const OPTION_PANEL_TEXT_COLOR               = 'sea_tryon_panel_text_color';
	public const OPTION_PANEL_MUTED_COLOR              = 'sea_tryon_panel_muted_color';
	public const OPTION_PANEL_ERROR_COLOR              = 'sea_tryon_panel_error_color';
	public const OPTION_PANEL_RADIUS                   = 'sea_tryon_panel_radius';

	/**
	 * Option persistence adapter.
	 *
	 * @var OptionsStoreInterface
	 */
	private $options;

	/**
	 * Shared SeaAI gateway URL policy.
	 *
	 * @var SeaAIBaseUrlValidator
	 */
	private $seaai_urls;

	/**
	 * Set up typed settings access.
	 *
	 * @param OptionsStoreInterface|null $options     Option adapter, or WordPress by default.
	 * @param SeaAIBaseUrlValidator|null $seaai_urls  SeaAI gateway URL policy.
	 */
	public function __construct( ?OptionsStoreInterface $options = null, ?SeaAIBaseUrlValidator $seaai_urls = null ) {
		$this->options    = $options ?? new WordPressOptionsStore();
		$this->seaai_urls = $seaai_urls ?? new SeaAIBaseUrlValidator();
	}

	/**
	 * Determine whether Virtual Try-On is globally enabled.
	 */
	public function is_enabled(): bool {
		return $this->to_bool( $this->options->get( self::OPTION_ENABLED, false ) );
	}

	/**
	 * Return the selected, normalized provider identifier.
	 */
	public function get_provider(): string {
		$provider = strtolower( trim( (string) $this->options->get( self::OPTION_PROVIDER, self::PROVIDER_WORDPRESS_AI ) ) );

		return self::PROVIDER_SEAAI === $provider ? self::PROVIDER_SEAAI : self::PROVIDER_WORDPRESS_AI;
	}

	/**
	 * Determine whether anonymous visitors may create jobs.
	 */
	public function allow_guests(): bool {
		return $this->to_bool( $this->options->get( self::OPTION_ALLOW_GUESTS, false ) );
	}

	/**
	 * Return the daily per-user dispatch limit.
	 */
	public function get_logged_in_daily_limit(): int {
		return $this->bounded_int( $this->options->get( self::OPTION_LOGGED_IN_DAILY_LIMIT, 3 ), 3, 1, 100 );
	}

	/**
	 * Return the daily per-guest-session dispatch limit.
	 */
	public function get_guest_daily_limit(): int {
		return $this->bounded_int( $this->options->get( self::OPTION_GUEST_DAILY_LIMIT, 3 ), 3, 1, 100 );
	}

	/**
	 * Return the optional whole-site daily dispatch limit.
	 *
	 * A value of zero explicitly leaves the global cap disabled.
	 */
	public function get_site_daily_limit(): ?int {
		$limit = $this->bounded_int( $this->options->get( self::OPTION_SITE_DAILY_LIMIT, 30 ), 30, 0, 100000 );

		return $limit > 0 ? $limit : null;
	}

	/**
	 * Return the normalized SeaAI image quality.
	 */
	public function get_seaai_quality(): string {
		return $this->enum_value(
			$this->options->get( self::OPTION_SEAAI_QUALITY, 'low' ),
			array( 'low', 'medium', 'high' ),
			'low'
		);
	}

	/**
	 * Determine whether verbose debug logging is enabled.
	 */
	public function is_debug_mode(): bool {
		return $this->to_bool( $this->options->get( self::OPTION_DEBUG_MODE, false ) );
	}

	/**
	 * Return the non-negative successful generation count.
	 */
	public function get_success_count(): int {
		return max( 0, (int) $this->options->get( self::OPTION_SUCCESS_COUNT, 0 ) );
	}

	/**
	 * Return sanitized storefront trigger colors with stable visual defaults.
	 *
	 * @return array<string,string>
	 */
	public function get_trigger_colors(): array {
		return array(
			'text'             => $this->hex_color( self::OPTION_TRIGGER_TEXT_COLOR, self::DEFAULT_TRIGGER_TEXT_COLOR ),
			'background'       => $this->hex_color( self::OPTION_TRIGGER_BACKGROUND_COLOR, self::DEFAULT_TRIGGER_BACKGROUND_COLOR ),
			'border'           => $this->hex_color( self::OPTION_TRIGGER_BORDER_COLOR, self::DEFAULT_TRIGGER_BORDER_COLOR ),
			'hover_text'       => $this->hex_color( self::OPTION_TRIGGER_HOVER_TEXT_COLOR, self::DEFAULT_TRIGGER_HOVER_TEXT_COLOR ),
			'hover_background' => $this->hex_color( self::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR, self::DEFAULT_TRIGGER_HOVER_BACKGROUND_COLOR ),
			'hover_border'     => $this->hex_color( self::OPTION_TRIGGER_HOVER_BORDER_COLOR, self::DEFAULT_TRIGGER_HOVER_BORDER_COLOR ),
		);
	}

	/**
	 * Return bounded storefront trigger heights for desktop and mobile layouts.
	 *
	 * @return array<string,int>
	 */
	public function get_trigger_heights(): array {
		return array(
			'desktop' => $this->bounded_int(
				$this->options->get( self::OPTION_TRIGGER_DESKTOP_HEIGHT, self::DEFAULT_TRIGGER_DESKTOP_HEIGHT ),
				self::DEFAULT_TRIGGER_DESKTOP_HEIGHT,
				self::MIN_TRIGGER_HEIGHT,
				self::MAX_TRIGGER_HEIGHT
			),
			'mobile'  => $this->bounded_int(
				$this->options->get( self::OPTION_TRIGGER_MOBILE_HEIGHT, self::DEFAULT_TRIGGER_MOBILE_HEIGHT ),
				self::DEFAULT_TRIGGER_MOBILE_HEIGHT,
				self::MIN_TRIGGER_HEIGHT,
				self::MAX_TRIGGER_HEIGHT
			),
		);
	}

	/**
	 * Determine whether the storefront trigger should fit its content width.
	 */
	public function trigger_uses_auto_width(): bool {
		return $this->to_bool( $this->options->get( self::OPTION_TRIGGER_AUTO_WIDTH, false ) );
	}

	/**
	 * Determine whether the storefront trigger should display its SVG icon.
	 */
	public function trigger_shows_icon(): bool {
		return $this->to_bool( $this->options->get( self::OPTION_TRIGGER_SHOW_ICON, true ) );
	}

	/**
	 * Return the bounded storefront trigger font size in pixels.
	 */
	public function get_trigger_font_size(): int {
		return $this->bounded_int(
			$this->options->get( self::OPTION_TRIGGER_FONT_SIZE, self::DEFAULT_TRIGGER_FONT_SIZE ),
			self::DEFAULT_TRIGGER_FONT_SIZE,
			self::MIN_TRIGGER_FONT_SIZE,
			self::MAX_TRIGGER_FONT_SIZE
		);
	}

	/**
	 * Return sanitized Try-On panel colors and corner radius.
	 *
	 * @return array<string,int|string>
	 */
	public function get_panel_appearance(): array {
		return array(
			'accent'            => $this->hex_color( self::OPTION_PANEL_ACCENT_COLOR, self::DEFAULT_PANEL_ACCENT_COLOR ),
			'border'            => $this->hex_color( self::OPTION_PANEL_BORDER_COLOR, self::DEFAULT_PANEL_BORDER_COLOR ),
			'surface'           => $this->hex_color( self::OPTION_PANEL_SURFACE_COLOR, self::DEFAULT_PANEL_SURFACE_COLOR ),
			'upload_background' => $this->hex_color( self::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR, self::DEFAULT_PANEL_UPLOAD_BACKGROUND_COLOR ),
			'text'              => $this->hex_color( self::OPTION_PANEL_TEXT_COLOR, self::DEFAULT_PANEL_TEXT_COLOR ),
			'muted'             => $this->hex_color( self::OPTION_PANEL_MUTED_COLOR, self::DEFAULT_PANEL_MUTED_COLOR ),
			'error'             => $this->hex_color( self::OPTION_PANEL_ERROR_COLOR, self::DEFAULT_PANEL_ERROR_COLOR ),
			'radius'            => $this->bounded_int(
				$this->options->get( self::OPTION_PANEL_RADIUS, self::DEFAULT_PANEL_RADIUS ),
				self::DEFAULT_PANEL_RADIUS,
				self::MIN_PANEL_RADIUS,
				self::MAX_PANEL_RADIUS
			),
		);
	}

	/**
	 * Return bounded border dimensions for the storefront trigger.
	 *
	 * @return array<string,int>
	 */
	public function get_trigger_border_dimensions(): array {
		return array(
			'width'  => $this->bounded_int(
				$this->options->get( self::OPTION_TRIGGER_BORDER_WIDTH, self::DEFAULT_TRIGGER_BORDER_WIDTH ),
				self::DEFAULT_TRIGGER_BORDER_WIDTH,
				self::MIN_TRIGGER_BORDER_WIDTH,
				self::MAX_TRIGGER_BORDER_WIDTH
			),
			'radius' => $this->bounded_int(
				$this->options->get( self::OPTION_TRIGGER_BORDER_RADIUS, self::DEFAULT_TRIGGER_BORDER_RADIUS ),
				self::DEFAULT_TRIGGER_BORDER_RADIUS,
				self::MIN_TRIGGER_BORDER_RADIUS,
				self::MAX_TRIGGER_BORDER_RADIUS
			),
		);
	}

	/**
	 * Return the persisted SeaAI gateway URL after structural validation.
	 */
	public function get_stored_seaai_base_url(): string {
		return $this->seaai_urls->normalize(
			(string) $this->options->get( self::OPTION_SEAAI_BASE_URL, self::DEFAULT_SEAAI_BASE_URL )
		);
	}

	/**
	 * Expose the adapter to collaborating domain services.
	 */
	public function options(): OptionsStoreInterface {
		return $this->options;
	}

	/**
	 * Normalize common WordPress/WooCommerce boolean representations.
	 *
	 * @param mixed $value Raw option value.
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Bound a numeric option.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Fallback value.
	 * @param int   $minimum  Minimum accepted value.
	 * @param int   $maximum  Maximum accepted value.
	 */
	private function bounded_int( $value, int $fallback, int $minimum, int $maximum ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return min( $maximum, max( $minimum, (int) $value ) );
	}

	/**
	 * Restrict an option to an allowlist.
	 *
	 * @param mixed         $value   Raw value.
	 * @param array<string> $allowed Allowed values.
	 * @param string        $fallback Fallback value.
	 */
	private function enum_value( $value, array $allowed, string $fallback ): string {
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Return a normalized three- or six-digit hexadecimal color.
	 *
	 * @param string $option   Stored option name.
	 * @param string $fallback Default color.
	 */
	private function hex_color( string $option, string $fallback ): string {
		$value = strtolower( trim( (string) $this->options->get( $option, $fallback ) ) );

		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : $fallback;
	}
}
