<?php
/**
 * Atomic lock handle.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Support;

defined( 'ABSPATH' ) || exit;

/**
 * An ownership token returned by an acquired lock.
 */
final class LockHandle {

	/**
	 * Normalized storage key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Opaque ownership value.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Selected backend.
	 *
	 * @var string
	 */
	private $backend;

	/**
	 * Initialize an acquired lock handle.
	 *
	 * @param string $key     Normalized storage key.
	 * @param string $value   Opaque owner-and-expiry value.
	 * @param string $backend Backend selected for this lock.
	 */
	public function __construct( string $key, string $value, string $backend ) {
		$this->key     = $key;
		$this->value   = $value;
		$this->backend = $backend;
	}

	/** Get the normalized key. */
	public function key(): string {
		return $this->key;
	}

	/** Get the opaque ownership value. */
	public function value(): string {
		return $this->value;
	}

	/** Get the backend used to acquire the lock. */
	public function backend(): string {
		return $this->backend;
	}
}
