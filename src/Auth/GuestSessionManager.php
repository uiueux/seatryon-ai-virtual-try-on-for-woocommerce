<?php
/**
 * High-entropy guest session cookie.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Issues an opaque HttpOnly, SameSite=Lax session cookie. */
final class GuestSessionManager {

	public const COOKIE_NAME = 'sea_tryon_session';
	private const LIFETIME   = 86400;

	/** Return a valid current session, without silently accepting malformed input. */
	public function current(): ?string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_string( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$value = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact high-entropy token validation follows.

		return 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $value ) ? $value : null;
	}

	/** Create the cookie when rendering the guest experience and return the session. */
	public function ensure(): string {
		$current = $this->current();
		if ( null !== $current ) {
			return $current;
		}

		$session = self::base64url_encode( random_bytes( 32 ) );
		$options = array(
			'expires'  => time() + self::LIFETIME,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( ! setcookie( self::COOKIE_NAME, $session, $options ) ) {
			throw new AuthException( 'authentication_required', __( 'A secure guest session could not be started.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
		}

		$_COOKIE[ self::COOKIE_NAME ] = $session;

		return $session;
	}

	/** Encode bytes without padding for cookie safety. */
	private static function base64url_encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-to-text encoding, not obfuscation.
	}
}
