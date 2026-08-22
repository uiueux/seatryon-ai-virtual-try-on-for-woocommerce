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

	/** @var bool */
	private $held = false;

	/** Make subsequent acquisitions available or unavailable. */
	public function set_available( bool $available ): void {
		$this->available = $available;
	}

	/** {@inheritDoc} */
	public function acquire( string $key, int $ttl ): ?LockHandle {
		unset( $ttl );
		if ( ! $this->available || $this->held ) {
			return null;
		}

		$this->held = true;
		return new LockHandle( $key, 'test-owner', 'memory' );
	}

	/** {@inheritDoc} */
	public function release( LockHandle $handle ): bool {
		unset( $handle );
		if ( ! $this->held ) {
			return false;
		}

		$this->held = false;
		return true;
	}
}
