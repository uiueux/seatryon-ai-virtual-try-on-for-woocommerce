<?php
/**
 * Settings repository tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Settings;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Settings\OptionsStoreInterface;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

final class SettingsRepositoryTest extends TestCase {

	public function test_frozen_mvp_defaults_are_typed(): void {
		$settings = new SettingsRepository( new MemoryOptionsStore() );

		self::assertFalse( $settings->is_enabled() );
		self::assertSame( 'openai', $settings->get_provider() );
		self::assertSame( SettingsRepository::DEFAULT_SEAAI_BASE_URL, $settings->get_stored_seaai_base_url() );
		self::assertFalse( $settings->allow_guests() );
		self::assertSame( 3, $settings->get_logged_in_daily_limit() );
		self::assertSame( 3, $settings->get_guest_daily_limit() );
		self::assertSame( 'low', $settings->get_seaai_quality() );
		self::assertFalse( $settings->is_debug_mode() );
		self::assertSame( 0, $settings->get_success_count() );
		self::assertFalse( $settings->trigger_uses_auto_width() );
		self::assertTrue( $settings->trigger_shows_icon() );
		self::assertSame( SettingsRepository::DEFAULT_TRIGGER_FONT_SIZE, $settings->get_trigger_font_size() );
		self::assertSame(
			array(
				'text'             => SettingsRepository::DEFAULT_TRIGGER_TEXT_COLOR,
				'background'       => SettingsRepository::DEFAULT_TRIGGER_BACKGROUND_COLOR,
				'border'           => SettingsRepository::DEFAULT_TRIGGER_BORDER_COLOR,
				'hover_text'       => SettingsRepository::DEFAULT_TRIGGER_HOVER_TEXT_COLOR,
				'hover_background' => SettingsRepository::DEFAULT_TRIGGER_HOVER_BACKGROUND_COLOR,
				'hover_border'     => SettingsRepository::DEFAULT_TRIGGER_HOVER_BORDER_COLOR,
			),
			$settings->get_trigger_colors()
		);
		self::assertSame(
			array(
				'desktop' => SettingsRepository::DEFAULT_TRIGGER_DESKTOP_HEIGHT,
				'mobile'  => SettingsRepository::DEFAULT_TRIGGER_MOBILE_HEIGHT,
			),
			$settings->get_trigger_heights()
		);
		self::assertSame(
			array(
				'width'  => SettingsRepository::DEFAULT_TRIGGER_BORDER_WIDTH,
				'radius' => SettingsRepository::DEFAULT_TRIGGER_BORDER_RADIUS,
			),
			$settings->get_trigger_border_dimensions()
		);
		self::assertSame(
			array(
				'accent'            => SettingsRepository::DEFAULT_PANEL_ACCENT_COLOR,
				'border'            => SettingsRepository::DEFAULT_PANEL_BORDER_COLOR,
				'surface'           => SettingsRepository::DEFAULT_PANEL_SURFACE_COLOR,
				'upload_background' => SettingsRepository::DEFAULT_PANEL_UPLOAD_BACKGROUND_COLOR,
				'text'              => SettingsRepository::DEFAULT_PANEL_TEXT_COLOR,
				'muted'             => SettingsRepository::DEFAULT_PANEL_MUTED_COLOR,
				'error'             => SettingsRepository::DEFAULT_PANEL_ERROR_COLOR,
				'radius'            => SettingsRepository::DEFAULT_PANEL_RADIUS,
			),
			$settings->get_panel_appearance()
		);
	}

	public function test_values_are_normalized_and_bounded(): void {
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array(
					SettingsRepository::OPTION_ENABLED    => 'yes',
					SettingsRepository::OPTION_PROVIDER   => 'SEAAI',
					SettingsRepository::OPTION_ALLOW_GUESTS => 'off',
					SettingsRepository::OPTION_LOGGED_IN_DAILY_LIMIT => 999,
					SettingsRepository::OPTION_GUEST_DAILY_LIMIT => -5,
					SettingsRepository::OPTION_SEAAI_QUALITY => 'HIGH',
					SettingsRepository::OPTION_DEBUG_MODE => 1,
					SettingsRepository::OPTION_SUCCESS_COUNT => -10,
					SettingsRepository::OPTION_TRIGGER_AUTO_WIDTH => 'yes',
					SettingsRepository::OPTION_TRIGGER_SHOW_ICON => 'no',
					SettingsRepository::OPTION_TRIGGER_FONT_SIZE => 999,
				)
			)
		);

		self::assertTrue( $settings->is_enabled() );
		self::assertSame( 'seaai', $settings->get_provider() );
		self::assertFalse( $settings->allow_guests() );
		self::assertSame( 100, $settings->get_logged_in_daily_limit() );
		self::assertSame( 1, $settings->get_guest_daily_limit() );
		self::assertSame( 'high', $settings->get_seaai_quality() );
		self::assertTrue( $settings->is_debug_mode() );
		self::assertSame( 0, $settings->get_success_count() );
		self::assertTrue( $settings->trigger_uses_auto_width() );
		self::assertFalse( $settings->trigger_shows_icon() );
		self::assertSame( SettingsRepository::MAX_TRIGGER_FONT_SIZE, $settings->get_trigger_font_size() );
	}

	public function test_trigger_colors_are_normalized_and_invalid_values_use_defaults(): void {
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array(
					SettingsRepository::OPTION_TRIGGER_TEXT_COLOR             => ' #ABC ',
					SettingsRepository::OPTION_TRIGGER_BACKGROUND_COLOR       => '#123456',
					SettingsRepository::OPTION_TRIGGER_BORDER_COLOR           => 'red',
					SettingsRepository::OPTION_TRIGGER_HOVER_TEXT_COLOR       => '',
					SettingsRepository::OPTION_TRIGGER_HOVER_BACKGROUND_COLOR => '#FEDCBA',
					SettingsRepository::OPTION_TRIGGER_HOVER_BORDER_COLOR     => '#000',
				)
			)
		);

		self::assertSame(
			array(
				'text'             => '#abc',
				'background'       => '#123456',
				'border'           => SettingsRepository::DEFAULT_TRIGGER_BORDER_COLOR,
				'hover_text'       => SettingsRepository::DEFAULT_TRIGGER_HOVER_TEXT_COLOR,
				'hover_background' => '#fedcba',
				'hover_border'     => '#000',
			),
			$settings->get_trigger_colors()
		);
	}

	public function test_trigger_heights_are_bounded_and_invalid_values_use_defaults(): void {
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array(
					SettingsRepository::OPTION_TRIGGER_DESKTOP_HEIGHT => 999,
					SettingsRepository::OPTION_TRIGGER_MOBILE_HEIGHT  => 'invalid',
				)
			)
		);

		self::assertSame(
			array(
				'desktop' => SettingsRepository::MAX_TRIGGER_HEIGHT,
				'mobile'  => SettingsRepository::DEFAULT_TRIGGER_MOBILE_HEIGHT,
			),
			$settings->get_trigger_heights()
		);
	}

	public function test_trigger_border_dimensions_are_bounded_and_invalid_values_use_defaults(): void {
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array(
					SettingsRepository::OPTION_TRIGGER_BORDER_WIDTH  => 999,
					SettingsRepository::OPTION_TRIGGER_BORDER_RADIUS => 'invalid',
				)
			)
		);

		self::assertSame(
			array(
				'width'  => SettingsRepository::MAX_TRIGGER_BORDER_WIDTH,
				'radius' => SettingsRepository::DEFAULT_TRIGGER_BORDER_RADIUS,
			),
			$settings->get_trigger_border_dimensions()
		);
	}

	public function test_panel_appearance_is_normalized_and_bounded(): void {
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array(
					SettingsRepository::OPTION_PANEL_ACCENT_COLOR  => ' #ABC ',
					SettingsRepository::OPTION_PANEL_BORDER_COLOR  => '#123456',
					SettingsRepository::OPTION_PANEL_SURFACE_COLOR => 'white',
					SettingsRepository::OPTION_PANEL_UPLOAD_BACKGROUND_COLOR => '#AABBCC',
					SettingsRepository::OPTION_PANEL_TEXT_COLOR    => '#222',
					SettingsRepository::OPTION_PANEL_MUTED_COLOR   => '#FEDCBA',
					SettingsRepository::OPTION_PANEL_ERROR_COLOR   => '',
					SettingsRepository::OPTION_PANEL_RADIUS        => 999,
				)
			)
		);

		self::assertSame(
			array(
				'accent'            => '#abc',
				'border'            => '#123456',
				'surface'           => SettingsRepository::DEFAULT_PANEL_SURFACE_COLOR,
				'upload_background' => '#aabbcc',
				'text'              => '#222',
				'muted'             => '#fedcba',
				'error'             => SettingsRepository::DEFAULT_PANEL_ERROR_COLOR,
				'radius'            => SettingsRepository::MAX_PANEL_RADIUS,
			),
			$settings->get_panel_appearance()
		);
	}

	public function test_provider_and_url_fail_closed(): void {
		$store    = new MemoryOptionsStore(
			array(
				SettingsRepository::OPTION_PROVIDER       => 'invented',
				SettingsRepository::OPTION_SEAAI_BASE_URL => 'file:///etc/passwd',
			)
		);
		$settings = new SettingsRepository( $store );

		self::assertSame( 'openai', $settings->get_provider() );
		self::assertSame( '', $settings->get_stored_seaai_base_url() );

		$store->update( SettingsRepository::OPTION_SEAAI_BASE_URL, 'http://localhost/wp-json/seaai/v1' );
		self::assertSame( '', $settings->get_stored_seaai_base_url() );

		$store->update( SettingsRepository::OPTION_SEAAI_BASE_URL, 'http://gateway.example/wp-json/seaai/v1' );
		self::assertSame( '', $settings->get_stored_seaai_base_url() );

		$store->update( SettingsRepository::OPTION_SEAAI_BASE_URL, 'https://gateway.example/wp-json/seaai/v1/' );
		self::assertSame( 'https://gateway.example/wp-json/seaai/v1', $settings->get_stored_seaai_base_url() );
	}

	public function test_loopback_wordpress_site_can_read_saved_loopback_gateway(): void {
		$validator = new SeaAIBaseUrlValidator(
			static function (): string {
				return 'production';
			},
			static function (): string {
				return 'http://localhost/wp/';
			}
		);
		$settings = new SettingsRepository(
			new MemoryOptionsStore(
				array( SettingsRepository::OPTION_SEAAI_BASE_URL => 'http://localhost/wp/wp-json/seaai/v1/' )
			),
			$validator
		);

		self::assertSame( 'http://localhost/wp/wp-json/seaai/v1', $settings->get_stored_seaai_base_url() );
	}
}

final class MemoryOptionsStore implements OptionsStoreInterface {

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
		$this->values[ $name ] = $value;
		return true;
	}
}
