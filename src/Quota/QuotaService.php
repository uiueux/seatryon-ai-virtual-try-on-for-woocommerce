<?php
/**
 * Daily dispatch quota service.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

use DateTimeImmutable;
use DateTimeZone;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionCommentThrowTag.WrongNumber,Squiz.Commenting.FunctionComment.SpacingAfterParamType,Squiz.Commenting.FunctionComment.ParamCommentFullStop

/**
 * Atomically consumes quota once for each first provider dispatch.
 */
final class QuotaService {

	private const LOCK_TTL        = 10;
	private const MAX_DAILY_LIMIT = 100000;

	/** @var QuotaStoreInterface */
	private $store;

	/** @var LockInterface */
	private $lock;

	/** @var ClockInterface */
	private $clock;

	/** @var DateTimeZone */
	private $site_timezone;

	/**
	 * @param QuotaStoreInterface $store         Quota persistence.
	 * @param LockInterface       $lock          Atomic identity lock.
	 * @param ClockInterface      $clock         Clock.
	 * @param DateTimeZone        $site_timezone WordPress site timezone.
	 */
	public function __construct( QuotaStoreInterface $store, LockInterface $lock, ClockInterface $clock, DateTimeZone $site_timezone ) {
		$this->store         = $store;
		$this->lock          = $lock;
		$this->clock         = $clock;
		$this->site_timezone = $site_timezone;
	}

	/**
	 * Consume one identity's quota immediately before the initial provider dispatch.
	 *
	 * @param QuotaIdentity $identity    User or guest identity.
	 * @param string        $dispatch_id Stable provider dispatch ID.
	 * @param int           $daily_limit Configured daily limit.
	 * @return QuotaResult Consumption result.
	 */
	public function consume_for_dispatch( QuotaIdentity $identity, string $dispatch_id, int $daily_limit ): QuotaResult {
		return $this->consume_for_dispatches( array( $identity ), $dispatch_id, $daily_limit );
	}

	/**
	 * Consume every required quota identity as one provider-dispatch decision.
	 *
	 * Guest work supplies both the anonymous session and one-way IP identities.
	 * All states are checked while their locks are held before any state is saved.
	 *
	 * @param array<int,QuotaIdentity> $identities Required identities.
	 * @param string                   $dispatch_id Stable provider dispatch ID.
	 * @param int                      $daily_limit      Configured per-identity daily limit.
	 * @param int|null                 $site_daily_limit Optional whole-site daily limit.
	 * @return QuotaResult Consumption result.
	 * @throws QuotaException When locking or persistence fails.
	 */
	public function consume_for_dispatches( array $identities, string $dispatch_id, int $daily_limit, ?int $site_daily_limit = null ): QuotaResult {
		if ( $daily_limit < 1 || $daily_limit > self::MAX_DAILY_LIMIT ) {
			throw new \InvalidArgumentException( 'Daily quota is outside the supported range.' );
		}
		if ( null !== $site_daily_limit && ( $site_daily_limit < 1 || $site_daily_limit > self::MAX_DAILY_LIMIT ) ) {
			throw new \InvalidArgumentException( 'Site daily quota is outside the supported range.' );
		}
		if ( strlen( $dispatch_id ) < 16 || strlen( $dispatch_id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $dispatch_id ) ) {
			throw new \InvalidArgumentException( 'A valid provider dispatch ID is required.' );
		}

		$identity_limits = array();
		foreach ( $identities as $identity ) {
			if ( ! $identity instanceof QuotaIdentity || $identity->is_quota_exempt() ) {
				throw new \InvalidArgumentException( 'Only limited quota identities may be consumed.' );
			}
			$limit = $identity->is_site() ? $site_daily_limit : $daily_limit;
			if ( null === $limit ) {
				throw new \InvalidArgumentException( 'A site daily quota is required for the site identity.' );
			}
			$identity_limits[ $identity->key() ] = $limit;
		}
		$identity_keys = array_keys( $identity_limits );
		sort( $identity_keys, SORT_STRING );
		if ( array() === $identity_keys ) {
			throw new \InvalidArgumentException( 'At least one quota identity is required.' );
		}

		$handles = array();
		foreach ( $identity_keys as $identity_key ) {
			$handle = $this->lock->acquire( 'quota:' . $identity_key, self::LOCK_TTL );
			if ( null === $handle ) {
				$this->release_handles( $handles );
				throw new QuotaException( 'Quota is busy. The dispatch was not charged.' );
			}
			$handles[] = $handle;
		}

		try {
			return $this->consume_under_locks( $identity_keys, $identity_limits, $dispatch_id );
		} finally {
			$this->release_handles( $handles );
		}
	}

