<?php
/**
 * Administrative health probe contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Makes diagnostics replaceable in unit tests.
 */
interface HealthProbeInterface {

	/**
	 * Inspect the runtime without exposing credentials.
	 *
	 * @return HealthIssue[]
	 */
	public function issues(): array;
}
