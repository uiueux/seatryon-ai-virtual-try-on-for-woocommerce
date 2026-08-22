<?php
/**
 * Plugin deactivation lifecycle.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Stops plugin-owned background work without deleting merchant settings.
 */
final class Deactivator {
	/**
	 * Deactivate the plugin.
	 *
	 * @param bool $network_wide Whether this is a network deactivation.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		Uninstaller::deactivate( $network_wide );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
