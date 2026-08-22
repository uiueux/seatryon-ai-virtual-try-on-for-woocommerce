<?php
/**
 * WooCommerce settings page tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Admin\Settings {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Admin\Settings\SettingsPage;
	use SeaTryOn\Security\SecretStore;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;

	defined( 'ABSPATH' ) || exit;

	final class SettingsPageTest extends TestCase {

		public function test_section_is_added_without_removing_existing_sections(): void {
			$page     = $this->page();
			$sections = $page->add_section( array( '' => 'General' ) );

			self::assertSame( 'General', $sections[''] );
			self::assertSame( 'Virtual Try-On', $sections[ SettingsPage::SECTION_ID ] );
		}

		public function test_unrelated_product_section_is_unchanged(): void {
			$existing = array( array( 'id' => 'existing', 'type' => 'text' ) );

			self::assertSame( $existing, $this->page()->get_settings( $existing, 'inventory' ) );
		}

		public function test_expected_fields_and_frozen_defaults_are_exposed(): void {
			$fields = $this->index_by_id( $this->page()->build_settings() );

			self::assertSame( 'no', $fields[ SettingsRepository::OPTION_ENABLED ]['default'] );
			self::assertSame( 'openai', $fields[ SettingsRepository::OPTION_PROVIDER ]['default'] );
			self::assertArrayNotHasKey( 'sea_tryon_openai_quality', $fields );
			self::assertSame( SettingsRepository::DEFAULT_SEAAI_BASE_URL, $fields[ SettingsRepository::OPTION_SEAAI_BASE_URL ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_SEAAI_BASE_URL, $fields[ SettingsRepository::OPTION_SEAAI_BASE_URL ]['placeholder'] );
			self::assertSame( 'low', $fields[ SettingsRepository::OPTION_SEAAI_QUALITY ]['default'] );
			self::assertSame( 'no', $fields[ SettingsRepository::OPTION_ALLOW_GUESTS ]['default'] );
			self::assertSame( '3', $fields[ SettingsRepository::OPTION_LOGGED_IN_DAILY_LIMIT ]['default'] );
			self::assertSame( '3', $fields[ SettingsRepository::OPTION_GUEST_DAILY_LIMIT ]['default'] );
			self::assertSame( 'no', $fields[ SettingsRepository::OPTION_DEBUG_MODE ]['default'] );
			self::assertSame( 'no', $fields[ SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH ]['default'] );
			self::assertSame( 'checkbox', $fields[ SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH ]['type'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH ]['autoload'] );
			self::assertSame( 'yes', $fields[ SettingsRepository::OPTION_TRIGGER_SHOW_ICON ]['default'] );
			self::assertSame( 'checkbox', $fields[ SettingsRepository::OPTION_TRIGGER_SHOW_ICON ]['type'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_SHOW_ICON ]['autoload'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_TRIGGER_FONT_SIZE, $fields[ SettingsRepository::OPTION_TRIGGER_FONT_SIZE ]['default'] );
			self::assertSame( (string) SettingsRepository::MIN_TRIGGER_FONT_SIZE, $fields[ SettingsRepository::OPTION_TRIGGER_FONT_SIZE ]['custom_attributes']['min'] );
			self::assertSame( (string) SettingsRepository::MAX_TRIGGER_FONT_SIZE, $fields[ SettingsRepository::OPTION_TRIGGER_FONT_SIZE ]['custom_attributes']['max'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_FONT_SIZE ]['autoload'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_TEXT_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_TEXT_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_BACKGROUND_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_BACKGROUND_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_BORDER_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_HOVER_TEXT_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_HOVER_TEXT_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_HOVER_BACKGROUND_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_TRIGGER_HOVER_BORDER_COLOR, $fields[ SettingsRepository::OPTION_TRIGGER_HOVER_BORDER_COLOR ]['default'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_TRIGGER_DESKTOP_HEIGHT, $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['default'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_TRIGGER_MOBILE_HEIGHT, $fields[ SettingsRepository::OPTION_TRIGGER_MOBILE_HEIGHT ]['default'] );
			self::assertSame( 'number', $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['type'] );
			self::assertSame( (string) SettingsRepository::MIN_TRIGGER_HEIGHT, $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['custom_attributes']['min'] );
			self::assertSame( (string) SettingsRepository::MAX_TRIGGER_HEIGHT, $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['custom_attributes']['max'] );
			self::assertSame( '1', $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['custom_attributes']['step'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT ]['autoload'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_TRIGGER_BORDER_WIDTH, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH ]['default'] );
			self::assertSame( (string) SettingsRepository::MIN_TRIGGER_BORDER_WIDTH, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH ]['custom_attributes']['min'] );
			self::assertSame( (string) SettingsRepository::MAX_TRIGGER_BORDER_WIDTH, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH ]['custom_attributes']['max'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_TRIGGER_BORDER_RADIUS, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS ]['default'] );
			self::assertSame( (string) SettingsRepository::MIN_TRIGGER_BORDER_RADIUS, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS ]['custom_attributes']['min'] );
			self::assertSame( (string) SettingsRepository::MAX_TRIGGER_BORDER_RADIUS, $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS ]['custom_attributes']['max'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH ]['autoload'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS ]['autoload'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_ACCENT_COLOR, $fields[ SettingsRepository::OPTION_PANEL_ACCENT_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_BORDER_COLOR, $fields[ SettingsRepository::OPTION_PANEL_BORDER_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_SURFACE_COLOR, $fields[ SettingsRepository::OPTION_PANEL_SURFACE_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_UPLOAD_BACKGROUND_COLOR, $fields[ SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR ]['default'] );
			self::assertSame( 'color', $fields[ SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR ]['type'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR ]['autoload'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_TEXT_COLOR, $fields[ SettingsRepository::OPTION_PANEL_TEXT_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_MUTED_COLOR, $fields[ SettingsRepository::OPTION_PANEL_MUTED_COLOR ]['default'] );
			self::assertSame( SettingsRepository::DEFAULT_PANEL_ERROR_COLOR, $fields[ SettingsRepository::OPTION_PANEL_ERROR_COLOR ]['default'] );
			self::assertSame( (string) SettingsRepository::DEFAULT_PANEL_RADIUS, $fields[ SettingsRepository::OPTION_PANEL_RADIUS ]['default'] );
			self::assertSame( (string) SettingsRepository::MIN_PANEL_RADIUS, $fields[ SettingsRepository::OPTION_PANEL_RADIUS ]['custom_attributes']['min'] );
			self::assertSame( (string) SettingsRepository::MAX_PANEL_RADIUS, $fields[ SettingsRepository::OPTION_PANEL_RADIUS ]['custom_attributes']['max'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_PANEL_RADIUS ]['autoload'] );
			self::assertSame( 'color', $fields[ SettingsRepository::OPTION_TRIGGER_TEXT_COLOR ]['type'] );
			self::assertFalse( $fields[ SettingsRepository::OPTION_TRIGGER_TEXT_COLOR ]['autoload'] );
			self::assertFalse( $fields['sea_tryon_statistics_display']['is_option'] );
		}

		public function test_secret_fields_show_only_fixed_mask_and_disable_autoload(): void {
			$store = new SettingsPageMemoryStore(
				array(
					'sea_tryon_openai_api_key'               => 'test-never-render-this',
					SettingsRepository::OPTION_SEAAI_API_KEY => 'sea-never-render-this',
				)
			);
			$fields = $this->index_by_id( $this->page( $store )->build_settings() );

			self::assertArrayNotHasKey( 'sea_tryon_openai_api_key', $fields );
			self::assertSame( SecretStore::MASK, $fields[ SettingsRepository::OPTION_SEAAI_API_KEY ]['value'] );
			self::assertSame( 'true', $fields[ SettingsRepository::OPTION_SEAAI_API_KEY ]['custom_attributes']['data-sea-tryon-seaai-key'] );
			self::assertStringNotContainsString( 'never-render', serialize( $fields ) );
		}

		public function test_provider_fields_support_selectwoo_change_events(): void {
			$method = new \ReflectionMethod( SettingsPage::class, 'conditional_fields_script' );
			$method->setAccessible( true );
			$script = (string) $method->invoke( $this->page() );

			self::assertStringContainsString( "provider.addEventListener( 'change', update )", $script );
			self::assertStringContainsString( "window.jQuery( provider ).on( 'change.seaTryOn', update )", $script );
			self::assertStringNotContainsString( 'sea-tryon-provider-openai', $script );
		}

		/** @param array<int,array<string,mixed>> $fields Fields. @return array<string,array<string,mixed>> */
		private function index_by_id( array $fields ): array {
			$indexed = array();
			foreach ( $fields as $field ) {
				if ( isset( $field['id'] ) ) {
					$indexed[ $field['id'] ] = $field;
				}
			}
			return $indexed;
		}

		private function page( ?SettingsPageMemoryStore $store = null ): SettingsPage {
			$repository = new SettingsRepository( $store ?? new SettingsPageMemoryStore() );
			$secrets    = new SecretStore( $repository );

			return new SettingsPage( $repository, $secrets );
		}
	}

	final class SettingsPageMemoryStore implements OptionsStoreInterface {

		/** @var array<string,mixed> */
		private $values;

		/** @param array<string,mixed> $values Initial options. */
		public function __construct( array $values = array() ) {
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
