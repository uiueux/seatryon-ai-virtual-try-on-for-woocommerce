<?php
/**
 * Statistics reset tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( string $capability ): bool {
			return 'manage_woocommerce' === $capability && ! empty( $GLOBALS['sea_tryon_test_can_manage_woocommerce'] );
		}
	}

	if ( ! function_exists( 'check_admin_referer' ) ) {
		function check_admin_referer( string $action ): int {
			$GLOBALS['sea_tryon_test_checked_nonce'] = $action;
			return 1;
		}
	}

	if ( ! function_exists( 'wp_die' ) ) {
		function wp_die( $message = '', $title = '', $args = array() ): void {
			unset( $title, $args );
			throw new \RuntimeException( (string) $message );
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( string $text, string $domain = 'default' ): string {
			unset( $domain );
			return $text;
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '' ): string {
			return 'https://shop.example/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( $key, $value = null, $url = null ): string {
			if ( is_array( $key ) ) {
				$query = $key;
				$base  = (string) $value;
			} else {
				$query = array( (string) $key => $value );
				$base  = (string) $url;
			}
			return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . http_build_query( $query );
		}
	}
}

namespace SeaTryOn\Tests\Admin\Settings {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Admin\Settings\StatisticsController;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;

	defined( 'ABSPATH' ) || exit;

	final class StatisticsControllerTest extends TestCase {

		protected function tearDown(): void {
			$GLOBALS['sea_tryon_test_can_manage_woocommerce'] = false;
			$GLOBALS['sea_tryon_test_checked_nonce']          = '';
		}

		public function test_authorized_reset_checks_nonce_updates_only_count_and_returns_safe_url(): void {
			$GLOBALS['sea_tryon_test_can_manage_woocommerce'] = true;
			$store      = new StatisticsMemoryStore(
				array(
					SettingsRepository::OPTION_SUCCESS_COUNT => 42,
					SettingsRepository::OPTION_PROVIDER      => 'seaai',
				)
			);
			$controller = new StatisticsController( new SettingsRepository( $store ) );

			$url = $controller->process_reset();

			self::assertSame( StatisticsController::NONCE_ACTION, $GLOBALS['sea_tryon_test_checked_nonce'] );
			self::assertSame( SettingsRepository::OPTION_SUCCESS_COUNT, $store->updates[0]['name'] );
			self::assertSame( 0, $store->updates[0]['value'] );
			self::assertFalse( $store->updates[0]['autoload'] );
			self::assertSame( 'seaai', $store->get( SettingsRepository::OPTION_PROVIDER ) );
			self::assertStringContainsString( 'page=wc-settings', $url );
			self::assertStringContainsString( 'section=sea_tryon', $url );
		}

		public function test_unauthorized_reset_is_rejected_before_nonce_or_update(): void {
			$store      = new StatisticsMemoryStore( array( SettingsRepository::OPTION_SUCCESS_COUNT => 42 ) );
			$controller = new StatisticsController( new SettingsRepository( $store ) );

			$this->expectException( \RuntimeException::class );
			try {
				$controller->process_reset();
			} finally {
				self::assertSame( array(), $store->updates );
				self::assertSame( '', $GLOBALS['sea_tryon_test_checked_nonce'] );
			}
		}
	}

	final class StatisticsMemoryStore implements OptionsStoreInterface {

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
