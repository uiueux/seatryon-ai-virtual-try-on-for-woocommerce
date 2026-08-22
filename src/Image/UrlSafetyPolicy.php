<?php
/**
 * Remote image URL safety policy.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

defined( 'ABSPATH' ) || exit;

/**
 * Fails closed for unsafe schemes, ports, hosts and resolved addresses.
 *
 * WordPress revalidates redirect targets in wp_safe_remote_request(). DNS cannot
 * be pinned through the public WordPress HTTP API, so this preflight plus the
 * safe client is defense in depth rather than a complete DNS-pinning guarantee.
 */
final class UrlSafetyPolicy {

	/**
	 * Explicit local HTTP opt-in.
	 *
	 * @var bool
	 */
	private $allow_insecure_local;

	/**
	 * DNS resolver.
	 *
	 * @var callable|null
	 */
	private $resolver;

	/**
	 * Environment type resolver.
	 *
	 * @var callable|null
	 */
	private $environment_resolver;

	/**
	 * Set up URL and DNS restrictions.
	 *
	 * @param bool          $allow_insecure_local Explicit development-only localhost exception.
	 * @param callable|null $resolver             Test seam returning a list of resolved IPs.
	 * @param callable|null $environment_resolver Returns the WordPress environment type.
	 */
	public function __construct( bool $allow_insecure_local = false, ?callable $resolver = null, ?callable $environment_resolver = null ) {
		$this->allow_insecure_local = $allow_insecure_local;
		$this->resolver             = $resolver;
		$this->environment_resolver = $environment_resolver;
	}

