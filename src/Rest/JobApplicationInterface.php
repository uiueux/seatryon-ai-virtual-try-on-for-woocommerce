<?php
/**
 * REST-facing job application boundary.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use SeaTryOn\Domain\Job;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

interface JobApplicationInterface {
	public function create( CreateJobCommand $command ): Job;
	public function find( string $job_id ): ?Job;
	public function delete( string $job_id ): bool;
}
