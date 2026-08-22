<?php
/**
 * Guest action token service.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Signs short-lived tokens bound to guest session, product, expiry and action. */
final class ActionTokenService {

	private const MAX_TTL = 900;

	/** @var string */
	private $secret;

	/** @var ReplayStoreInterface */
	private $replays;

	/** @var callable */
	private $now;

	/** @param string $secret Site secret. @param ReplayStoreInterface $replays Replay store. @param callable|null $now Unix clock. */
	public function __construct( string $secret, ReplayStoreInterface $replays, ?callable $now = null ) {
		if ( strlen( $secret ) < 32 ) {
			throw new InvalidArgumentException( 'The action-token secret is too short.' );
		}
		$this->secret  = $secret;
		$this->replays = $replays;
		$this->now     = $now ?? 'time';
	}

	/** Issue a token for frontend localization. */
	public function issue( string $session_id, int $product_id, string $action, int $ttl = 300 ): string {
		$this->assert_claims( $session_id, $product_id, $action );
		if ( $ttl < 30 || $ttl > self::MAX_TTL ) {
			throw new InvalidArgumentException( 'Action-token TTL is invalid.' );
		}

		$payload = array(
			'v' => 1,
			'a' => $action,
			'p' => $product_id,
			'e' => call_user_func( $this->now ) + $ttl,
			'n' => self::base64url_encode( random_bytes( 18 ) ),
		);
		$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			throw new InvalidArgumentException( 'Action token could not be encoded.' );
		}
		$body = self::base64url_encode( $json );
		$sig  = hash_hmac( 'sha256', $body . '|' . $session_id, $this->secret, true );

		return $body . '.' . self::base64url_encode( $sig );
	}

	/** Verify a token and atomically consume it when the action mutates state. */
	public function verify( string $token, string $session_id, int $product_id, string $action, bool $consume ): void {
		$this->assert_claims( $session_id, $product_id, $action );
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || strlen( $token ) > 1024 ) {
			$this->deny();
		}

		$body      = $parts[0];
		$signature = self::base64url_decode( $parts[1] );
		$expected  = hash_hmac( 'sha256', $body . '|' . $session_id, $this->secret, true );
		if ( null === $signature || ! hash_equals( $expected, $signature ) ) {
			$this->deny();
		}

		$json    = self::base64url_decode( $body );
		$payload = null === $json ? null : json_decode( $json, true );
		$now     = (int) call_user_func( $this->now );
		$keys    = is_array( $payload ) ? array_keys( $payload ) : array();
		sort( $keys );
		if (
			! is_array( $payload ) ||
			array( 'a', 'e', 'n', 'p', 'v' ) !== $keys ||
			1 !== $payload['v'] ||
			$action !== $payload['a'] ||
			$product_id !== $payload['p'] ||
			! is_int( $payload['e'] ) ||
			$payload['e'] <= $now ||
			$payload['e'] > $now + self::MAX_TTL ||
			! is_string( $payload['n'] ) ||
			1 !== preg_match( '/^[A-Za-z0-9_-]{24}$/D', $payload['n'] )
		) {
			$this->deny();
		}

		if ( $consume && ! $this->replays->consume( hash( 'sha256', $token ), $payload['e'] ) ) {
			throw new AuthException( 'token_replayed', __( 'This authorization token has already been used.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 403 );
		}
	}

	/** Validate non-secret public claims. */
	private function assert_claims( string $session_id, int $product_id, string $action ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $session_id ) || $product_id < 1 || 1 !== preg_match( '/^(create|status|result|delete)$/D', $action ) ) {
			throw new InvalidArgumentException( 'Action-token claims are invalid.' );
		}
	}

	/** Throw the stable invalid-token response. */
	private function deny(): void {
		throw new AuthException( 'invalid_token', __( 'This authorization token is invalid or has expired.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 403 );
	}

	/** URL-safe base64 encoder. */
	private static function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-to-text encoding, not obfuscation.
	}

	/** Strict URL-safe base64 decoder. */
	private static function base64url_decode( string $value ): ?string {
		if ( '' === $value || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $value ) ) {
			return null;
		}
		$padding = ( 4 - strlen( $value ) % 4 ) % 4;
		$decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Strict binary-to-text decoding.

		return false === $decoded ? null : $decoded;
	}
}
