<?php
/**
 * Same-origin request policy.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Rejects cross-site guest requests before token verification. */
final class SameOriginPolicy {

	/** Assert Origin, or Referer when Origin is absent, matches the WordPress home origin. */
	public function assert_request( WP_REST_Request $request ): void {
		$source = trim( (string) $request->get_header( 'origin' ) );
		if ( '' === $source ) {
			$source = trim( (string) $request->get_header( 'referer' ) );
		}

		if ( '' === $source || ! $this->same_origin( $source, home_url( '/' ) ) ) {
			throw new AuthException( 'invalid_origin', __( 'This request did not come from this store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 403 );
		}
	}

	/** Compare normalized scheme, host and effective port. */
	private function same_origin( string $candidate, string $home ): bool {
		$left  = wp_parse_url( $candidate );
		$right = wp_parse_url( $home );
		if ( ! is_array( $left ) || ! is_array( $right ) || ! isset( $left['scheme'], $left['host'], $right['scheme'], $right['host'] ) ) {
			return false;
		}

		$left_scheme  = strtolower( (string) $left['scheme'] );
		$right_scheme = strtolower( (string) $right['scheme'] );
		$left_port    = isset( $left['port'] ) ? (int) $left['port'] : ( 'https' === $left_scheme ? 443 : 80 );
		$right_port   = isset( $right['port'] ) ? (int) $right['port'] : ( 'https' === $right_scheme ? 443 : 80 );

		return $left_scheme === $right_scheme && strtolower( (string) $left['host'] ) === strtolower( (string) $right['host'] ) && $left_port === $right_port;
	}
}
