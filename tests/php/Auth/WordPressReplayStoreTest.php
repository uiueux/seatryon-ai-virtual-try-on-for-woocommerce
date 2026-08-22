<?php
/**
 * WordPress replay-store tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Auth;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Auth\WordPressReplayStore;

defined( 'ABSPATH' ) || exit;

final class WordPressReplayStoreTest extends TestCase {
	private const FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	public function test_new_fingerprint_is_added_once(): void {
		$added = array();
		$store = new WordPressReplayStore(
			static function () {
				return null;
			},
			static function ( string $name, int $expiry ) use ( &$added ): bool {
				$added = array( $name, $expiry );
				return true;
			},
			null,
			static function (): int {
				return 1000;
			}
		);

		self::assertTrue( $store->consume( self::FINGERPRINT, 1100 ) );
		self::assertSame( 1100, $added[1] );
	}

	public function test_expired_database_string_is_reclaimed_with_compare_delete(): void {
		$deleted = null;
		$store   = new WordPressReplayStore(
			static function () {
				return '900';
			},
			static function (): bool {
				return true;
			},
			static function ( string $name, $expected ) use ( &$deleted ): bool {
				unset( $name );
				$deleted = $expected;
				return true;
			},
			static function (): int {
				return 1000;
			}
		);

		self::assertTrue( $store->consume( self::FINGERPRINT, 1100 ) );
		self::assertSame( '900', $deleted );
	}

	public function test_competing_replacement_survives_failed_compare_delete(): void {
		$value = '900';
		$adds  = 0;
		$store = new WordPressReplayStore(
			static function () use ( &$value ) {
				return $value;
			},
			static function () use ( &$adds ): bool {
				++$adds;
				return true;
			},
			static function () use ( &$value ): bool {
				$value = '1200';
				return false;
			},
			static function (): int {
				return 1000;
			}
		);

		self::assertFalse( $store->consume( self::FINGERPRINT, 1100 ) );
		self::assertSame( '1200', $value );
		self::assertSame( 0, $adds );
	}

	public function test_active_or_malformed_marker_fails_closed(): void {
		foreach ( array( '1200', 'not-an-expiry' ) as $existing ) {
			$store = new WordPressReplayStore(
				static function () use ( $existing ) {
					return $existing;
				},
				static function (): bool {
					return true;
				},
				static function (): bool {
					return true;
				},
				static function (): int {
					return 1000;
				}
			);
			self::assertFalse( $store->consume( self::FINGERPRINT, 1100 ) );
		}
	}

	public function test_cleanup_uses_compare_delete_for_expired_and_malformed_markers(): void {
		$deleted = array();
		$prefix  = 'sea_tryon_replay_';
		$store   = new WordPressReplayStore(
			null,
			null,
			static function ( string $name, $expected ) use ( &$deleted ): bool {
				$deleted[ $name ] = $expected;
				return true;
			},
			static function (): int {
				return 1000;
			},
			static function () use ( $prefix ): array {
				return array(
					array(
						'option_name'  => $prefix . str_repeat( 'a', 64 ),
						'option_value' => '900',
					),
					array(
						'option_name'  => $prefix . str_repeat( 'b', 64 ),
						'option_value' => '1200',
					),
					array(
						'option_name'  => $prefix . str_repeat( 'c', 64 ),
						'option_value' => 'malformed',
					),
				);
			}
		);

		self::assertSame( 2, $store->cleanup_expired() );
		self::assertArrayHasKey( $prefix . str_repeat( 'a', 64 ), $deleted );
		self::assertArrayHasKey( $prefix . str_repeat( 'c', 64 ), $deleted );
		self::assertArrayNotHasKey( $prefix . str_repeat( 'b', 64 ), $deleted );
	}
}
