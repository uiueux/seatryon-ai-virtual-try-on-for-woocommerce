<?php
/**
 * Administrative health probe tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Admin\Notices;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Admin\Notices\HealthIssue;
use SeaTryOn\Admin\Notices\SystemStatusInterface;
use SeaTryOn\Admin\Notices\WordPressHealthProbe;
use SeaTryOn\Image\ValidatedImage;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Provider\WordPressAI\WordPressAIImage;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\OptionsStoreInterface;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

final class WordPressHealthProbeTest extends TestCase {

	public function test_disabled_plugin_does_not_report_provider_configuration(): void {
		$probe = $this->probe( array(), new NoticeSystemStatus() );

		self::assertSame( array(), $this->codes( $probe->issues() ) );
	}

	public function test_enabled_wordpress_ai_provider_requires_an_image_editing_connector(): void {
		$probe = $this->probe(
			array(
				SettingsRepository::OPTION_ENABLED        => 'yes',
				SettingsRepository::OPTION_PROVIDER       => 'openai',
				SettingsRepository::OPTION_SEAAI_API_KEY => 'irrelevant-secret',
			),
			new NoticeSystemStatus(),
			false
		);

		self::assertSame( array( HealthIssue::WORDPRESS_AI_UNAVAILABLE ), $this->codes( $probe->issues() ) );
	}

	public function test_enabled_seaai_provider_uses_default_url_and_requires_key(): void {
		$probe = $this->probe(
			array(
				SettingsRepository::OPTION_ENABLED  => true,
				SettingsRepository::OPTION_PROVIDER => 'seaai',
			),
			new NoticeSystemStatus()
		);

		self::assertSame( array( HealthIssue::MISSING_SEAAI_KEY ), $this->codes( $probe->issues() ) );
	}

	public function test_explicitly_empty_seaai_url_is_reported(): void {
		$probe = $this->probe(
			array(
				SettingsRepository::OPTION_ENABLED        => true,
				SettingsRepository::OPTION_PROVIDER       => 'seaai',
				SettingsRepository::OPTION_SEAAI_API_KEY  => 'server-secret',
				SettingsRepository::OPTION_SEAAI_BASE_URL => '',
			),
			new NoticeSystemStatus()
		);

		self::assertSame( array( HealthIssue::MISSING_SEAAI_URL ), $this->codes( $probe->issues() ) );
	}

	public function test_complete_selected_provider_has_no_configuration_issue(): void {
		$probe = $this->probe(
			array(
				SettingsRepository::OPTION_ENABLED        => true,
				SettingsRepository::OPTION_PROVIDER       => 'seaai',
				SettingsRepository::OPTION_SEAAI_API_KEY => 'server-secret',
				SettingsRepository::OPTION_SEAAI_BASE_URL => 'https://gateway.example/wp-json/seaai/v1',
			),
			new NoticeSystemStatus()
		);

		self::assertSame( array(), $this->codes( $probe->issues() ) );
	}

	public function test_storage_and_dependency_failures_are_reported_without_paths_or_secrets(): void {
		$system                  = new NoticeSystemStatus();
		$system->woocommerce     = false;
		$system->storage         = SystemStatusInterface::STORAGE_PUBLIC;
		$probe                   = $this->probe( array(), $system );

		self::assertSame(
			array( HealthIssue::WOOCOMMERCE_MISSING, HealthIssue::STORAGE_PUBLIC ),
			$this->codes( $probe->issues() )
		);
	}

	public function test_old_woocommerce_context_contains_only_versions(): void {
		$system              = new NoticeSystemStatus();
		$system->current     = '10.8.2';
		$system->minimum     = '10.9';
		$issues              = $this->probe( array(), $system )->issues();

		self::assertCount( 1, $issues );
		self::assertSame( HealthIssue::WOOCOMMERCE_TOO_OLD, $issues[0]->code() );
		self::assertSame( '10.8.2', $issues[0]->context( 'current' ) );
		self::assertSame( '10.9', $issues[0]->context( 'minimum' ) );
	}

	/**
	 * @param array<string,mixed> $values Option values.
	 */
	private function probe( array $values, SystemStatusInterface $system, bool $wordpress_ai_supported = true ): WordPressHealthProbe {
		$settings = new SettingsRepository( new NoticeOptionsStore( $values ) );

		return new WordPressHealthProbe( $settings, new SecretStore( $settings ), $system, new NoticeWordPressAIClient( $wordpress_ai_supported ) );
	}

	/**
	 * @param HealthIssue[] $issues Issues.
	 * @return string[]
	 */
	private function codes( array $issues ): array {
		return array_map(
			static function ( HealthIssue $issue ): string {
				return $issue->code();
			},
			$issues
		);
	}
}

final class NoticeWordPressAIClient implements WordPressAIClientInterface {
	/** @var bool */ private $supported;
	public function __construct( bool $supported ) { $this->supported = $supported; }
	public function supports_image_editing(): bool { return $this->supported; }
	/** @param ValidatedImage[] $images Images. */
	public function generate_image( string $prompt, array $images ): WordPressAIImage {
		unset( $prompt, $images );
		return new WordPressAIImage( 'image-bytes', 'image/png' );
	}
}

final class NoticeOptionsStore implements OptionsStoreInterface {

	/** @var array<string,mixed> */
	private $values;

	/** @param array<string,mixed> $values Values. */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function get( string $name, $fallback = null ) {
		return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : $fallback;
	}

	public function update( string $name, $value, bool $autoload = false ): bool {
		$this->values[ $name ] = $value;
		return true;
	}
}

final class NoticeSystemStatus implements SystemStatusInterface {

	/** @var bool */
	public $woocommerce = true;

	/** @var string */
	public $current = '10.9';

	/** @var string */
	public $minimum = '10.9';

	/** @var string */
	public $storage = self::STORAGE_AVAILABLE;

	public function is_woocommerce_active(): bool {
		return $this->woocommerce;
	}

	public function woocommerce_version(): string {
		return $this->current;
	}

	public function minimum_woocommerce_version(): string {
		return $this->minimum;
	}

	public function storage_status(): string {
		return $this->storage;
	}
}
