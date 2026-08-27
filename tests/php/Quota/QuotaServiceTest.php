<?php
/**
 * Quota service tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Quota;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Quota\QuotaException;
use SeaTryOn\Quota\QuotaIdentity;
use SeaTryOn\Quota\QuotaService;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies identity isolation, daily boundaries and dispatch idempotency.
 */
final class QuotaServiceTest extends TestCase {

	/** A replayed dispatch is allowed but never charged twice. */
	public function test_dispatch_is_charged_exactly_once_and_limit_is_enforced(): void {
		$store   = new MemoryQuotaStore();
		$service = $this->service( $store );
		$identity = QuotaIdentity::for_user( 42 );

		$first  = $service->consume_for_dispatch( $identity, 'dispatch_00000001', 2 );
		$replay = $service->consume_for_dispatch( $identity, 'dispatch_00000001', 2 );
		$second = $service->consume_for_dispatch( $identity, 'dispatch_00000002', 2 );
		$third  = $service->consume_for_dispatch( $identity, 'dispatch_00000003', 2 );

		self::assertTrue( $first->is_allowed() );
		self::assertTrue( $first->was_consumed() );
		self::assertSame( 1, $first->remaining() );
		self::assertTrue( $replay->is_allowed() );
		self::assertFalse( $replay->was_consumed() );
		self::assertSame( 1, $replay->remaining() );
		self::assertTrue( $second->is_allowed() );
		self::assertFalse( $third->is_allowed() );
		self::assertSame( 0, $third->remaining() );
	}

	/** Site-local midnight, not UTC midnight, selects and resets the bucket. */
	public function test_site_timezone_controls_day_bucket_and_reset(): void {
		$store = new MemoryQuotaStore();
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T15:59:59+00:00' ) );
		$service = new QuotaService( $store, new MemoryLock(), $clock, new DateTimeZone( 'Asia/Shanghai' ) );
		$identity = QuotaIdentity::for_user( 7 );

		$first = $service->consume_for_dispatch( $identity, 'dispatch_before_midnight', 1 );
		self::assertSame( '2026-08-10T00:00:00+08:00', $first->resets_at()->format( DATE_ATOM ) );

		$clock->set( new DateTimeImmutable( '2026-08-09T16:00:00+00:00' ) );
		$next_day = $service->consume_for_dispatch( $identity, 'dispatch_after_midnight', 1 );

		self::assertTrue( $next_day->is_allowed() );
		self::assertTrue( $next_day->was_consumed() );
		self::assertSame( '2026-08-11T00:00:00+08:00', $next_day->resets_at()->format( DATE_ATOM ) );
	}

	/** Logged-in and guest identities occupy separate one-way namespaces. */
	public function test_user_and_guest_identities_are_isolated_and_guest_value_is_hashed(): void {
		$guest_session = 'guest_session_0123456789abcdef0123456789abcdef';
		$user          = QuotaIdentity::for_user( 12 );
		$unlimited     = QuotaIdentity::for_user( 12, true );
		$guest         = QuotaIdentity::for_guest( $guest_session );

		self::assertNotSame( $user->key(), $guest->key() );
		self::assertNotSame( $user->key(), $unlimited->key() );
		self::assertStringNotContainsString( $guest_session, $guest->key() );
		self::assertTrue( $user->is_user() );
		self::assertTrue( $unlimited->is_user() );
		self::assertTrue( $unlimited->is_quota_exempt() );
		self::assertTrue( QuotaIdentity::from_persisted_key( $unlimited->key() )->is_quota_exempt() );
		self::assertFalse( $guest->is_user() );
		self::assertFalse( $guest->is_quota_exempt() );
	}

	/** Lock contention fails before any store write or provider authorization. */
	public function test_lock_contention_fails_without_charging(): void {
		$store = new MemoryQuotaStore();
		$lock  = new MemoryLock();
		$lock->set_available( false );
		$service = new QuotaService(
			$store,
			$lock,
			new MutableClock( new DateTimeImmutable( '2026-08-09T12:00:00+00:00' ) ),
			new DateTimeZone( 'UTC' )
		);

		$this->expectException( QuotaException::class );
		$service->consume_for_dispatch( QuotaIdentity::for_user( 1 ), 'dispatch_00000001', 3 );
	}

	/** Persistence failure fails closed so a provider caller cannot proceed. */
	public function test_persistence_failure_fails_closed(): void {
		$store = new MemoryQuotaStore();
		$store->fail_saves( true );

		$this->expectException( QuotaException::class );
		$this->service( $store )->consume_for_dispatch( QuotaIdentity::for_user( 1 ), 'dispatch_00000001', 3 );
	}

	/** Malformed current-day data cannot silently reset and bypass quota. */
	public function test_malformed_current_bucket_fails_closed(): void {
		$store    = new MemoryQuotaStore();
		$identity = QuotaIdentity::for_user( 1 );
		$store->set(
			$identity->key(),
			array(
				'bucket'     => '2026-08-09',
				'count'      => 2,
				'dispatches' => array(),
			)
		);

		$this->expectException( QuotaException::class );
		$this->service( $store )->consume_for_dispatch( $identity, 'dispatch_00000001', 3 );
	}

	/** A changed guest cookie cannot bypass the daily limit for the same IP. */
	public function test_guest_session_and_ip_quotas_must_both_allow_dispatch(): void {
		$store   = new MemoryQuotaStore();
		$service = $this->service( $store );
		$session = QuotaIdentity::for_guest( str_repeat( 'A', 43 ) );
		$ip      = QuotaIdentity::for_guest_ip_hash( hash_hmac( 'sha256', '203.0.113.10', 'test-secret' ) );

		$first = $service->consume_for_dispatches( array( $session, $ip ), 'guest_dispatch_0001', 1 );
		self::assertTrue( $first->is_allowed() );
		self::assertSame( 1, $store->count_for( $session->key() ) );
		self::assertSame( 1, $store->count_for( $ip->key() ) );

		$replacement_session = QuotaIdentity::for_guest( str_repeat( 'B', 43 ) );
		$blocked = $service->consume_for_dispatches( array( $replacement_session, $ip ), 'guest_dispatch_0002', 1 );
		self::assertFalse( $blocked->is_allowed() );
		self::assertSame( 0, $store->count_for( $replacement_session->key() ) );

		$other_ip = QuotaIdentity::for_guest_ip_hash( hash_hmac( 'sha256', '203.0.113.11', 'test-secret' ) );
		$second = $service->consume_for_dispatches( array( $replacement_session, $other_ip ), 'guest_dispatch_0003', 1 );
		self::assertTrue( $second->is_allowed() );
	}
	/** Build a UTC service at a deterministic instant. */
	private function service( MemoryQuotaStore $store ): QuotaService {
		return new QuotaService(
			$store,
			new MemoryLock(),
			new MutableClock( new DateTimeImmutable( '2026-08-09T12:00:00+00:00' ) ),
			new DateTimeZone( 'UTC' )
		);
	}
}
