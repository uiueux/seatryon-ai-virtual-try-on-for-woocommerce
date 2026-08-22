<?php
/**
 * Advisory create-time quota check.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use SeaTryOn\Auth\RequestIdentity;

defined( 'ABSPATH' ) || exit;

interface QuotaPreflightInterface {
	/**
	 * Fail early when today's already-persisted dispatch count is exhausted.
	 *
	 * @param RequestIdentity $identity Authenticated request identity.
	 */
	public function assert_available( RequestIdentity $identity ): void;
}
