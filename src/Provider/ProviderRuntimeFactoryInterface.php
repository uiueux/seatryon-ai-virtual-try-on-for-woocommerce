<?php
/**
 * Provider runtime factory contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider;

use SeaTryOn\Domain\Job;

defined( 'ABSPATH' ) || exit;

/** Creates one selected runtime for a persisted job. */
interface ProviderRuntimeFactoryInterface {
	/**
	 * Create the selected provider and its job-derived request.
	 *
	 * @param Job $job Persisted job.
	 */
	public function create_for_job( Job $job ): ProviderRuntime;
}
