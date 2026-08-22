<?php
/**
 * SeaAI gateway URL validation shared by settings and runtime resolution.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes the fixed SeaAI API root without weakening production HTTP rules.
 */
final class SeaAIBaseUrlValidator {

	/**
	 * WordPress environment type resolver.
	 *
	 * @var callable
	 */
	private $environment_resolver;

	/**
	 * Current WordPress site URL resolver.
	 *
	 * @var callable
	 */
	private $site_url_resolver;

	/**
	 * Optional development-only HTTP policy override.
	 *
	 * @var callable|null
	 */
	private $development_http_filter;

	/**
	 * Set up the shared SeaAI gateway URL policy.
	 *
	 * @param callable|null $environment_resolver     Returns the WordPress environment type.
	 * @param callable|null $site_url_resolver        Returns the current WordPress site URL.
	 * @param callable|null $development_http_filter  Optional test seam for development HTTP hosts.
	 */
	public function __construct(
		?callable $environment_resolver = null,
		?callable $site_url_resolver = null,
		?callable $development_http_filter = null
	) {
		$this->environment_resolver    = $environment_resolver ?? static function (): string {
			return function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		};
		$this->site_url_resolver       = $site_url_resolver ?? static function (): string {
			return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		};
		$this->development_http_filter = $development_http_filter;
	}

	/**
	 * Return a normalized gateway root, or an empty string when invalid.
	 *
	 * HTTPS is valid in every environment. Cleartext HTTP is limited to:
	 * - a loopback gateway on a loopback WordPress site; or
	 * - local/development environments, where loopback is allowed by default
	 *   and a filter may explicitly allow another test host.
	 *
	 * @param string $url Candidate gateway root.
	 */
	public function normalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$parts = $this->parse_url( $url );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'] )
			|| '' === trim( (string) $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
			|| '/wp-json/seaai/v1' !== substr( $path, -strlen( '/wp-json/seaai/v1' ) )
		) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( 'https' === $scheme ) {
			return rtrim( $url, '/' );
		}

		return 'http' === $scheme && $this->is_development_http_allowed( $url )
			? rtrim( $url, '/' )
			: '';
	}

	/**
	 * Determine whether cleartext HTTP is confined to a development boundary.
	 *
	 * @param string $url Candidate gateway root.
	 */
	private function is_development_http_allowed( string $url ): bool {
		$environment         = (string) call_user_func( $this->environment_resolver );
		$gateway_is_loopback = $this->is_loopback_url( $url );

		if ( in_array( $environment, array( 'local', 'development' ), true ) ) {
			$allowed = $gateway_is_loopback;
			if ( null !== $this->development_http_filter ) {
				return (bool) call_user_func( $this->development_http_filter, $allowed, $url, $environment );
			}

			if ( function_exists( 'apply_filters' ) ) {
				/**
				 * Permit an HTTP SeaAI test host in local/development environments only.
				 *
				 * @param bool   $allowed     Whether the URL may use HTTP.
				 * @param string $url         Candidate SeaAI base URL.
				 * @param string $environment WordPress environment type.
				 */
				$allowed = (bool) apply_filters( 'sea_tryon_allow_insecure_seaai_base_url', $allowed, $url, $environment );
			}

			return $allowed;
		}

		// Local WordPress installations frequently omit WP_ENVIRONMENT_TYPE. A
		// loopback site talking to a loopback gateway remains machine-local and
		// does not enable arbitrary production HTTP hosts.
		$site_url = (string) call_user_func( $this->site_url_resolver );

		return $gateway_is_loopback && $this->is_loopback_url( $site_url );
	}

	/**
	 * Determine whether a URL has a literal loopback host.
	 *
	 * @param string $url URL to inspect.
	 */
	private function is_loopback_url( string $url ): bool {
		$parts = $this->parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( trim( (string) $parts['host'], '[]' ) );
		if ( 'localhost' === $host || '::1' === $host ) {
			return true;
		}

		return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
			&& 0 === strpos( $host, '127.' );
	}

	/**
	 * Parse a URL with WordPress semantics and an isolated-test fallback.
	 *
	 * @param string $url URL to parse.
	 * @return array<string,int|string>|false
	 */
	private function parse_url( string $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			return wp_parse_url( $url );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit tests run without WordPress.
		return parse_url( $url );
	}
}
