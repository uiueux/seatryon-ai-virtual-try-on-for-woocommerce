<?php
/**
 * Runtime status contract for administrative diagnostics.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Isolates WordPress and filesystem state from diagnostic policy.
 */
interface SystemStatusInterface {

	public const STORAGE_AVAILABLE   = 'available';
	public const STORAGE_UNAVAILABLE = 'unavailable';
	public const STORAGE_PUBLIC      = 'public';

	/**
	 * Whether WooCommerce is active and loaded.
	 */
	public function is_woocommerce_active(): bool;

	/**
	 * Active WooCommerce version, if available.
	 */
	public function woocommerce_version(): string;

	/**
	 * Minimum supported WooCommerce version.
	 */
	public function minimum_woocommerce_version(): string;

	/**
	 * Return one of the STORAGE_* constants.
	 */
	public function storage_status(): string;
}
