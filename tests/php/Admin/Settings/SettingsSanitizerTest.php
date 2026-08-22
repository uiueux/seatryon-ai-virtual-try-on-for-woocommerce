<?php
/**
 * Settings sanitizer tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! function_exists( '__' ) ) {
		function __( string $text, string $domain = 'default' ): string {
			unset( $domain );
			return $text;
		}
	}

	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( string $url, ?array $protocols = null ): string {
			unset( $protocols );
			return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
		}
	}
}

namespace SeaTryOn\Tests\Admin\Settings {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Admin\Settings\SettingsSanitizer;
	use SeaTryOn\Security\SecretStore;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SeaAIBaseUrlValidator;
	use SeaTryOn\Settings\SettingsRepository;

	defined( 'ABSPATH' ) || exit;

	final class SettingsSanitizerTest extends TestCase {

		public function test_provider_and_seaai_quality_values_are_allowlisted(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( 'seaai', $sanitizer->sanitize_provider( 'seaai' ) );
			self::assertSame( 'openai', $sanitizer->sanitize_provider( 'unexpected' ) );
			self::assertSame( 'medium', $sanitizer->sanitize_seaai_quality( 'medium' ) );
			self::assertSame( 'low', $sanitizer->sanitize_seaai_quality( 'auto' ) );
		}

		public function test_daily_limits_are_clamped_to_one_through_one_hundred(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '1', $sanitizer->sanitize_daily_limit( 0 ) );
			self::assertSame( '25', $sanitizer->sanitize_daily_limit( '25' ) );
			self::assertSame( '100', $sanitizer->sanitize_daily_limit( 999 ) );
			self::assertSame( '3', $sanitizer->sanitize_daily_limit( 'invalid' ) );
		}

		public function test_trigger_heights_are_clamped_to_supported_pixel_range(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '30', $sanitizer->sanitize_trigger_height( 0 ) );
			self::assertSame( '76', $sanitizer->sanitize_trigger_height( '76' ) );
			self::assertSame( '120', $sanitizer->sanitize_trigger_height( 999 ) );
			self::assertSame( '56', $sanitizer->sanitize_trigger_height( 'invalid' ) );
		}

		public function test_trigger_border_dimensions_are_clamped_to_supported_pixel_ranges(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '0', $sanitizer->sanitize_trigger_border_width( -2 ) );
			self::assertSame( '4', $sanitizer->sanitize_trigger_border_width( '4' ) );
			self::assertSame( '10', $sanitizer->sanitize_trigger_border_width( 99 ) );
			self::assertSame( '1', $sanitizer->sanitize_trigger_border_width( 'invalid' ) );
			self::assertSame( '0', $sanitizer->sanitize_trigger_border_radius( -2 ) );
			self::assertSame( '24', $sanitizer->sanitize_trigger_border_radius( '24' ) );
			self::assertSame( '100', $sanitizer->sanitize_trigger_border_radius( 999 ) );
			self::assertSame( '7', $sanitizer->sanitize_trigger_border_radius( 'invalid' ) );
		}

		public function test_trigger_font_size_is_clamped_to_supported_pixel_range(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '10', $sanitizer->sanitize_trigger_font_size( 0 ) );
			self::assertSame( '26', $sanitizer->sanitize_trigger_font_size( '26' ) );
			self::assertSame( '48', $sanitizer->sanitize_trigger_font_size( 999 ) );
			self::assertSame( '20', $sanitizer->sanitize_trigger_font_size( 'invalid' ) );
		}

		public function test_panel_radius_is_clamped_to_supported_pixel_range(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '0', $sanitizer->sanitize_panel_radius( -1 ) );
			self::assertSame( '32', $sanitizer->sanitize_panel_radius( '32' ) );
			self::assertSame( '100', $sanitizer->sanitize_panel_radius( 999 ) );
			self::assertSame( '10', $sanitizer->sanitize_panel_radius( 'invalid' ) );
		}

		public function test_button_colors_accept_hex_values_and_allow_resetting_to_defaults(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame( '#abc', $sanitizer->sanitize_color( ' #ABC ' ) );
			self::assertSame( '#123456', $sanitizer->sanitize_color( '#123456' ) );
			self::assertSame( '', $sanitizer->sanitize_color( '' ) );
			self::assertSame( '', $sanitizer->sanitize_color( 'rgb(0, 0, 0)' ) );
			self::assertSame( '', $sanitizer->sanitize_color( array( '#fff' ) ) );
		}

		public function test_seaai_url_requires_https_and_preserves_previous_value_on_error(): void {
			$store     = new AdminSettingsMemoryStore(
				array( SettingsRepository::OPTION_SEAAI_BASE_URL => 'https://old.example/wp-json/seaai/v1' )
			);
			$sanitizer = $this->sanitizer( $store );

			self::assertSame(
				'https://new.example/wp-json/seaai/v1',
				$sanitizer->sanitize_seaai_base_url( 'https://new.example/wp-json/seaai/v1/' )
			);
			self::assertSame(
				'https://old.example/wp-json/seaai/v1',
				$sanitizer->sanitize_seaai_base_url( 'http://insecure.example/wp-json/seaai/v1' )
			);
		}

		public function test_seaai_url_rejects_wrong_path_query_fragment_and_userinfo(): void {
			$store     = new AdminSettingsMemoryStore(
				array( SettingsRepository::OPTION_SEAAI_BASE_URL => 'https://old.example/wp-json/seaai/v1' )
			);
			$sanitizer = $this->sanitizer( $store );

			$invalid = array(
				'https://gateway.example/wp-json/seaai/v1/forward/image/generate',
				'https://gateway.example/wp-json/other/v1',
				'https://gateway.example/wp-json/seaai/v1?token=visible',
				'https://gateway.example/wp-json/seaai/v1#settings',
				'https://user:password@gateway.example/wp-json/seaai/v1',
			);

			foreach ( $invalid as $url ) {
				self::assertSame( 'https://old.example/wp-json/seaai/v1', $sanitizer->sanitize_seaai_base_url( $url ) );
			}
		}

		public function test_seaai_url_accepts_and_normalizes_subdirectory_wordpress_root(): void {
			$sanitizer = $this->sanitizer();

			self::assertSame(
				'https://gateway.example/store/wp-json/seaai/v1',
				$sanitizer->sanitize_seaai_base_url( 'https://gateway.example/store/wp-json/seaai/v1/' )
			);
		}

		public function test_seaai_url_accepts_loopback_http_on_a_loopback_wordpress_site(): void {
			$store = new AdminSettingsMemoryStore();
			$urls  = new SeaAIBaseUrlValidator(
				static function (): string {
					return 'production';
				},
				static function (): string {
					return 'http://localhost/wp/';
				}
			);
			$sanitizer = $this->sanitizer( $store, $urls );

			self::assertSame(
				'http://localhost/wp/wp-json/seaai/v1',
				$sanitizer->sanitize_seaai_base_url( 'http://localhost/wp/wp-json/seaai/v1/' )
			);
		}

		public function test_blank_and_masked_seaai_key_preserve_secret_while_new_key_is_sanitized(): void {
			$store     = new AdminSettingsMemoryStore(
				array( SettingsRepository::OPTION_SEAAI_API_KEY => 'existing-key' )
			);
			$sanitizer = $this->sanitizer( $store );

			self::assertSame( 'existing-key', $sanitizer->sanitize_seaai_api_key( '', array(), '' ) );
			self::assertSame( 'existing-key', $sanitizer->sanitize_seaai_api_key( SecretStore::MASK, array(), SecretStore::MASK ) );
			self::assertSame( 'replacement-key', $sanitizer->sanitize_seaai_api_key( '', array(), " replacement\r\n-key " ) );
			self::assertFalse( $store->updates[0]['autoload'] );
		}

		private function sanitizer(
			?AdminSettingsMemoryStore $store = null,
			?SeaAIBaseUrlValidator $urls = null
		): SettingsSanitizer {
			$repository = new SettingsRepository( $store ?? new AdminSettingsMemoryStore(), $urls );

			return new SettingsSanitizer( $repository, new SecretStore( $repository, $urls ), $urls );
		}
	}

	final class AdminSettingsMemoryStore implements OptionsStoreInterface {

		/** @var array<string,mixed> */
		private $values;

		/** @var array<int,array{name:string,value:mixed,autoload:bool}> */
		public $updates = array();

		/** @param array<string,mixed> $values Initial options. */
		public function __construct( array $values = array() ) {
			$this->values = $values;
		}

		public function get( string $name, $default = null ) {
			return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : $default;
		}

		public function update( string $name, $value, bool $autoload = false ): bool {
			$this->values[ $name ] = $value;
			$this->updates[]       = array(
				'name'     => $name,
				'value'    => $value,
				'autoload' => $autoload,
			);
			return true;
		}
	}
}
