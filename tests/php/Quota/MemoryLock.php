<?php
/**
 * In-memory lock double.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Quota;

use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;

/**
 * Simple deterministic lock for quota service tests.
 */
final class MemoryLock implements LockInterface {

	/** @var bool */
	private $available = true;

	/** @var array<string,bool> */
	private $held = array();

	/** Make subsequent acquisitions available or unavailable. */
	public function set_available( bool $available ): void {
		$this->available = $available;
	}

	/** {@inheritDoc} */
	public function acquire( string $key, int $ttl ): ?LockHandle {
		unset( $ttl );
		if ( ! $this->available || isset( $this->held[ $key ] ) ) {
			return null;
		}

		$this->held[ $key ] = true;
		return new LockHandle( $key, 'test-owner', 'memory' );
	}

	/** {@inheritDoc} */
	public function release( LockHandle $handle ): bool {
		unset( $handle );
		if ( ! isset( $this->held[ $handle->key() ] ) ) {
			return false;
		}

		unset( $this->held[ $handle->key() ] );
		return true;
	}
}
