<?php
/**
 * Server-rendered guest authorization bootstrap.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

/** Supplies opaque action tokens; the HttpOnly session never enters JavaScript. */
final class GuestActionBootstrap {
	/** @var GuestSessionManager */ private $sessions;
	/** @var ActionTokenService */ private $tokens;
	public function __construct( GuestSessionManager $sessions, ActionTokenService $tokens ) {
		$this->sessions = $sessions;
		$this->tokens   = $tokens;
	}

	/** @return array<string,string> Header tokens localized by the product-page renderer. */
	public function for_product( int $product_id ): array {
		$session = $this->sessions->ensure();

		return array(
			'create' => $this->tokens->issue( $session, $product_id, 'create', 900 ),
			'status' => $this->tokens->issue( $session, $product_id, 'status', 900 ),
			'result' => $this->tokens->issue( $session, $product_id, 'result', 900 ),
			'delete' => $this->tokens->issue( $session, $product_id, 'delete', 900 ),
		);
	}
}
