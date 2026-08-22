<?php
/**
 * Quota identity value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing

/**
 * Separates logged-in user and anonymous session quota namespaces.
 */
final class QuotaIdentity {

	private const USER      = 'user';
	private const GUEST     = 'guest';
	private const UNLIMITED = 'unlimited';

	/**
	 * Identity type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Raw identifier kept in memory only.
	 *
	 * @var string
	 */
	private $identifier;

	/** @var string|null Already one-way persisted namespace key. */
	private $persisted_key;

	/**
	 * Create a logged-in user identity.
	 *
	 * @param int  $user_id      WordPress user ID.
	 * @param bool $quota_exempt Whether this user bypasses generation quotas.
	 * @throws \InvalidArgumentException When the ID is invalid.
	 */
	public static function for_user( int $user_id, bool $quota_exempt = false ): self {
		if ( $user_id < 1 ) {
			throw new \InvalidArgumentException( 'A positive WordPress user ID is required.' );
		}

		return new self( $quota_exempt ? self::UNLIMITED : self::USER, (string) $user_id );
	}

	/**
	 * Create an anonymous high-entropy session identity.
	 *
	 * @param string $session_id Opaque guest session ID.
	 * @throws \InvalidArgumentException When the ID is invalid.
	 */
	public static function for_guest( string $session_id ): self {
		if ( strlen( $session_id ) < 32 || strlen( $session_id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $session_id ) ) {
			throw new \InvalidArgumentException( 'A valid high-entropy guest session ID is required.' );
		}

		return new self( self::GUEST, $session_id );
	}

	/** Reconstitute only an already one-way, namespaced persisted key. */
	public static function from_persisted_key( string $key ): self {
		if ( 1 !== preg_match( '/^(user|guest|unlimited)-[a-f0-9]{64}$/D', $key, $matches ) ) {
			throw new \InvalidArgumentException( 'A valid persisted quota identity key is required.' );
		}

		$identity                = new self( $matches[1], '' );
		$identity->persisted_key = $key;

		return $identity;
	}

	/** Return whether this identity represents a logged-in user. */
	public function is_user(): bool {
		return in_array( $this->type, array( self::USER, self::UNLIMITED ), true );
	}

	/** Return whether this identity bypasses the daily generation quota. */
	public function is_quota_exempt(): bool {
		return self::UNLIMITED === $this->type;
	}

	/**
	 * Return a one-way stable key; never persist the raw guest session ID.
	 */
	public function key(): string {
		if ( null !== $this->persisted_key ) {
			return $this->persisted_key;
		}
		return $this->type . '-' . hash( 'sha256', $this->identifier );
	}

	/**
	 * Internal constructor.
	 *
	 * @param string $type       Identity type.
	 * @param string $identifier Raw identifier.
	 */
	private function __construct( string $type, string $identifier ) {
		$this->type          = $type;
		$this->identifier    = $identifier;
		$this->persisted_key = null;
	}
}
