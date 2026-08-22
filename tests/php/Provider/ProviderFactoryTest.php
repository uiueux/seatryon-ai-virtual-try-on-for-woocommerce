<?php
/**
 * Provider factory tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Provider;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\Job;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Http\HttpResponse;
use SeaTryOn\Http\ProviderTransport;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\ValidatedImage;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Provider\ProviderFactory;
use SeaTryOn\Provider\SeaAI\SeaAIProvider;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Provider\WordPressAI\WordPressAIImage;
use SeaTryOn\Provider\WordPressAI\WordPressAIProvider;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\OptionsStoreInterface;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Tests\Http\RecordingHttpClient;

defined( 'ABSPATH' ) || exit;

final class ProviderFactoryTest extends TestCase {
	public function test_wordpress_ai_uses_site_connector_without_reading_plugin_secrets(): void {
		$options = new FactoryOptions(
			array(
				SettingsRepository::OPTION_PROVIDER => 'openai',
			)
		);
		$runtime = $this->factory( $options )->create_for_job( $this->job( 'openai' ) );

		self::assertInstanceOf( WordPressAIProvider::class, $runtime->provider() );
		self::assertSame( 'auto', $runtime->request()->size() );
		self::assertSame( 'auto', $runtime->request()->quality() );
		self::assertNotContains( SettingsRepository::OPTION_SEAAI_API_KEY, $options->reads );
		self::assertNotContains( SettingsRepository::OPTION_SEAAI_BASE_URL, $options->reads );
	}

	public function test_seaai_reads_only_seaai_secret_and_uses_auto_size(): void {
		$options = new FactoryOptions(
			array(
				SettingsRepository::OPTION_PROVIDER      => 'seaai',
				SettingsRepository::OPTION_SEAAI_API_KEY => implode( '-', array( 'sk', 'test-seaai-key' ) ),
			)
		);
		$runtime = $this->factory( $options )->create_for_job( $this->job( 'seaai' ) );

		self::assertInstanceOf( SeaAIProvider::class, $runtime->provider() );
		self::assertSame( 'auto', $runtime->request()->size() );
		self::assertContains( SettingsRepository::OPTION_SEAAI_API_KEY, $options->reads );
		self::assertContains( SettingsRepository::OPTION_SEAAI_BASE_URL, $options->reads );
	}

	private function factory( FactoryOptions $options ): ProviderFactory {
		$settings = new SettingsRepository( $options );
		$client = new RecordingHttpClient( new HttpResponse( 500, array(), '{}' ) );
		$storage = new FactoryStorage();

		return new ProviderFactory(
			$settings,
			new SecretStore( $settings ),
			new ProviderTransport( $client ),
			new RemoteImageDownloader( $client, new ImageValidator( 1024, 64, 4096, false ) ),
			$storage,
			new FactoryWordPressAIClient()
		);
	}

	private function job( string $provider ): Job {
		$request = new CreateJobRequest(
			hash( 'sha256', 'owner' ),
			'idempotency-key-1',
			10,
			null,
			$provider,
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			'Keep the product visually accurate.',
			str_repeat( 'a', 32 ) . '/customer.png',
			str_repeat( 'a', 32 ) . '/product.png'
		);

		return Job::create( str_repeat( 'b', 32 ), hash( 'sha256', 'idempotency-key-1' ), $request, new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ), new DateTimeImmutable( '2026-08-10T00:00:00+00:00' ) );
	}
}

final class FactoryWordPressAIClient implements WordPressAIClientInterface {
	public function supports_image_editing(): bool { return true; }
	/** @param ValidatedImage[] $images Images. */
	public function generate_image( string $prompt, array $images ): WordPressAIImage {
		unset( $prompt, $images );
		return new WordPressAIImage( 'image-bytes', 'image/png' );
	}
}

final class FactoryOptions implements OptionsStoreInterface {
	/** @var array<string,mixed> */ private $values;
	/** @var string[] */ public $reads = array();
	/** @param array<string,mixed> $values Values. */
	public function __construct( array $values ) { $this->values = $values; }
	public function get( string $name, $fallback = null ) { $this->reads[] = $name; return $this->values[ $name ] ?? $fallback; }
	public function update( string $name, $value, bool $autoload = false ): bool { $this->values[ $name ] = $value; return true; }
}

final class FactoryStorage implements TemporaryStorageInterface {
	public function create_scope(): string { return str_repeat( 'a', 32 ); }
	public function write( string $scope_id, string $role, string $contents, string $extension ): string { return $scope_id . '/' . $role . '.' . $extension; }
	public function read( string $storage_id ): string { return ''; }
	public function absolute_path( string $storage_id ): string { return $storage_id; }
	public function delete( string $storage_id ): bool { return true; }
	public function delete_scope( string $scope_id ): bool { return true; }
	public function cleanup_expired(): int { return 0; }
	public function root_path(): string { return 'private'; }
}
