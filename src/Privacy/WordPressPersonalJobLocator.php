<?php
/**
 * WordPress-backed personal job lookup.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Privacy;

use SeaTryOn\Domain\Job;
use SeaTryOn\Job\JobRepositoryMaintenanceInterface;
use SeaTryOn\Quota\QuotaIdentity;
use SeaTryOn\Security\OwnerIdentityHasher;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the repository's bounded opaque index and compares one-way identities.
 */
final class WordPressPersonalJobLocator implements PersonalJobLocatorInterface {

	private const MAX_INDEX_SIZE = 5000;

	/**
	 * Job repository.
	 *
	 * @var JobRepositoryMaintenanceInterface
	 */
	private $repository;

	/**
	 * Shared owner identity hasher.
	 *
	 * @var OwnerIdentityHasher
	 */
	private $owner_hasher;

	/**
	 * Create the locator.
	 *
	 * @param JobRepositoryMaintenanceInterface $repository   Job repository.
	 * @param OwnerIdentityHasher               $owner_hasher Shared owner identity hasher.
	 */
	public function __construct( JobRepositoryMaintenanceInterface $repository, OwnerIdentityHasher $owner_hasher ) {
		$this->repository   = $repository;
		$this->owner_hasher = $owner_hasher;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $user_id  WordPress user ID.
	 * @param int $page     One-based page.
	 * @param int $per_page Page size.
	 * @throws \InvalidArgumentException When pagination is invalid.
	 */
	public function find_for_user( int $user_id, int $page, int $per_page ): array {
		if ( $page < 1 || $per_page < 1 || $per_page > 100 ) {
			throw new \InvalidArgumentException( 'Privacy job pagination is invalid.' );
		}

		$matches = $this->find_all_for_user( $user_id );

		return array_values( array_slice( $matches, ( $page - 1 ) * $per_page, $per_page ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function find_all_for_user( int $user_id ): array {
		$owner_hash = $this->owner_hasher->for_user_id( $user_id );
		$quota_keys = array(
			QuotaIdentity::for_user( $user_id )->key(),
			QuotaIdentity::for_user( $user_id, true )->key(),
		);
		$matches    = array();

		foreach ( $this->repository->find_job_ids( self::MAX_INDEX_SIZE ) as $job_id ) {
			$job = $this->repository->find_by_id( $job_id );
			if ( ! $job instanceof Job ) {
				continue;
			}

			if ( hash_equals( $owner_hash, $job->owner_hash() ) && in_array( $job->quota_identity_key(), $quota_keys, true ) ) {
				$matches[] = $job;
			}
		}

		return $matches;
	}
}
