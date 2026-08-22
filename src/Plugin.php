<?php
/**
 * Main plugin coordinator.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn;

use SeaTryOn\Admin\Notices\NoticeRenderer;
use SeaTryOn\Admin\Notices\WordPressCapabilityChecker;
use SeaTryOn\Admin\Notices\WordPressDependencyNoticeRegistry;
use SeaTryOn\Admin\Notices\WordPressHealthProbe;
use SeaTryOn\Admin\Notices\WordPressSystemStatus;
use SeaTryOn\Admin\Product\ProductFields;
use SeaTryOn\Admin\Settings\SeaAIConnectionController;
use SeaTryOn\Admin\Settings\SeaAIConnectionTester;
use SeaTryOn\Admin\Settings\SettingsPage;
use SeaTryOn\Http\WordPressHttpClient;
use SeaTryOn\Domain\SystemClock;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Runtime\RuntimeBootstrap;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;
use SeaTryOn\Support\NativeFilesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin only after its runtime dependencies are available.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the plugin has already been booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the shared plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin services.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		if ( ! Dependencies::is_satisfied() ) {
			Dependencies::register_admin_notices();
			return;
		}

		try {
			( new RuntimeBootstrap() )->register();
		} catch ( \Throwable $exception ) {
			// Fail closed: do not expose the customer UI or REST routes when the
			// private runtime cannot be constructed. The health notice remains.
			( new Logger() )->error( 'The Virtual Try-On runtime could not be initialized.' );
			unset( $exception );
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			$this->register_admin_services();
		}

		/**
		 * Fires after Sea Try-On has verified its runtime dependencies.
		 *
		 * @param Plugin $plugin Main plugin instance.
		 */
		do_action( 'sea_tryon_loaded', $this );
	}

	/**
	 * Register merchant settings, product fields, and diagnostic notices.
	 */
	private function register_admin_services(): void {
		$settings = new SettingsRepository();
		$secrets  = new SecretStore( $settings );

		( new SettingsPage( $settings, $secrets ) )->register_hooks();
		( new SeaAIConnectionController( new SeaAIConnectionTester( $secrets, null, new WordPressHttpClient( null, true ) ) ) )->register_hooks();
		( new ProductFields() )->register_hooks();

		$system_status = new WordPressSystemStatus( new NativeFilesystem(), new SystemClock() );
		$health_probe  = new WordPressHealthProbe( $settings, $secrets, $system_status );
		$notices       = new NoticeRenderer(
			$health_probe,
			new WordPressCapabilityChecker(),
			new WordPressDependencyNoticeRegistry()
		);
		$notices->register();
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning the singleton.
	 */
	private function __clone() {
	}
}