	/**
	 * @param array<int,string> $identity_keys   Sorted identity keys.
	 * @param array<string,int> $identity_limits Configured limit by identity key.
	 * @param string            $dispatch_id     Provider dispatch ID.
	 */
	private function consume_under_locks( array $identity_keys, array $identity_limits, string $dispatch_id ): QuotaResult {
		$now       = $this->clock->now()->setTimezone( $this->site_timezone );
		$bucket    = $now->format( 'Y-m-d' );
		$resets_at = $now->setTime( 0, 0, 0 )->modify( '+1 day' );
		$states    = array();
		$changed   = array();
		$remaining = self::MAX_DAILY_LIMIT;
		$hash      = hash( 'sha256', $dispatch_id );

		foreach ( $identity_keys as $identity_key ) {
			$limit = $identity_limits[ $identity_key ];
			$state = $this->current_day_state( $this->store->load( $identity_key ), $bucket, $resets_at );
			$seen  = in_array( $hash, $state['dispatches'], true );
			if ( ! $seen && (int) $state['count'] >= $limit ) {
				return new QuotaResult( false, false, 0, $resets_at );
			}
			if ( ! $seen ) {
				$state['count']           = (int) $state['count'] + 1;
				$state['dispatches'][]    = $hash;
				$state['resets_at']       = $resets_at->getTimestamp();
				$changed[ $identity_key ] = true;
			}
			$states[ $identity_key ] = $state;
			$remaining               = min( $remaining, $limit - (int) $state['count'] );
		}

		foreach ( array_keys( $changed ) as $identity_key ) {
			if ( ! $this->store->save( $identity_key, $states[ $identity_key ] ) ) {
				throw new QuotaException( 'Quota could not be persisted. The provider must not be called.' );
			}
		}

		return new QuotaResult( true, array() !== $changed, $remaining, $resets_at );
	}

	/**
	 * @param array<string,mixed>|null $state     Stored state.
	 * @param string                    $bucket    Current site-local day.
	 * @param DateTimeImmutable         $resets_at Current reset instant.
	 * @return array<string,mixed> Current-day state.
	 */
	private function current_day_state( ?array $state, string $bucket, DateTimeImmutable $resets_at ): array {
		if ( null === $state ) {
			return $this->fresh_state( $bucket, $resets_at );
		}
		if ( ! isset( $state['bucket'] ) || ! is_string( $state['bucket'] ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $state['bucket'] ) ) {
			throw new QuotaException( 'Stored quota state is malformed.' );
		}
		if ( $bucket !== $state['bucket'] ) {
			return $this->fresh_state( $bucket, $resets_at );
		}

		$this->assert_valid_state( $state );

		return $state;
	}

	/** @return array<string,mixed> New current-day state. */
	private function fresh_state( string $bucket, DateTimeImmutable $resets_at ): array {
		return array(
			'bucket'     => $bucket,
			'count'      => 0,
			'dispatches' => array(),
			'resets_at'  => $resets_at->getTimestamp(),
		);
	}

	/** @param array<int,LockHandle> $handles Locks to release in reverse order. */
	private function release_handles( array $handles ): void {
		foreach ( array_reverse( $handles ) as $handle ) {
			$this->lock->release( $handle );
		}
	}

	/**
	 * Fail closed when current-day quota state is malformed.
	 *
	 * @param array<string,mixed> $state Stored state.
	 * @throws QuotaException When state is malformed.
	 */
	private function assert_valid_state( array $state ): void {
		if (
			! isset( $state['count'], $state['dispatches'] ) ||
			! is_int( $state['count'] ) ||
			$state['count'] < 0 ||
			$state['count'] > self::MAX_DAILY_LIMIT ||
			! is_array( $state['dispatches'] ) ||
			count( $state['dispatches'] ) !== $state['count']
		) {
			throw new QuotaException( 'Stored quota state is malformed.' );
		}

		foreach ( $state['dispatches'] as $dispatch_hash ) {
			if ( ! is_string( $dispatch_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $dispatch_hash ) ) {
				throw new QuotaException( 'Stored quota state is malformed.' );
			}
		}
	}
}
