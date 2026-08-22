<?php
/**
 * Job repository maintenance contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use DateTimeImmutable;
use SeaTryOn\Contracts\JobRepositoryInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag

/** Bounded lifecycle operations intentionally kept out of the domain contract. */
interface JobRepositoryMaintenanceInterface extends JobRepositoryInterface {
	/** @return string[] Expired opaque IDs, limited by the caller. */
	public function find_expired_ids( DateTimeImmutable $now, int $limit ): array;
	/** @return string[] Opaque IDs from the bounded lifecycle index. */
	public function find_job_ids( int $limit ): array;
	/** Delete one job and its owner/idempotency pointer. */
	public function delete( string $job_id ): bool;
}
