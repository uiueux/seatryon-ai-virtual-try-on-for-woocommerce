<?php
/**
 * SeaAI error normalization.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\SeaAI;

use SeaTryOn\DTO\ProviderError;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the custom SeaAI WordPress gateway contract to stable plugin errors.
 */
final class SeaAIErrorMapper {

	/**
	 * Normalize an unsuccessful gateway response without retaining its body.
	 *
	 * Only provider-controlled code/type fields are inspected. Gateway messages
	 * are deliberately ignored because they may echo prompts or remote URLs.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Raw bounded response body.
	 */
	public function from_http_response( int $status, string $body ): ProviderError {
		if ( 400 === $status ) {
			return new ProviderError( 'provider_invalid_request', 'The SeaAI gateway rejected the image request.', false, null, $status );
		}

		if ( 401 === $status ) {
			return new ProviderError( 'provider_auth_missing', 'SeaAI authentication is missing or invalid.', false, null, $status );
		}

		if ( 402 === $status ) {
			return new ProviderError( 'provider_insufficient_balance', 'The SeaAI account has insufficient points.', false, null, $status );
		}

		if ( 403 === $status ) {
			$discriminators = $this->safe_discriminators( $body );
			if ( false !== strpos( $discriminators, 'rate_limit' ) || false !== strpos( $discriminators, 'rate-limit' ) ) {
				return new ProviderError( 'provider_rate_limited', 'The SeaAI key is rate limited.', false, null, $status );
			}

			return new ProviderError( 'provider_auth_rejected', 'The SeaAI key was rejected.', false, null, $status );
		}

		if ( 502 === $status ) {
			return new ProviderError( 'provider_upstream_failure', 'The SeaAI upstream image provider failed.', true, null, $status );
		}

		if ( 503 === $status ) {
			return new ProviderError( 'provider_unavailable', 'The SeaAI image service is unavailable.', true, null, $status );
		}

		return new ProviderError( 'provider_gateway_error', 'The SeaAI gateway returned an unexpected error.', false, null, $status );
	}

	/**
	 * Build the invalid response error.
	 *
	 * @param int|null $status HTTP success status, when available.
	 */
	public function invalid_response( ?int $status = null ): ProviderError {
		return new ProviderError( 'provider_invalid_response', 'The SeaAI gateway returned an invalid response.', false, null, $status );
	}

	/**
	 * Build the synchronous Universal X contract-drift error.
	 *
	 * @param int|null    $status               HTTP success status, when available.
	 * @param string|null $diagnostic_reference Safe internal provider reference.
	 */
	public function contract_error( ?int $status = null, ?string $diagnostic_reference = null ): ProviderError {
		return new ProviderError( 'provider_contract_error', 'The SeaAI gateway returned an unsupported Universal X response.', false, null, $status, $diagnostic_reference );
	}

	/**
	 * Read only stable machine fields used to disambiguate a safe 403 outcome.
	 *
	 * @param string $body Raw bounded response body.
	 */
	private function safe_discriminators( string $body ): string {
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$values = array();
		foreach ( array( 'code', 'type' ) as $field ) {
			if ( isset( $decoded[ $field ] ) && is_string( $decoded[ $field ] ) ) {
				$values[] = strtolower( $decoded[ $field ] );
			}
		}

		if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
			foreach ( array( 'code', 'type' ) as $field ) {
				if ( isset( $decoded['error'][ $field ] ) && is_string( $decoded['error'][ $field ] ) ) {
					$values[] = strtolower( $decoded['error'][ $field ] );
				}
			}
		}

		return implode( ' ', $values );
	}
}
