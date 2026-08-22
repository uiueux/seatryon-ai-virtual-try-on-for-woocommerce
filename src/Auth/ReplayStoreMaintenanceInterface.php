<?php
/**
 * Replay-store maintenance contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Adds bounded expiry cleanup to the request-time replay barrier.
 */
interface ReplayStoreMaintenanceInterface extends ReplayStoreInterface {

	/**
	 * Remove expired markers without touching concurrently replaced values.
	 *
	 * @param int $limit Maximum records to inspect.
	 */
	public function cleanup_expired( int $limit = 100 ): int;
}
