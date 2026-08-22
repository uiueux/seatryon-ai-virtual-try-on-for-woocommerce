<?php
/**
 * Personal job lookup contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Privacy;

use SeaTryOn\Domain\Job;

defined( 'ABSPATH' ) || exit;

/**
 * Locates temporary jobs for WordPress privacy requests without raw identities.
 */
interface PersonalJobLocatorInterface {

	/**
	 * Return one stable page of a user's temporary jobs.
	 *
	 * @param int $user_id  WordPress user ID.
	 * @param int $page     One-based page.
	 * @param int $per_page Page size.
	 * @return array<int,Job>
	 */
	public function find_for_user( int $user_id, int $page, int $per_page ): array;

	/**
	 * Return every currently indexed temporary job for erasure.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int,Job>
	 */
	public function find_all_for_user( int $user_id ): array;
}