	/**
	 * Validate a candidate image URL.
	 *
	 * @param string $url Candidate URL.
	 * @throws UnsafeUrlException When a URL could target an unsafe network location.
	 */
	public function assert_safe( string $url ): void {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit-test fallback.
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new UnsafeUrlException( 'The remote image URL is invalid.' );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( trim( rtrim( (string) $parts['host'], '.' ), '[]' ) );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		if ( '' === $host || false === filter_var( $url, FILTER_VALIDATE_URL ) || false !== strpbrk( $host, "\r\n\0" ) ) {
			throw new UnsafeUrlException( 'The remote image URL is invalid.' );
		}

		$development_local = $this->allow_insecure_local
			&& in_array( $this->environment_type(), array( 'local', 'development' ), true )
			&& $this->is_local_hostname( $host );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && $development_local ) ) {
			throw new UnsafeUrlException( 'Remote images require HTTPS.' );
		}

		if ( ( 'https' === $scheme && 443 !== $port ) || ( 'http' === $scheme && 80 !== $port ) ) {
			throw new UnsafeUrlException( 'The remote image port is not allowed.' );
		}

		if ( $this->is_metadata_hostname( $host ) ) {
			throw new UnsafeUrlException( 'The remote image host is not allowed.' );
		}

		$addresses = $this->resolve( $host );
		if ( array() === $addresses ) {
			throw new UnsafeUrlException( 'The remote image host could not be resolved safely.' );
		}

		foreach ( $addresses as $address ) {
			if ( $development_local && $this->is_loopback_ip( $address ) ) {
				continue;
			}

			if ( ! $this->is_public_ip( $address ) ) {
				throw new UnsafeUrlException( 'The remote image host resolves to a restricted address.' );
			}
		}
	}

	/**
	 * Determine whether a hostname is an explicit loopback target.
	 *
	 * @param string $host Hostname or IP.
	 */
	private function is_local_hostname( string $host ): bool {
		return 'localhost' === $host || $this->is_loopback_ip( $host );
	}

	/**
	 * Identify known metadata-service hostnames.
	 *
	 * @param string $host Hostname.
	 */
	private function is_metadata_hostname( string $host ): bool {
		return 'metadata.google.internal' === $host
			|| 'metadata' === $host
			|| 'instance-data' === $host
			|| '.internal' === substr( $host, -9 )
			|| '.localhost' === substr( $host, -10 );
	}

	/**
	 * Resolve all available A and AAAA addresses.
	 *
	 * @param string $host Hostname or IP.
	 * @return array<string>
	 */
	private function resolve( string $host ): array {
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}

		if ( null !== $this->resolver ) {
			$result = call_user_func( $this->resolver, $host );

			return is_array( $result ) ? array_values( array_filter( array_map( 'strval', $result ) ) ) : array();
		}

		$addresses = array();
		$records   = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_A | DNS_AAAA ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is handled by fail-closed policy.
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( isset( $record['ip'] ) ) {
					$addresses[] = (string) $record['ip'];
				} elseif ( isset( $record['ipv6'] ) ) {
					$addresses[] = (string) $record['ipv6'];
				}
			}
		}

		return array_values( array_unique( $addresses ) );
	}

	/**
	 * Determine whether an IP is loopback.
	 *
	 * @param string $ip IP address.
	 */
	private function is_loopback_ip( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		return '::1' === $ip || 0 === strpos( $ip, '127.' );
	}

	/**
	 * Determine whether an IP is globally routable.
	 *
	 * @param string $ip IP address.
	 */
	private function is_public_ip( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) || false === inet_pton( $ip ) ) {
			return false;
		}

		foreach ( $this->restricted_networks() as $network ) {
			if ( $this->ip_is_in_network( $ip, $network[0], $network[1] ) ) {
				return false;
			}
		}

		return false !== filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Return networks that must never be reached by a remote image request.
	 *
	 * PHP's FILTER_FLAG_NO_RES_RANGE does not cover every special-purpose
	 * registry entry consistently across supported PHP versions. Keep the
	 * security boundary explicit, including carrier-grade NAT, cloud metadata,
	 * benchmarking, multicast, transition mechanisms and mapped IPv4.
	 *
	 * @return array<int,array{string,int}>
	 */
	private function restricted_networks(): array {
		return array(
			array( '0.0.0.0', 8 ),
			array( '10.0.0.0', 8 ),
			array( '100.64.0.0', 10 ),
			array( '127.0.0.0', 8 ),
			array( '169.254.0.0', 16 ),
			array( '172.16.0.0', 12 ),
			array( '192.0.0.0', 24 ),
			array( '192.0.2.0', 24 ),
			array( '192.88.99.0', 24 ),
			array( '192.168.0.0', 16 ),
			array( '198.18.0.0', 15 ),
			array( '198.51.100.0', 24 ),
			array( '203.0.113.0', 24 ),
			array( '224.0.0.0', 4 ),
			array( '240.0.0.0', 4 ),
			array( '::', 96 ),
			array( '::ffff:0:0', 96 ),
			array( '64:ff9b::', 96 ),
			array( '64:ff9b:1::', 48 ),
			array( '100::', 64 ),
			array( '2001::', 32 ),
			array( '2001:2::', 48 ),
			array( '2001:10::', 28 ),
			array( '2001:20::', 28 ),
			array( '2001:db8::', 32 ),
			array( '2002::', 16 ),
			array( '3fff::', 20 ),
			array( '5f00::', 16 ),
			array( 'fc00::', 7 ),
			array( 'fe80::', 10 ),
			array( 'fec0::', 10 ),
			array( 'ff00::', 8 ),
		);
	}

	/**
	 * Compare an IP address with a binary CIDR prefix.
	 *
	 * @param string $ip      Candidate IP address.
	 * @param string $network Network base address.
	 * @param int    $prefix  CIDR prefix length.
	 */
	private function ip_is_in_network( string $ip, string $network, int $prefix ): bool {
		$packed_ip      = inet_pton( $ip );
		$packed_network = inet_pton( $network );

		if ( false === $packed_ip || false === $packed_network || strlen( $packed_ip ) !== strlen( $packed_network ) ) {
			return false;
		}

		$maximum_bits = 8 * strlen( $packed_ip );
		if ( $prefix < 0 || $prefix > $maximum_bits ) {
			return false;
		}

		$whole_bytes = intdiv( $prefix, 8 );
		if ( $whole_bytes > 0 && substr( $packed_ip, 0, $whole_bytes ) !== substr( $packed_network, 0, $whole_bytes ) ) {
			return false;
		}

		$remaining_bits = $prefix % 8;
		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;

		return ( ord( $packed_ip[ $whole_bytes ] ) & $mask ) === ( ord( $packed_network[ $whole_bytes ] ) & $mask );
	}

	/** Return the current WordPress environment type. */
	private function environment_type(): string {
		if ( null !== $this->environment_resolver ) {
			return strtolower( (string) call_user_func( $this->environment_resolver ) );
		}

		return function_exists( 'wp_get_environment_type' ) ? strtolower( wp_get_environment_type() ) : 'production';
	}
}
