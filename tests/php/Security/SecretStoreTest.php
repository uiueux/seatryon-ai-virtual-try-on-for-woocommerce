<?php
/**
 * Secret store tests and a controllable WordPress filter double.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $hook, $value, ...$arguments ) {
			$filters = isset( $GLOBALS['sea_tryon_test_filters'] ) && is_array( $GLOBALS['sea_tryon_test_filters'] )
				? $GLOBALS['sea_tryon_test_filters']
				: array();

			return isset( $filters[ $hook ] ) && is_callable( $filters[ $hook ] )
				? $filters[ $hook ]( $value, ...$arguments )
				: $value;
		}
	}

	if ( ! function_exists( 'wp_get_environment_type' ) ) {
		function wp_get_environment_type(): string {
			return isset( $GLOBALS['sea_tryon_test_environment'] )
				? (string) $GLOBALS['sea_tryon_test_environment']
				: 'production';
		}
	}
}

namespace SeaTryOn\Tests\Security {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Security\SecretStore;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;

	defined( 'ABSPATH' ) || exit;

	final class SecretStoreTest extends TestCase {

		protected function tearDown(): void {
			$GLOBALS['sea_tryon_test_filters'] = array();
			$GLOBALS['sea_tryon_test_environment'] = 'production';
		}

		public function test_wordpress_ai_does_not_read_the_legacy_plugin_secret(): void {
			$store   = new SecretMemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PROVIDER      => 'openai',
					'sea_tryon_openai_api_key'               => 'legacy-secret',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'seaai-secret',
				)
			);
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertSame( '', $secrets->get_active_api_key() );
			self::assertSame( '', $secrets->get_seaai_api_key() );
			self::assertTrue( $secrets->is_active_provider_configured() );

			$store->update( SettingsRepository::OPTION_PROVIDER, 'seaai' );
			self::assertSame( 'seaai-secret', $secrets->get_seaai_api_key() );
		}

		public function test_blank_or_masked_seaai_submission_preserves_existing_secret(): void {
			$store   = new SecretMemoryOptionsStore( array( SettingsRepository::OPTION_SEAAI_API_KEY => 'original' ) );
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertTrue( $secrets->save_seaai_api_key( '  ' ) );
			self::assertTrue( $secrets->save_seaai_api_key( SecretStore::MASK ) );
			self::assertSame( 'original', $store->get( SettingsRepository::OPTION_SEAAI_API_KEY ) );
			self::assertSame( array(), $store->updates );
		}

		public function test_saved_seaai_secret_is_sanitized_and_not_autoloaded(): void {
			$store   = new SecretMemoryOptionsStore();
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertTrue( $secrets->save_seaai_api_key( " new\r\n-key\t" ) );
			self::assertSame( 'new-key', $store->get( SettingsRepository::OPTION_SEAAI_API_KEY ) );
			self::assertFalse( $store->updates[0]['autoload'] );
		}

		public function test_seaai_filter_is_final_runtime_override(): void {
			$GLOBALS['sea_tryon_test_filters']['sea_tryon_seaai_api_key'] = static function (): string {
				return 'filtered-key';
			};
			$store   = new SecretMemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PROVIDER      => 'seaai',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'database-key',
				)
			);
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertSame( 'filtered-key', $secrets->get_active_api_key() );
		}

		public function test_mask_is_fixed_and_reveals_no_secret_fragment(): void {
			$secrets = new SecretStore( new SettingsRepository( new SecretMemoryOptionsStore() ) );

			self::assertSame( '', $secrets->mask( '' ) );
			self::assertSame( SecretStore::MASK, $secrets->mask( 'test-visible-tail' ) );
			self::assertStringNotContainsString( 'tail', $secrets->mask( 'test-visible-tail' ) );
		}

		public function test_seaai_configuration_requires_key_and_valid_url(): void {
			$store   = new SecretMemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PROVIDER => 'seaai',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'seaai-key',
					SettingsRepository::OPTION_SEAAI_BASE_URL => 'https://gateway.example/wp-json/seaai/v1/',
				)
			);
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertTrue( $secrets->is_active_provider_configured() );
			self::assertSame( 'https://gateway.example/wp-json/seaai/v1', $secrets->get_seaai_base_url() );
		}

		public function test_http_loopback_override_is_allowed_only_in_local_or_development(): void {
			$GLOBALS['sea_tryon_test_environment'] = 'local';
			$GLOBALS['sea_tryon_test_filters']['sea_tryon_seaai_base_url'] = static function (): string {
				return 'http://127.0.0.1:8080/wp-json/seaai/v1/';
			};
			$store = new SecretMemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PROVIDER      => 'seaai',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'seaai-key',
				)
			);
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			self::assertSame( 'http://127.0.0.1:8080/wp-json/seaai/v1', $secrets->get_seaai_base_url() );

			$GLOBALS['sea_tryon_test_filters']['sea_tryon_seaai_base_url'] = static function (): string {
				return 'http://127.999.999.999/wp-json/seaai/v1';
			};
			self::assertSame( '', $secrets->get_seaai_base_url() );

			$GLOBALS['sea_tryon_test_filters']['sea_tryon_seaai_base_url'] = static function (): string {
				return 'http://127.0.0.1:8080/wp-json/seaai/v1/';
			};
			$GLOBALS['sea_tryon_test_environment'] = 'production';
			self::assertSame( '', $secrets->get_seaai_base_url() );
		}

		public function test_development_filter_can_allow_test_host_but_never_production_http(): void {
			$GLOBALS['sea_tryon_test_filters']['sea_tryon_seaai_base_url'] = static function (): string {
				return 'http://private-gateway.example.test/wp-json/seaai/v1';
			};
			$store = new SecretMemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PROVIDER      => 'seaai',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'seaai-key',
				)
			);
			$secrets = new SecretStore( new SettingsRepository( $store ) );

			$GLOBALS['sea_tryon_test_environment'] = 'development';
			self::assertSame( '', $secrets->get_seaai_base_url() );

			$GLOBALS['sea_tryon_test_filters']['sea_tryon_allow_insecure_seaai_base_url'] = static function (): bool {
				return true;
			};
			self::assertSame( 'http://private-gateway.example.test/wp-json/seaai/v1', $secrets->get_seaai_base_url() );

			$GLOBALS['sea_tryon_test_environment'] = 'production';
			self::assertSame( '', $secrets->get_seaai_base_url() );
		}
	}

	final class SecretMemoryOptionsStore implements OptionsStoreInterface {

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
