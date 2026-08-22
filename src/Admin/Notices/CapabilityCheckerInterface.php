<?php
/**
 * Administrative notice capability contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps authorization testable without WordPress globals.
 */
interface CapabilityCheckerInterface {

	/**
	 * Whether the current user may see the issue.
	 *
	 * @param HealthIssue $issue Health issue.
	 */
	public function can_view( HealthIssue $issue ): bool;
}
