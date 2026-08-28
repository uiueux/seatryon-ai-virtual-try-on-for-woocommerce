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
	private const GUEST_IP  = 'guest-ip';
	private const SITE      = 'site';
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

	/**
	 * Create an anonymous IP identity from an already HMAC-derived value.
	 *
	 * @param string $address_hash One-way HMAC-SHA-256 address hash.
	 */
	public static function for_guest_ip_hash( string $address_hash ): self {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $address_hash ) ) {
			throw new \InvalidArgumentException( 'A valid one-way guest IP hash is required.' );
		}

		return new self( self::GUEST_IP, $address_hash );
	}

	/** Create the single whole-site dispatch identity. */
	public static function for_site(): self {
		return new self( self::SITE, 'all-dispatches' );
	}

	/** Reconstitute only an already one-way, namespaced persisted key. */
	public static function from_persisted_key( string $key ): self {
		if ( 1 !== preg_match( '/^(user|guest|guest-ip|site|unlimited)-[a-f0-9]{64}$/D', $key, $matches ) ) {
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

	/** Whether this identity represents a one-way anonymous IP quota key. */
	public function is_guest_ip(): bool {
		return self::GUEST_IP === $this->type;
	}

	/** Whether this identity applies to every provider dispatch on the site. */
	public function is_site(): bool {
		return self::SITE === $this->type;
	}

	/**
	 * Return a one-way stable key; never persist the raw guest session ID.
	 */
	public function key(): string {
		if ( null !== $this->persisted_key ) {
			return $this->persisted_key;
		}
		return self::GUEST_IP === $this->type ? $this->type . '-' . $this->identifier : $this->type . '-' . hash( 'sha256', $this->identifier );
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
