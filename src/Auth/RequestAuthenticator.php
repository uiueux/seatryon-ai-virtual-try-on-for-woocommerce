<?php
/**
 * REST cookie and guest-token authentication.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use WP_REST_Request;
use SeaTryOn\Security\OwnerIdentityHasher;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Authenticates a request before object-level ownership checks. */
final class RequestAuthenticator {

	/** @var SettingsRepository */
	private $settings;

	/** @var GuestSessionManager */
	private $sessions;

	/** @var ActionTokenService */
	private $tokens;

	/** @var SameOriginPolicy */
	private $origins;

	/** @var OwnerIdentityHasher */
	private $owner_hasher;

	/** @param SettingsRepository $settings Settings. @param GuestSessionManager $sessions Guest sessions. @param ActionTokenService $tokens Tokens. @param SameOriginPolicy $origins Origin policy. @param OwnerIdentityHasher $owner_hasher Shared owner hasher. */
	public function __construct( SettingsRepository $settings, GuestSessionManager $sessions, ActionTokenService $tokens, SameOriginPolicy $origins, OwnerIdentityHasher $owner_hasher ) {
		$this->settings     = $settings;
		$this->sessions     = $sessions;
		$this->tokens       = $tokens;
		$this->origins      = $origins;
		$this->owner_hasher = $owner_hasher;
	}

	/** Authenticate logged-in cookie+nonce or guest session+token. */
	public function authenticate( WP_REST_Request $request, int $product_id, string $action, bool $consume = false ): RequestIdentity {
		if ( is_user_logged_in() ) {
			$nonce = sanitize_text_field( (string) $request->get_header( 'x_wp_nonce' ) );
			if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				throw new AuthException( 'invalid_nonce', __( 'Your session has expired. Please refresh the page and try again.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 403 );
			}

			$user_id      = get_current_user_id();
			$quota_exempt = current_user_can( 'manage_options' );

			return new RequestIdentity( $user_id, null, $this->owner_hasher->for_user_id( $user_id ), $quota_exempt );
		}

		if ( ! $this->settings->allow_guests() ) {
			throw new AuthException( 'authentication_required', __( 'Please log in to use Virtual Try-On.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
		}

		$this->origins->assert_request( $request );
		$session = $this->sessions->current();
		$token   = trim( (string) $request->get_header( 'x_sea_tryon_token' ) );
		if ( null === $session || '' === $token ) {
			throw new AuthException( 'authentication_required', __( 'A valid guest session is required.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
		}

		$this->tokens->verify( $token, $session, $product_id, $action, $consume );

		return new RequestIdentity( null, $session, $this->owner_hasher->for_guest_session( $session ) );
	}
}
