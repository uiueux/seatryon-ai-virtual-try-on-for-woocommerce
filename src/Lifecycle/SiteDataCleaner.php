<?php
/**
 * Plugin-owned site data cleanup.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Lifecycle;

use SeaTryOn\Admin\Product\ProductFields;
use SeaTryOn\Domain\SystemClock;
use SeaTryOn\Job\JobCleanupService;
use SeaTryOn\Job\WordPressJobRepository;
use SeaTryOn\Job\WordPressJobLockFactory;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Storage\PurgeableTemporaryStorageInterface;
use SeaTryOn\Storage\WordPressTemporaryStorageFactory;
use SeaTryOn\Support\NativeFilesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Clears runtime personal data on deactivation and all owned data on uninstall.
 */
final class SiteDataCleaner {

	/**
	 * Owned option prefix lookup.
	 *
	 * @var callable
	 */
	private $find_options;

	/**
	 * Option deletion callback.
	 *
	 * @var callable
	 */
	private $delete_option;

	/**
	 * Global post-meta deletion callback.
	 *
	 * @var callable
	 */
	private $delete_post_meta;

	/**
	 * Scheduled action cancellation callback.
	 *
	 * @var callable
	 */
	private $unschedule;

	/**
	 * Private storage purge callback.
	 *
	 * @var callable
	 */
	private $purge_storage;

	/**
	 * Plugin lock-cache flush callback.
	 *
	 * @var callable
	 */
	private $flush_lock_cache;

	/**
	 * Sanitized cleanup failure reporter.
	 *
	 * @var callable
	 */
	private $report_failure;

	/**
	 * Create the cleaner.
	 *
	 * @param callable|null $find_options      Find exact option names by owned prefix.
	 * @param callable|null $delete_option     Delete one option.
	 * @param callable|null $delete_post_meta  Delete a product meta key globally.
	 * @param callable|null $unschedule        Unschedule one owned action hook.
	 * @param callable|null $purge_storage     Purge all private temporary scopes.
	 * @param callable|null $flush_lock_cache  Flush the plugin-only cache group.
	 * @param callable|null $report_failure    Report a sanitized cleanup failure.
	 */
	public function __construct(
		?callable $find_options = null,
		?callable $delete_option = null,
		?callable $delete_post_meta = null,
		?callable $unschedule = null,
		?callable $purge_storage = null,
		?callable $flush_lock_cache = null,
		?callable $report_failure = null
	) {
		$this->find_options     = $find_options ?? static function ( string $prefix ): array {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return array();
			}

			$pattern = $wpdb->esc_like( $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted core table identifier; value uses prepare().
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );

			return array_values( array_filter( $names, 'is_string' ) );
		};
		$this->delete_option    = $delete_option ?? static function ( string $name ): bool {
			return delete_option( $name );
		};
		$this->delete_post_meta = $delete_post_meta ?? static function ( string $key ): bool {
			return delete_post_meta_by_key( $key );
		};
		$this->unschedule       = $unschedule ?? static function ( string $hook ): void {
			if ( '' === $hook ) {
				if ( function_exists( 'as_unschedule_all_actions' ) ) {
					as_unschedule_all_actions( '', array(), JobScheduler::GROUP );
				}
				return;
			}

			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		};
		$this->purge_storage    = $purge_storage ?? static function (): bool {
			$storage = null;
			try {
				$clock      = new SystemClock();
				$storage    = WordPressTemporaryStorageFactory::create( new NativeFilesystem(), $clock );
				$lock       = WordPressJobLockFactory::create( $clock );
				$repository = new WordPressJobRepository( $lock );
				$cleanup    = new JobCleanupService( $repository, $clock, $storage, new JobScheduler(), null, $lock );
				$cleanup->delete_all();
				return true;
			} catch ( \Throwable $exception ) {
				if ( $storage instanceof PurgeableTemporaryStorageInterface ) {
					try {
						$storage->purge_all();
					} catch ( \Throwable $storage_exception ) {
						return false;
					}
				}
				return false;
			}
		};
		$this->flush_lock_cache = $flush_lock_cache ?? static function (): void {
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'sea-tryon-locks' );
			}
		};
		$this->report_failure   = $report_failure ?? static function (): void {
			( new Logger() )->error( 'Temporary Virtual Try-On personal data cleanup did not complete.' );
		};
	}

	/**
	 * Remove all scheduled work and temporary personal/runtime state.
	 *
	 * Merchant settings, secrets, product configuration, aggregate success count,
	 * and the data version are intentionally preserved.
	 */
	public function deactivate_site(): void {
		$this->unschedule_actions();
		$this->delete_options_by_prefixes(
			array(
				'sea_tryon_job_',
				'sea_tryon_quota_',
				'sea_tryon_lock_',
				'sea_tryon_replay_',
				'sea_tryon_success_job_',
				'_transient_sea_tryon_',
				'_transient_timeout_sea_tryon_',
				'_site_transient_sea_tryon_',
				'_site_transient_timeout_sea_tryon_',
			)
		);
		call_user_func( $this->flush_lock_cache );
		if ( ! (bool) call_user_func( $this->purge_storage ) ) {
			call_user_func( $this->report_failure );
		}
	}

	/** Remove all plugin-owned data for the current site. */
	public function uninstall_site(): void {
		$this->deactivate_site();
		$this->delete_options_by_prefixes( array( 'sea_tryon_' ) );

		foreach ( array( ProductFields::META_ENABLED, ProductFields::META_PROMPT, ProductFields::META_EXPERIENCE_TYPE, ProductFields::META_PRODUCT_IMAGE_ID ) as $meta_key ) {
			call_user_func( $this->delete_post_meta, $meta_key );
		}
	}

	/** Unschedule current and legacy cleanup hooks owned by this plugin. */
	private function unschedule_actions(): void {
		// Group-wide cancellation is required because job actions carry arguments.
		call_user_func( $this->unschedule, '' );

		foreach ( array( JobScheduler::WORK_HOOK, JobScheduler::CLEANUP_HOOK, 'sea_tryon_cleanup_expired_jobs' ) as $hook ) {
			call_user_func( $this->unschedule, $hook );
		}
	}

	/**
	 * Delete exact option names returned for fixed plugin-owned prefixes.
	 *
	 * @param array<int,string> $prefixes Fixed owned prefixes.
	 */
	private function delete_options_by_prefixes( array $prefixes ): void {
		$deleted = array();
		foreach ( $prefixes as $prefix ) {
			$names = call_user_func( $this->find_options, $prefix );
			if ( ! is_array( $names ) ) {
				continue;
			}

			foreach ( $names as $name ) {
				if ( ! is_string( $name ) || isset( $deleted[ $name ] ) || 0 !== strpos( $name, $prefix ) ) {
					continue;
				}

				call_user_func( $this->delete_option, $name );
				$deleted[ $name ] = true;
			}
		}
	}
}
