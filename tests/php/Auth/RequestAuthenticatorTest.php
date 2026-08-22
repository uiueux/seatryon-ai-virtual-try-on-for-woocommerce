<?php
/**
 * Request authenticator input-boundary tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	/** Minimal REST request double for header authentication. */
	class WP_REST_Request {

		/** @var array<string,string> */
		private $headers;

		/** @param array<string,string> $headers Request headers. */
		public function __construct( array $headers ) {
			$this->headers = $headers;
		}

		/** Return a request header. */
		public function get_header( string $name ): string {
			return $this->headers[ $name ] ?? '';
		}
	}
}

namespace SeaTryOn\Auth {
	function is_user_logged_in(): bool {
		return true;
	}

	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}

	function wp_verify_nonce( string $nonce, string $action ): bool {
		return 'valid-rest-nonce' === $nonce && 'wp_rest' === $action;
	}

	function get_current_user_id(): int {
		return 42;
	}

	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability;
	}

	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $message, string $domain ): string {
			unset( $domain );

			return $message;
		}
	}
}

namespace SeaTryOn\Tests\Auth {

	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Auth\ActionTokenService;
	use SeaTryOn\Auth\GuestSessionManager;
	use SeaTryOn\Auth\ReplayStoreInterface;
	use SeaTryOn\Auth\RequestAuthenticator;
	use SeaTryOn\Auth\SameOriginPolicy;
	use SeaTryOn\Security\OwnerIdentityHasher;
	use SeaTryOn\Settings\SettingsRepository;
	use WP_REST_Request;

	defined( 'ABSPATH' ) || exit;

	/** Verifies that REST nonce headers are sanitized before validation. */
	final class RequestAuthenticatorTest extends TestCase {

		/** A padded nonce header authenticates only after text sanitization. */
		public function test_logged_in_nonce_header_is_sanitized_before_verification(): void {
			$replays = new class() implements ReplayStoreInterface {
				public function consume( string $fingerprint, int $expires_at ): bool {
					unset( $fingerprint, $expires_at );

					return true;
				}
			};
			$auth    = new RequestAuthenticator(
				new SettingsRepository(),
				new GuestSessionManager(),
				new ActionTokenService( str_repeat( 's', 32 ), $replays ),
				new SameOriginPolicy(),
				new OwnerIdentityHasher( static function (): string {
					return str_repeat( 'o', 32 );
				} )
			);

			$identity = $auth->authenticate(
				new WP_REST_Request( array( 'x_wp_nonce' => '  valid-rest-nonce  ' ) ),
				123,
				'create'
			);

			self::assertTrue( $identity->is_logged_in() );
			self::assertTrue( $identity->is_quota_exempt() );
		}
	}
}
