<?php
/**
 * Quota persistence contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

defined( 'ABSPATH' ) || exit;

/**
 * Persists the current daily state for one quota identity.
 */
interface QuotaStoreInterface {

	/**
	 * Load raw state, or null when no state exists.
	 *
	 * @param string $identity_key One-way identity key.
	 * @return array<string,mixed>|null
	 */
	public function load( string $identity_key ): ?array;

	/**
	 * Persist raw state without autoloading it on normal page requests.
	 *
	 * @param string              $identity_key One-way identity key.
	 * @param array<string,mixed> $state        State to persist.
	 */
	public function save( string $identity_key, array $state ): bool;
}
