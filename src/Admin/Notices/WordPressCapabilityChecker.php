<?php
/**
 * WordPress capability checker for administrative notices.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Uses public WordPress capabilities appropriate to each remediation.
 */
final class WordPressCapabilityChecker implements CapabilityCheckerInterface {

	/**
	 * Determine whether the current user may remediate an issue.
	 *
	 * @param HealthIssue $issue Health issue.
	 */
	public function can_view( HealthIssue $issue ): bool {
		if ( HealthIssue::AUDIENCE_PLUGIN_MANAGER === $issue->audience() ) {
			return current_user_can( 'activate_plugins' ) || current_user_can( 'manage_network_plugins' );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this public capability.
		return current_user_can( 'manage_woocommerce' );
	}
}
