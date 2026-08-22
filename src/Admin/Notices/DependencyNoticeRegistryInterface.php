<?php
/**
 * Existing dependency notice registry contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Allows the renderer to avoid duplicating the bootstrap dependency notice.
 */
interface DependencyNoticeRegistryInterface {

	/**
	 * Whether Dependencies already owns the WooCommerce notice.
	 */
	public function is_registered(): bool;
}
