<?php
/**
 * Guest IP quota identity resolver tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Auth {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $message, string $domain ): string {
			unset( $domain );

			return $message;
		}
	}
}

namespace SeaTryOn\Tests\Auth {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Auth\AuthException;
	use SeaTryOn\Auth\GuestIpQuotaIdentityResolver;

	defined( 'ABSPATH' ) || exit;

	final class GuestIpQuotaIdentityResolverTest extends TestCase {

		public function test_resolves_a_one_way_ip_quota_identity(): void {
			$address  = '203.0.113.20';
			$secret   = 'resolver-test-secret';
			$resolver = new GuestIpQuotaIdentityResolver(
				static function () use ( $address ): string {
					return $address;
				},
				static function () use ( $secret ): string {
					return $secret;
				}
			);

			$identity = $resolver->resolve();
			self::assertSame( 'guest-ip-' . hash_hmac( 'sha256', $address, $secret ), $identity->key() );
			self::assertTrue( $identity->is_guest_ip() );
			self::assertStringNotContainsString( $address, $identity->key() );
		}

		public function test_rejects_an_invalid_web_server_address(): void {
			$resolver = new GuestIpQuotaIdentityResolver(
				static function (): string {
					return 'not-an-ip';
				},
				static function (): string {
					return 'resolver-test-secret';
				}
			);

			$this->expectException( AuthException::class );
			$resolver->resolve();
		}
	}
}
