<?php
/**
 * WordPress transient quota store.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps only the active site-local day for each anonymous or user quota identity.
 */
final class WordPressOptionQuotaStore implements QuotaStoreInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $identity_key One-way identity key.
	 * @throws QuotaException When stored state is malformed.
	 */
	public function load( string $identity_key ): ?array {
		$name  = $this->option_name( $identity_key );
		$value = get_transient( $name );

		// Read a legacy option once so an active quota is not bypassed.
		if ( false === $value ) {
			$value = get_option( $name, null );
		}
		if ( null === $value || false === $value ) {
			return null;
		}
		if ( ! is_array( $value ) ) {
			throw new QuotaException( 'Stored quota state is malformed.' );
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string              $identity_key One-way identity key.
	 * @param array<string,mixed> $state        State to persist.
	 * @throws QuotaException When the reset time is malformed.
	 */
	public function save( string $identity_key, array $state ): bool {
		if ( ! isset( $state['resets_at'] ) || ! is_int( $state['resets_at'] ) ) {
			throw new QuotaException( 'Quota state is missing its reset time.' );
		}

		$name  = $this->option_name( $identity_key );
		$ttl   = max( 1, $state['resets_at'] - time() );
		$saved = set_transient( $name, $state, $ttl );
		if ( $saved ) {
			delete_option( $name );
		}

		return $saved;
	}

	/**
	 * Build a bounded key without exposing the identity.
	 *
	 * @param string $identity_key One-way identity key.
	 */
	private function option_name( string $identity_key ): string {
		return 'sea_tryon_quota_' . substr( hash( 'sha256', $identity_key ), 0, 40 );
	}
}
