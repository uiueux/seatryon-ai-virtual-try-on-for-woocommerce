<?php
/**
 * Atomic lock contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates short critical sections across requests.
 */
interface LockInterface {

	/**
	 * Acquire a lock, or return null when another owner holds it.
	 *
	 * @param string $key Logical lock key.
	 * @param int    $ttl Time to live in seconds.
	 */
	public function acquire( string $key, int $ttl ): ?LockHandle;

	/**
	 * Release only the exact lock represented by the handle.
	 *
	 * @param LockHandle $handle Acquired lock handle.
	 */
	public function release( LockHandle $handle ): bool;
}
