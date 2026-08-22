<?php
/**
 * URL safety policy tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Image;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Image\UnsafeUrlException;
use SeaTryOn\Image\UrlSafetyPolicy;

defined( 'ABSPATH' ) || exit;

final class UrlSafetyPolicyTest extends TestCase {

	/** @dataProvider provideUnsafeUrls */
	public function test_rejects_ssrf_targets( string $url, array $addresses ): void {
		$policy = new UrlSafetyPolicy(
			false,
			static function () use ( $addresses ): array {
				return $addresses;
			}
		);

		$this->expectException( UnsafeUrlException::class );
		$policy->assert_safe( $url );
	}

	/** @return array<string,array{string,array<string>}> */
	public function provideUnsafeUrls(): array {
		return array(
			'cleartext'        => array( 'http://cdn.example/image.png', array( '93.184.216.34' ) ),
			'userinfo'         => array( 'https://user:pass@cdn.example/image.png', array( '93.184.216.34' ) ),
			'dangerous port'   => array( 'https://cdn.example:8080/image.png', array( '93.184.216.34' ) ),
			'loopback'         => array( 'https://cdn.example/image.png', array( '127.0.0.1' ) ),
			'private'          => array( 'https://cdn.example/image.png', array( '10.0.0.5' ) ),
			'link local'       => array( 'https://cdn.example/image.png', array( '169.254.169.254' ) ),
			'private ipv6'     => array( 'https://cdn.example/image.png', array( 'fd00::1' ) ),
			'mixed DNS answer' => array( 'https://cdn.example/image.png', array( '93.184.216.34', '192.168.1.1' ) ),
			'DNS failure'      => array( 'https://cdn.example/image.png', array() ),
			'mapped loopback'  => array( 'https://[::ffff:127.0.0.1]/image.png', array( '::ffff:127.0.0.1' ) ),
			'mapped private'   => array( 'https://[::ffff:10.0.0.1]/image.png', array( '::ffff:10.0.0.1' ) ),
			'compatible loopback' => array( 'https://[::127.0.0.1]/image.png', array( '::127.0.0.1' ) ),
			'compatible private' => array( 'https://[::10.0.0.1]/image.png', array( '::10.0.0.1' ) ),
			'carrier NAT'      => array( 'https://100.100.100.200/latest/meta-data', array( '100.100.100.200' ) ),
			'6to4 relay'       => array( 'https://192.88.99.1/image.png', array( '192.88.99.1' ) ),
			'multicast'        => array( 'https://224.0.0.1/image.png', array( '224.0.0.1' ) ),
			'benchmark'        => array( 'https://198.18.0.1/image.png', array( '198.18.0.1' ) ),
			'IPv6 NAT64'       => array( 'https://cdn.example/image.png', array( '64:ff9b::7f00:1' ) ),
			'IPv6 benchmark'   => array( 'https://cdn.example/image.png', array( '2001:2::1' ) ),
			'IPv6 documentation' => array( 'https://cdn.example/image.png', array( '2001:db8::1' ) ),
			'IPv6 documentation v2' => array( 'https://cdn.example/image.png', array( '3fff::1' ) ),
			'IPv6 service network' => array( 'https://cdn.example/image.png', array( '5f00::1' ) ),
			'IPv6 site local'  => array( 'https://cdn.example/image.png', array( 'fec0::1' ) ),
			'IPv6 multicast'   => array( 'https://cdn.example/image.png', array( 'ff02::1' ) ),
		);
	}

	public function test_accepts_only_public_https_by_default(): void {
		$policy = new UrlSafetyPolicy(
			false,
			static function (): array {
				return array( '93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946' );
			}
		);

		$policy->assert_safe( 'https://cdn.example/image.png?signature=hidden' );
		self::assertTrue( true );
	}

	public function test_explicit_development_policy_only_allows_http_loopback(): void {
		$policy = new UrlSafetyPolicy(
			true,
			null,
			static function (): string {
				return 'development';
			}
		);
		$policy->assert_safe( 'http://127.0.0.1/image.png' );

		self::assertTrue( true );
	}

	public function test_production_rejects_loopback_even_when_boolean_is_true(): void {
		$policy = new UrlSafetyPolicy(
			true,
			null,
			static function (): string {
				return 'production';
			}
		);

		$this->expectException( UnsafeUrlException::class );
		$policy->assert_safe( 'http://127.0.0.1/image.png' );
	}

	/** @dataProvider provideDevelopmentNonLoopbackUrls */
	public function test_development_boolean_does_not_allow_private_or_public_cleartext_hosts( string $url, string $ip ): void {
		$policy = new UrlSafetyPolicy(
			true,
			static function () use ( $ip ): array {
				return array( $ip );
			},
			static function (): string {
				return 'development';
			}
		);

		$this->expectException( UnsafeUrlException::class );
		$policy->assert_safe( $url );
	}

	/** @return array<string,array{string,string}> */
	public function provideDevelopmentNonLoopbackUrls(): array {
		return array(
			'private host' => array( 'http://10.0.0.5/image.png', '10.0.0.5' ),
			'public host'  => array( 'http://cdn.example/image.png', '93.184.216.34' ),
		);
	}
}
