<?php
/**
 * WordPress dependency notice registry adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

use SeaTryOn\Dependencies;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the existing bootstrap notice through the public Hooks API.
 */
final class WordPressDependencyNoticeRegistry implements DependencyNoticeRegistryInterface {

	/** {@inheritDoc} */
	public function is_registered(): bool {
		$callback = array( Dependencies::class, 'render_admin_notice' );

		return false !== has_action( 'admin_notices', $callback )
			|| false !== has_action( 'network_admin_notices', $callback );
	}
}
