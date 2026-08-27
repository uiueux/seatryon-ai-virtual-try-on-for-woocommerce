<?php
/**
 * Authorized REST request identity.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use SeaTryOn\Quota\QuotaIdentity;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop

/** Keeps raw guest session material in memory only. */
final class RequestIdentity {

	/** @var int|null */
	private $user_id;

	/** @var string|null */
	private $guest_session_id;

	/** @var string */
	private $owner_hash;

	/** @var QuotaIdentity */
	private $quota_identity;

	/** @var QuotaIdentity|null */
	private $guest_ip_quota_identity;

	/** @param int|null $user_id WordPress user ID. @param string|null $guest_session_id Guest session. @param string $owner_hash Shared owner hasher output. @param bool $quota_exempt Whether this user bypasses quotas. @param string|null $guest_ip_quota_identity_key One-way guest IP quota key. */
	public function __construct( ?int $user_id, ?string $guest_session_id, string $owner_hash, bool $quota_exempt = false, ?string $guest_ip_quota_identity_key = null ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $owner_hash ) || ( null === $user_id ) === ( null === $guest_session_id ) || ( $quota_exempt && null === $user_id ) || ( null !== $guest_ip_quota_identity_key && null === $guest_session_id ) ) {
			throw new \InvalidArgumentException( 'Exactly one request identity and a valid owner hash are required.' );
		}

		$this->quota_identity          = null !== $user_id ? QuotaIdentity::for_user( $user_id, $quota_exempt ) : QuotaIdentity::for_guest( (string) $guest_session_id );
		$this->guest_ip_quota_identity = null;
		if ( null !== $guest_ip_quota_identity_key ) {
			$identity = QuotaIdentity::from_persisted_key( $guest_ip_quota_identity_key );
			if ( ! $identity->is_guest_ip() ) {
				throw new \InvalidArgumentException( 'A valid guest IP quota identity is required.' );
			}
			$this->guest_ip_quota_identity = $identity;
		}
		$this->user_id          = $user_id;
		$this->guest_session_id = $guest_session_id;
		$this->owner_hash       = $owner_hash;
	}

	/** Whether WordPress authenticated this request. */
	public function is_logged_in(): bool {
		return null !== $this->user_id;
	}

	/** Stable one-way job ownership hash. */
	public function owner_hash(): string {
		return $this->owner_hash;
	}

	/** One-way namespace-safe quota identity key. */
	public function quota_identity_key(): string {
		return $this->quota_identity->key();
	}

	/** @return array<int,string> One-way quota keys required for this request. */
	public function quota_identity_keys(): array {
		$keys = array( $this->quota_identity->key() );
		if ( null !== $this->guest_ip_quota_identity ) {
			$keys[] = $this->guest_ip_quota_identity->key();
		}

		return $keys;
	}

	/** Return the optional one-way guest-IP quota key. */
	public function guest_ip_quota_identity_key(): ?string {
		return null === $this->guest_ip_quota_identity ? null : $this->guest_ip_quota_identity->key();
	}

	/** Whether this request identity bypasses the daily generation quota. */
	public function is_quota_exempt(): bool {
		return $this->quota_identity->is_quota_exempt();
	}

	/** Raw guest session for short-lived token verification only. */
	public function guest_session_id(): ?string {
		return $this->guest_session_id;
	}
}
