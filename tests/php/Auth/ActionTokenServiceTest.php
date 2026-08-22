<?php
/**
 * Guest action token tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Auth {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $text, string $domain ): string {
			unset( $domain );
			return $text;
		}
	}
	if ( ! function_exists( __NAMESPACE__ . '\\wp_json_encode' ) ) {
		/** @param mixed $value JSON value. */
		function wp_json_encode( $value, int $flags = 0 ) {
			return json_encode( $value, $flags );
		}
	}
}

namespace SeaTryOn\Tests\Auth {
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Auth\ActionTokenService;
	use SeaTryOn\Auth\AuthException;
	use SeaTryOn\Auth\ReplayStoreInterface;

	final class ActionTokenServiceTest extends TestCase {
		private const SECRET  = 'test-secret-that-is-at-least-thirty-two-bytes-long';
		private const SESSION = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

		public function test_issued_token_round_trips_without_consumption(): void {
			$service = new ActionTokenService( self::SECRET, new MemoryReplayStore(), static function (): int { return 1000; } );
			$token   = $service->issue( self::SESSION, 42, 'status', 300 );

			$service->verify( $token, self::SESSION, 42, 'status', false );
			$service->verify( $token, self::SESSION, 42, 'status', false );
			$this->addToAssertionCount( 1 );
		}

		public function test_mutating_token_is_consumed_once(): void {
			$service = new ActionTokenService( self::SECRET, new MemoryReplayStore(), static function (): int { return 1000; } );
			$token   = $service->issue( self::SESSION, 42, 'create', 300 );
			$service->verify( $token, self::SESSION, 42, 'create', true );

			$this->expectException( AuthException::class );
			$this->expectExceptionMessage( 'already been used' );
			$service->verify( $token, self::SESSION, 42, 'create', true );
		}

		public function test_token_is_rejected_at_its_exact_expiry_second(): void {
			$now     = 1000;
			$service = new ActionTokenService( self::SECRET, new MemoryReplayStore(), static function () use ( &$now ): int { return $now; } );
			$token   = $service->issue( self::SESSION, 42, 'status', 30 );
			$now     = 1030;

			$this->expectException( AuthException::class );
			$service->verify( $token, self::SESSION, 42, 'status', false );
		}

		/** @dataProvider mismatched_claim_provider */
		public function test_token_is_bound_to_session_product_and_action( string $session, int $product, string $action ): void {
			$service = new ActionTokenService( self::SECRET, new MemoryReplayStore(), static function (): int { return 1000; } );
			$token   = $service->issue( self::SESSION, 42, 'result', 300 );

			$this->expectException( AuthException::class );
			$service->verify( $token, $session, $product, $action, false );
		}

		/** @return array<string,array{string,int,string}> */
		public function mismatched_claim_provider(): array {
			return array(
				'session' => array( 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB', 42, 'result' ),
				'product' => array( self::SESSION, 43, 'result' ),
				'action'  => array( self::SESSION, 42, 'status' ),
			);
		}

		public function test_extra_payload_key_is_rejected_even_with_valid_signature(): void {
			$service = new ActionTokenService( self::SECRET, new MemoryReplayStore(), static function (): int { return 1000; } );
			$token   = $service->issue( self::SESSION, 42, 'status', 300 );
			$parts   = explode( '.', $token );
			$payload = json_decode( self::decode( $parts[0] ), true );
			$payload['extra'] = 'rejected';
			$body = self::encode( (string) json_encode( $payload ) );
			$sig  = self::encode( hash_hmac( 'sha256', $body . '|' . self::SESSION, self::SECRET, true ) );

			$this->expectException( AuthException::class );
			$service->verify( $body . '.' . $sig, self::SESSION, 42, 'status', false );
		}

		private static function encode( string $value ): string {
			return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
		}

		private static function decode( string $value ): string {
			$padding = ( 4 - strlen( $value ) % 4 ) % 4;
			return (string) base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true );
		}
	}

	final class MemoryReplayStore implements ReplayStoreInterface {
		/** @var array<string,bool> */ private $seen = array();
		public function consume( string $fingerprint, int $expires_at ): bool {
			unset( $expires_at );
			if ( isset( $this->seen[ $fingerprint ] ) ) {
				return false;
			}
			$this->seen[ $fingerprint ] = true;
			return true;
		}
	}
}
