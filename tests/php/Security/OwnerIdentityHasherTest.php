<?php
/**
 * Owner identity hasher tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Security;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Security\OwnerIdentityHasher;

/**
 * Verifies one-way, domain-separated identity derivation.
 */
final class OwnerIdentityHasherTest extends TestCase {

	/** Verify stable hashes and user/guest namespace separation. */
	public function test_hashes_are_stable_and_namespace_separated(): void {
		$hasher = new OwnerIdentityHasher(
			static function (): string {
				return 'site-secret';
			}
		);

		self::assertSame( $hasher->for_user_id( 42 ), $hasher->for_user_id( 42 ) );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $hasher->for_user_id( 42 ) );
		self::assertNotSame( $hasher->for_user_id( 42 ), $hasher->for_guest_session( str_repeat( 'A', 42 ) ) );
		self::assertNotSame(
			( new OwnerIdentityHasher(
				static function (): string {
					return 'other-site-secret'; }
			) )->for_user_id( 42 ),
			$hasher->for_user_id( 42 )
		);
	}

	/** Verify invalid raw identities and missing secret material fail closed. */
	public function test_invalid_inputs_and_missing_secret_fail_closed(): void {
		$hasher = new OwnerIdentityHasher(
			static function (): string {
				return 'site-secret';
			}
		);

		try {
			$hasher->for_user_id( 0 );
			self::fail( 'Invalid user ID was accepted.' );
		} catch ( \InvalidArgumentException $exception ) {
			self::assertSame( 'A positive WordPress user ID is required.', $exception->getMessage() );
		}

		try {
			$hasher->for_guest_session( '../short' );
			self::fail( 'Invalid guest session was accepted.' );
		} catch ( \InvalidArgumentException $exception ) {
			self::assertSame( 'A valid high-entropy guest session ID is required.', $exception->getMessage() );
		}

		$this->expectException( \RuntimeException::class );
		( new OwnerIdentityHasher(
			static function (): string {
				return '';
			}
		) )->for_user_id( 1 );
	}
}
