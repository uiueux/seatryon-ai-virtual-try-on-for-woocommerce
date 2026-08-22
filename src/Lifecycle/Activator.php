<?php
/**
 * Plugin activation lifecycle.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Performs safe, repeatable activation work.
 */
final class Activator {

	/**
	 * Activate the plugin.
	 *
	 * Defaults are added without overwriting an existing installation. No remote
	 * calls, rewrites, or long-running work are performed during activation.
	 *
	 * @param bool $network_wide Whether the plugin is network activated.
	 */
	public static function activate( bool $network_wide = false ): void {
		unset( $network_wide );

		add_option( 'sea_tryon_data_version', SEA_TRYON_VERSION, '', false );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
