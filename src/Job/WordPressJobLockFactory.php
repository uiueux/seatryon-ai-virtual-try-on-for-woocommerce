<?php
/**
 * Release-capable WordPress job lock factory.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\LockInterface;
use SeaTryOn\Support\WordPressAtomicLock;

defined( 'ABSPATH' ) || exit;

/** Forces the compare-and-delete option backend for long provider work. */
final class WordPressJobLockFactory {
	/**
	 * Create a release-capable lock for repository and worker coordination.
	 *
	 * Persistent object-cache locks intentionally cannot be actively released by
	 * WordPressAtomicLock. A 300-second worker cache lock would suppress a valid
	 * retry, so job services must use its atomic option CAS backend explicitly.
	 *
	 * @param ClockInterface $clock Clock.
	 */
	public static function create( ClockInterface $clock ): LockInterface {
		return new WordPressAtomicLock(
			$clock,
			static function (): bool {
				return false;
			}
		);
	}
}
