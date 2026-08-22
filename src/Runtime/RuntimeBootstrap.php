<?php
/**
 * Production runtime composition root.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Runtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SeaTryOn\Auth\ActionTokenService;
use SeaTryOn\Auth\GuestActionBootstrap;
use SeaTryOn\Auth\GuestSessionManager;
use SeaTryOn\Auth\RequestAuthenticator;
use SeaTryOn\Auth\SameOriginPolicy;
use SeaTryOn\Auth\WordPressReplayStore;
use SeaTryOn\Domain\CSPRNGIdGenerator;
use SeaTryOn\Domain\JobService;
use SeaTryOn\Domain\SystemClock;
use SeaTryOn\Frontend\FrontendController;
use SeaTryOn\Frontend\WordPressFrontendConfigProvider;
use SeaTryOn\Http\ProviderTransport;
use SeaTryOn\Http\WordPressHttpClient;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Image\RemoteImageDownloader;
use SeaTryOn\Image\UrlSafetyPolicy;
use SeaTryOn\Job\JobCleanupService;
use SeaTryOn\Job\JobWorker;
use SeaTryOn\Job\ScheduledJobCreator;
use SeaTryOn\Job\WordPressJobLockFactory;
use SeaTryOn\Job\WordPressJobRepository;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Privacy\PrivacyHooks;
use SeaTryOn\Privacy\WordPressPersonalJobLocator;
use SeaTryOn\Prompt\PromptComposer;
use SeaTryOn\Provider\ProviderFactory;
use SeaTryOn\Provider\WordPressAI\WordPressAIClient;
use SeaTryOn\Quota\WordPressOptionQuotaStore;
use SeaTryOn\Quota\WordPressQuotaServiceFactory;
use SeaTryOn\Rest\GuestTokenController;
use SeaTryOn\Rest\JobsController;
use SeaTryOn\Rest\ScheduledJobApplication;
use SeaTryOn\Rest\WordPressProductContextResolver;
use SeaTryOn\Rest\WordPressQuotaPreflight;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Scheduler\SchedulerHooks;
use SeaTryOn\Security\OwnerIdentityHasher;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Storage\WordPressTemporaryStorageFactory;
use SeaTryOn\Support\NativeFilesystem;
use SeaTryOn\Upload\UploadService;
use SeaTryOn\Upload\WordPressImageProcessor;

/**
 * Builds and registers the public, asynchronous and privacy services once.
 */
final class RuntimeBootstrap {

	/**
	 * Register one shared production object graph.
	 */
	public function register(): void {
		$clock      = new SystemClock();
		$settings   = new SettingsRepository();
		$secrets    = new SecretStore( $settings );
		$filesystem = new NativeFilesystem();
		$storage    = WordPressTemporaryStorageFactory::create( $filesystem, $clock );
		$lock       = WordPressJobLockFactory::create( $clock );
		$repository = new WordPressJobRepository( $lock );
		$scheduler  = new JobScheduler();
		$counter    = new \SeaTryOn\Job\SuccessCounter();
		$replays    = new WordPressReplayStore();
		$cleanup    = new JobCleanupService( $repository, $clock, $storage, $scheduler, $counter, $lock, $replays );

		$quota_store  = new WordPressOptionQuotaStore();
		$quota        = WordPressQuotaServiceFactory::create( $quota_store, $lock, $clock );
		$http         = new WordPressHttpClient();
		$transport    = new ProviderTransport( $http );
		$images       = new ImageValidator();
		$downloader   = new RemoteImageDownloader( $http, $images, new UrlSafetyPolicy() );
		$wordpress_ai = new WordPressAIClient();
		$providers    = new ProviderFactory( $settings, $secrets, $transport, $downloader, $storage, $wordpress_ai );
		$logger       = new Logger(
			function_exists( 'wc_get_logger' ) ? wc_get_logger() : null,
			null,
			$settings->is_debug_mode()
		);

		$worker = new JobWorker(
			$repository,
			$clock,
			$lock,
			$quota,
			$settings,
			$providers,
			$scheduler,
			$storage,
			$counter,
			$logger
		);
		( new SchedulerHooks( $worker, $cleanup ) )->register();

		$sessions        = new GuestSessionManager();
		$action_tokens   = new ActionTokenService( $this->action_token_secret(), $replays );
		$guest_tokens    = new GuestActionBootstrap( $sessions, $action_tokens );
		$origins         = new SameOriginPolicy();
		$owner_hasher    = new OwnerIdentityHasher();
		$authenticator   = new RequestAuthenticator( $settings, $sessions, $action_tokens, $origins, $owner_hasher );
		$image_processor = new WordPressImageProcessor();
		$uploads         = new UploadService( $image_processor, $storage );
		$product_context = new WordPressProductContextResolver( $settings, new PromptComposer(), $image_processor, $storage, $secrets, $wordpress_ai );
		$creator         = new ScheduledJobCreator( new JobService( $repository, $clock, new CSPRNGIdGenerator() ), $scheduler, $clock );
		$application     = new ScheduledJobApplication( $creator, $repository, $cleanup );
		$preflight       = new WordPressQuotaPreflight( $quota_store, $settings, $clock, wp_timezone() );

		( new JobsController( $authenticator, $uploads, $product_context, $application, $storage, $preflight, $logger ) )->register_hooks();
		( new GuestTokenController( $settings, $sessions, $origins, $action_tokens, $logger ) )->register_hooks();

		$privacy_jobs = new WordPressPersonalJobLocator( $repository, $owner_hasher );
		( new PrivacyHooks( $privacy_jobs, $cleanup ) )->register();

		$frontend_config = new WordPressFrontendConfigProvider( $settings, $guest_tokens );
		( new FrontendController( null, null, null, null, $frontend_config ) )->register_hooks();
	}

	/**
	 * Derive a dedicated signing secret without storing another credential.
	 *
	 * @throws \RuntimeException When WordPress secret material is unavailable.
	 */
	private function action_token_secret(): string {
		$site_secret = wp_salt( 'auth' );
		if ( ! is_string( $site_secret ) || '' === $site_secret ) {
			throw new \RuntimeException( 'WordPress authentication secret material is unavailable.' );
		}

		return hash_hmac( 'sha256', 'sea-tryon|action-token|v1', $site_secret );
	}
}
