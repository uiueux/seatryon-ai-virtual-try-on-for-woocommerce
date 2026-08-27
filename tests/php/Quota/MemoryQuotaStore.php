<?php
/**
 * In-memory quota store.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Quota;

use SeaTryOn\Quota\QuotaStoreInterface;

/**
 * Deterministic quota persistence double.
 */
final class MemoryQuotaStore implements QuotaStoreInterface {

	/** @var array<string,array<string,mixed>> */
	private $states = array();

	/** @var bool */
	private $fail_saves = false;

	/** {@inheritDoc} */
	public function load( string $identity_key ): ?array {
		return $this->states[ $identity_key ] ?? null;
	}

	/** {@inheritDoc} */
	public function save( string $identity_key, array $state ): bool {
		if ( $this->fail_saves ) {
			return false;
		}

		$this->states[ $identity_key ] = $state;
		return true;
	}

	/** Force raw state for malformed-state tests. */
	public function set( string $identity_key, array $state ): void {
		$this->states[ $identity_key ] = $state;
	}

	/** Return the current count for an identity. */
	public function count_for( string $identity_key ): int {
		return isset( $this->states[ $identity_key ] ) ? (int) $this->states[ $identity_key ]['count'] : 0;
	}

	/** Toggle write failure. */
	public function fail_saves( bool $fail ): void {
		$this->fail_saves = $fail;
	}
}
