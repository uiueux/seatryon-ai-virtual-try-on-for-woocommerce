<?php
/**
 * One-way job owner identity hashing.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Derives stable, namespace-separated owner hashes without persisting raw IDs.
 */
final class OwnerIdentityHasher {

	/**
	 * Secret resolver.
	 *
	 * @var callable
	 */
	private $secret_resolver;

	/**
	 * Create the hasher.
	 *
	 * @param callable|null $secret_resolver Returns site authentication secret material.
	 */
	public function __construct( ?callable $secret_resolver = null ) {
		$this->secret_resolver = $secret_resolver ?? static function (): string {
			return function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : '';
		};
	}

	/**
	 * Derive a logged-in owner hash.
	 *
	 * @param int $user_id WordPress user ID.
	 * @throws \InvalidArgumentException When the user ID is invalid.
	 */
	public function for_user_id( int $user_id ): string {
		if ( $user_id < 1 ) {
			throw new \InvalidArgumentException( 'A positive WordPress user ID is required.' );
		}

		return $this->hash( 'user|' . $user_id );
	}

	/**
	 * Derive a guest owner hash.
	 *
	 * @param string $session_id Opaque high-entropy guest session ID.
	 * @throws \InvalidArgumentException When the guest session is invalid.
	 */
	public function for_guest_session( string $session_id ): string {
		if ( strlen( $session_id ) < 32 || strlen( $session_id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $session_id ) ) {
			throw new \InvalidArgumentException( 'A valid high-entropy guest session ID is required.' );
		}

		return $this->hash( 'guest|' . $session_id );
	}

	/**
	 * Apply the versioned owner namespace and site secret.
	 *
	 * @param string $identity Namespaced raw identity held in memory only.
	 * @throws \RuntimeException When site secret material is unavailable.
	 */
	private function hash( string $identity ): string {
		$secret = call_user_func( $this->secret_resolver );
		if ( ! is_string( $secret ) || '' === $secret ) {
			throw new \RuntimeException( 'WordPress authentication secret material is unavailable.' );
		}

		return hash_hmac( 'sha256', 'sea-tryon|owner|v1|' . $identity, $secret );
	}
}
