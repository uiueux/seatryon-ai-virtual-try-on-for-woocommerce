<?php
/**
 * Daily dispatch quota service.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

use DateTimeZone;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Atomically consumes quota once for each first provider dispatch.
 */
final class QuotaService {

	private const LOCK_TTL = 10;

	/**
	 * Quota persistence.
	 *
	 * @var QuotaStoreInterface
	 */
	private $store;

	/**
	 * Atomic identity lock.
	 *
	 * @var LockInterface
	 */
	private $lock;

	/**
	 * Clock used for daily boundaries.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * WordPress site timezone.
	 *
	 * @var DateTimeZone
	 */
	private $site_timezone;

	/**
	 * Initialize the service.
	 *
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
	 * Consume one unit immediately before the initial provider dispatch.
	 *
	 * Replays using the same dispatch ID are allowed without a second charge.
	 *
	 * @param QuotaIdentity $identity    User or guest identity.
	 * @param string        $dispatch_id Stable provider dispatch ID.
	 * @param int           $daily_limit Configured daily limit.
	 * @throws \InvalidArgumentException When inputs are invalid.
	 * @throws QuotaException When locking or persistence fails.
	 */
	public function consume_for_dispatch( QuotaIdentity $identity, string $dispatch_id, int $daily_limit ): QuotaResult {
		if ( $daily_limit < 1 || $daily_limit > 100 ) {
			throw new \InvalidArgumentException( 'Daily quota must be between 1 and 100.' );
		}

		if ( strlen( $dispatch_id ) < 16 || strlen( $dispatch_id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $dispatch_id ) ) {
			throw new \InvalidArgumentException( 'A valid provider dispatch ID is required.' );
		}

		$identity_key = $identity->key();
		$handle       = $this->lock->acquire( 'quota:' . $identity_key, self::LOCK_TTL );

		if ( null === $handle ) {
			throw new QuotaException( 'Quota is busy. The dispatch was not charged.' );
		}

		try {
			return $this->consume_under_lock( $identity_key, $dispatch_id, $daily_limit );
		} finally {
			$this->lock->release( $handle );
		}
	}

	/**
	 * Perform the state transition while the identity lock is held.
	 *
	 * @param string $identity_key One-way identity key.
	 * @param string $dispatch_id Stable provider dispatch ID.
	 * @param int    $daily_limit Configured daily limit.
	 * @throws QuotaException When state is malformed or cannot be saved.
	 */
	private function consume_under_lock( string $identity_key, string $dispatch_id, int $daily_limit ): QuotaResult {
		$now       = $this->clock->now()->setTimezone( $this->site_timezone );
		$bucket    = $now->format( 'Y-m-d' );
		$resets_at = $now->setTime( 0, 0, 0 )->modify( '+1 day' );
		$state     = $this->store->load( $identity_key );

		if ( null === $state ) {
			$state = array(
				'bucket'     => $bucket,
				'count'      => 0,
				'dispatches' => array(),
				'resets_at'  => $resets_at->getTimestamp(),
			);
		} elseif ( ! isset( $state['bucket'] ) || ! is_string( $state['bucket'] ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $state['bucket'] ) ) {
			throw new QuotaException( 'Stored quota state is malformed.' );
		} elseif ( $bucket !== $state['bucket'] ) {
			$state = array(
				'bucket'     => $bucket,
				'count'      => 0,
				'dispatches' => array(),
				'resets_at'  => $resets_at->getTimestamp(),
			);
		} else {
			$this->assert_valid_state( $state );
		}

		$count         = (int) $state['count'];
		$dispatches    = $state['dispatches'];
		$dispatch_hash = hash( 'sha256', $dispatch_id );

		if ( in_array( $dispatch_hash, $dispatches, true ) ) {
			return new QuotaResult( true, false, $daily_limit - $count, $resets_at );
		}

		if ( $count >= $daily_limit ) {
			return new QuotaResult( false, false, 0, $resets_at );
		}

		$dispatches[] = $dispatch_hash;

		$state['count']      = $count + 1;
		$state['dispatches'] = $dispatches;
		$state['resets_at']  = $resets_at->getTimestamp();

		if ( ! $this->store->save( $identity_key, $state ) ) {
			throw new QuotaException( 'Quota could not be persisted. The provider must not be called.' );
		}

		return new QuotaResult( true, true, $daily_limit - (int) $state['count'], $resets_at );
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
			$state['count'] > 100 ||
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
