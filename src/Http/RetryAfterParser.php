<?php
/**
 * Retry-After parsing.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Retry-After to a bounded queue delay without sleeping.
 */
final class RetryAfterParser {

	public const MAX_SECONDS = 3600;

	/**
	 * Parse a delta-seconds or HTTP-date value.
	 *
	 * @param string|null $value Header value.
	 * @param int|null    $now   Current timestamp for deterministic scheduling.
	 */
	public function parse( ?string $value, ?int $now = null ): ?int {
		$value = null === $value ? '' : trim( $value );
		if ( '' === $value || strlen( $value ) > 128 || false !== strpbrk( $value, "\r\n" ) ) {
			return null;
		}

		if ( 1 === preg_match( '/^[0-9]{1,10}$/', $value ) ) {
			return min( self::MAX_SECONDS, (int) $value );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		$delay = $timestamp - ( $now ?? time() );

		return min( self::MAX_SECONDS, max( 0, $delay ) );
	}
}
