<?php
/**
 * Selected provider factory.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider;

use RuntimeException;
use SeaTryOn\Domain\Job;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\Http\ProviderTransport;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Provider\SeaAI\SeaAIProvider;
use SeaTryOn\Provider\WordPressAI\WordPressAIClient;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Provider\WordPressAI\WordPressAIProvider;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

/** Constructs only the selected provider and never reads the inactive secret. */
final class ProviderFactory implements ProviderRuntimeFactoryInterface {
	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Selected-secret boundary.
	 *
	 * @var SecretStore
	 */
	private $secrets;

	/**
	 * Provider transport.
	 *
	 * @var ProviderTransport
	 */
	private $transport;

	/**
	 * Safe downloader.
	 *
	 * @var RemoteImageDownloader
	 */
	private $downloader;

	/**
	 * Private storage.
	 *
	 * @var TemporaryStorageInterface
	 */
	private $storage;

	/**
	 * WordPress site-level AI provider adapter.
	 *
	 * @var WordPressAIClientInterface
	 */
	private $wordpress_ai;

	/**
	 * Configure selected-provider dependencies.
	 *
	 * @param SettingsRepository              $settings   Settings.
	 * @param SecretStore                     $secrets    Selected-secret boundary.
	 * @param ProviderTransport               $transport  Provider transport.
	 * @param RemoteImageDownloader           $downloader Safe downloader.
	 * @param TemporaryStorageInterface       $storage      Private storage.
	 * @param WordPressAIClientInterface|null $wordpress_ai WordPress AI Client adapter.
	 */
	public function __construct( SettingsRepository $settings, SecretStore $secrets, ProviderTransport $transport, RemoteImageDownloader $downloader, TemporaryStorageInterface $storage, ?WordPressAIClientInterface $wordpress_ai = null ) {
		$this->settings     = $settings;
		$this->secrets      = $secrets;
		$this->transport    = $transport;
		$this->downloader   = $downloader;
		$this->storage      = $storage;
		$this->wordpress_ai = $wordpress_ai ?? new WordPressAIClient();
	}

	/**
	 * Build only the provider frozen into the job and currently selected.
	 *
	 * @param Job $job Persisted job.
	 * @throws RuntimeException When selection changed or active configuration is incomplete.
	 */
	public function create_for_job( Job $job ): ProviderRuntime {
		$selected = $this->settings->get_provider();
		if ( $selected !== $job->provider() ) {
			throw new RuntimeException( 'The job provider is no longer selected.' );
		}

		if ( SettingsRepository::PROVIDER_SEAAI === $selected ) {
			$key = $this->secrets->get_seaai_api_key();
			$url = $this->secrets->get_seaai_base_url();
			if ( '' === $key || '' === $url ) {
				throw new RuntimeException( 'The selected SeaAI provider is not configured.' );
			}
			$provider = new SeaAIProvider( $this->transport, $this->downloader, $this->storage, $url, $key );
			$quality  = $this->settings->get_seaai_quality();
			$size     = 'auto';
		} else {
			if ( ! $this->wordpress_ai->supports_image_editing() ) {
				throw new RuntimeException( 'No configured WordPress AI connector supports image editing.' );
			}
			$provider = new WordPressAIProvider( $this->storage, $this->wordpress_ai );
			$quality  = 'auto';
			$size     = 'auto';
		}

		$request = new ProviderRequest(
			$job->id(),
			$job->customer_image_reference(),
			$job->product_image_reference(),
			$job->prompt(),
			$job->experience_type(),
			$quality,
			$size
		);

		return new ProviderRuntime( $provider, $request );
	}
}
