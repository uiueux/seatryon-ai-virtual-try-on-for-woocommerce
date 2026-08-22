<?php
/**
 * Atomic lock tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Support;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use SeaTryOn\Support\WordPressAtomicLock;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies backend selection, TTL and compare-and-delete ownership semantics.
 */
final class WordPressAtomicLockTest extends TestCase {

	/** Atomic option add excludes a second owner and CAS release permits reuse. */
	public function test_exclusion_and_atomic_release_with_option_backend(): void {
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$state = new stdClass();
		$lock  = $this->create_lock( false, $clock, $state );
		$first = $lock->acquire( 'quota:abc', 10 );

		self::assertNotNull( $first );
		self::assertNull( $lock->acquire( 'quota:abc', 10 ) );
		self::assertTrue( $lock->release( $first ) );
		self::assertNotNull( $lock->acquire( 'quota:abc', 10 ) );
		self::assertSame( array(), $state->cache );
	}

	/** A release CAS cannot delete a token installed during the operation. */
	public function test_old_option_owner_release_cannot_delete_interleaved_replacement(): void {
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$state = new stdClass();
		$lock  = $this->create_lock( false, $clock, $state );
		$first = $lock->acquire( 'job:release-race', 10 );

		self::assertNotNull( $first );
		$replacement = 'replacement-owner|' . ( $clock->now()->getTimestamp() + 30 );
		$state->before_cas = static function ( string $key ) use ( $state, $replacement ): void {
			$state->options[ $key ] = $replacement;
		};

		self::assertFalse( $lock->release( $first ) );
		self::assertSame( $replacement, $state->options[ $first->key() ] );
		self::assertNull( $lock->acquire( 'job:release-race', 10 ) );
	}

	/** Expired option takeover CAS cannot delete a newly interleaved owner. */
	public function test_expired_option_takeover_cannot_delete_interleaved_replacement(): void {
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$state = new stdClass();
		$lock  = $this->create_lock( false, $clock, $state );
		$first = $lock->acquire( 'job:expiry-race', 5 );

		self::assertNotNull( $first );
		$clock->set( new DateTimeImmutable( '2026-08-09T00:00:06+00:00' ) );
		$replacement = 'replacement-owner|' . ( $clock->now()->getTimestamp() + 30 );
		$state->before_cas = static function ( string $key ) use ( $state, $replacement ): void {
			$state->options[ $key ] = $replacement;
		};

		self::assertNull( $lock->acquire( 'job:expiry-race', 5 ) );
		self::assertSame( $replacement, $state->options[ $first->key() ] );
	}

	/** Cache locks are never actively deleted and the backend owns expiration. */
	public function test_cache_release_and_expiry_never_delete_a_replacement(): void {
		$clock = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$state = new stdClass();
		$lock  = $this->create_lock( true, $clock, $state );
		$first = $lock->acquire( 'job:cache', 5 );

		self::assertNotNull( $first );
		self::assertFalse( $lock->release( $first ) );
		self::assertNull( $lock->acquire( 'job:cache', 5 ) );

		$clock->set( new DateTimeImmutable( '2026-08-09T00:00:06+00:00' ) );
		$replacement = $lock->acquire( 'job:cache', 5 );

		self::assertNotNull( $replacement );
		self::assertFalse( $lock->release( $first ) );
		self::assertSame( $replacement->value(), $state->cache[ $replacement->key() ]['value'] );
		self::assertNull( $lock->acquire( 'job:cache', 5 ) );
		self::assertSame( array(), $state->options );
	}

	/**
	 * Build a lock with deterministic in-memory WordPress function doubles.
	 *
	 * @param bool         $persistent Whether the cache backend is selected.
	 * @param MutableClock $clock      Shared deterministic clock.
	 * @param stdClass     $state      Mutable backend state.
	 */
	private function create_lock( bool $persistent, MutableClock $clock, stdClass $state ): WordPressAtomicLock {
		$state->cache      = array();
		$state->options    = array();
		$state->before_cas = null;

		$option_add = static function ( string $key, string $value ) use ( $state ): bool {
			if ( array_key_exists( $key, $state->options ) ) {
				return false;
			}

			$state->options[ $key ] = $value;
			return true;
		};

		return new WordPressAtomicLock(
			$clock,
			static function () use ( $persistent ): bool {
				return $persistent;
			},
			static function ( string $key, string $value, int $ttl ) use ( $state, $clock ): bool {
				if ( isset( $state->cache[ $key ] ) && $state->cache[ $key ]['expires_at'] <= $clock->now()->getTimestamp() ) {
					unset( $state->cache[ $key ] );
				}

				if ( array_key_exists( $key, $state->cache ) ) {
					return false;
				}

				$state->cache[ $key ] = array(
					'value'      => $value,
					'expires_at' => $clock->now()->getTimestamp() + $ttl,
				);
				return true;
			},
			$option_add,
			static function ( string $key ) use ( $state ) {
				return $state->options[ $key ] ?? null;
			},
			static function ( string $key, string $expected ) use ( $state ): bool {
				if ( is_callable( $state->before_cas ) ) {
					$interleave        = $state->before_cas;
					$state->before_cas = null;
					$interleave( $key );
				}

				if ( ! isset( $state->options[ $key ] ) || ! hash_equals( $expected, $state->options[ $key ] ) ) {
					return false;
				}

				unset( $state->options[ $key ] );
				return true;
			}
		);
	}
}
