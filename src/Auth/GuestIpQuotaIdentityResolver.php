<?php
/**
 * Guest IP quota identity resolver.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use SeaTryOn\Quota\QuotaIdentity;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Produces a one-way daily quota identity from the web-server client address.
 *
 * The default deliberately uses only REMOTE_ADDR. Proxies must opt in through
 * the filter after they have established a trusted forwarded-address policy.
 */
final class GuestIpQuotaIdentityResolver {

	/** @var callable */
	private $address_resolver;

	/** @var callable */
	private $secret_resolver;

	/**
	 * @param callable|null $address_resolver Returns the web-server client address.
	 * @param callable|null $secret_resolver  Returns secret material for HMAC.
	 */
	public function __construct( ?callable $address_resolver = null, ?callable $secret_resolver = null ) {
		$this->address_resolver = $address_resolver ?? static function (): string {
			$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact IP validation follows.

			$filtered_address = apply_filters( 'sea_tryon_guest_ip_address', $address );

			return is_string( $filtered_address ) ? $filtered_address : '';
		};
		$this->secret_resolver  = $secret_resolver ?? static function (): string {
			return (string) wp_salt( 'sea_tryon_guest_ip_quota' );
		};
	}

	/**
	 * Return a one-way guest-IP quota identity; the raw address is never stored.
	 *
	 * @throws AuthException When the address or the secret cannot be used safely.
	 */
	public function resolve(): QuotaIdentity {
		$address = trim( (string) call_user_func( $this->address_resolver ) );
		$secret  = (string) call_user_func( $this->secret_resolver );

		if ( false === filter_var( $address, FILTER_VALIDATE_IP ) || '' === $secret ) {
			throw new AuthException( 'guest_ip_unavailable', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503 );
		}

		return QuotaIdentity::for_guest_ip_hash( hash_hmac( 'sha256', $address, $secret ) );
	}
}
