<?php
/**
 * WordPress option quota store.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps only the current day per identity, preventing daily option buildup.
 */
final class WordPressOptionQuotaStore implements QuotaStoreInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $identity_key One-way identity key.
	 * @throws QuotaException When stored state is malformed.
	 */
	public function load( string $identity_key ): ?array {
		$value = get_option( $this->option_name( $identity_key ), null );

		if ( null === $value ) {
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
	 */
	public function save( string $identity_key, array $state ): bool {
		$name = $this->option_name( $identity_key );

		if ( null === get_option( $name, null ) ) {
			return add_option( $name, $state, '', false );
		}

		return update_option( $name, $state, false );
	}

	/**
	 * Build a bounded option name without exposing the identity.
	 *
	 * @param string $identity_key One-way identity key.
	 */
	private function option_name( string $identity_key ): string {
		return 'sea_tryon_quota_' . substr( hash( 'sha256', $identity_key ), 0, 40 );
	}
}
