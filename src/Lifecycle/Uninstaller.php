<?php
/**
 * Network-aware plugin uninstall coordinator.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Runs destructive plugin-owned cleanup only from the uninstall entrypoint.
 */
final class Uninstaller {

	/** Remove all plugin-owned data on every affected site. */
	public static function uninstall(): void {
		self::for_each_site(
			static function (): void {
				( new SiteDataCleaner() )->uninstall_site();
			},
			true
		);
	}

	/**
	 * Run deactivation cleanup while preserving merchant configuration.
	 *
	 * @param bool $network_wide Whether this is a network deactivation.
	 */
	public static function deactivate( bool $network_wide ): void {
		self::for_each_site(
			static function (): void {
				( new SiteDataCleaner() )->deactivate_site();
			},
			$network_wide
		);
	}

	/**
	 * Run a callback in the current site or across the network.
	 *
	 * @param callable $callback     Site cleanup callback.
	 * @param bool     $network_wide Whether all sites are affected.
	 */
	private static function for_each_site( callable $callback, bool $network_wide ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			call_user_func( $callback );
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				call_user_func( $callback );
			} finally {
				restore_current_blog();
			}
		}
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
