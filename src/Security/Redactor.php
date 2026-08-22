<?php
/**
 * Log data redaction.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Security;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Removes credentials, authorization tokens and image payloads from logs.
 */
final class Redactor {

	private const REDACTED = '[REDACTED]';

	/**
	 * Exact secret values to remove.
	 *
	 * @var array<string>
	 */
	private $known_secrets;

	/**
	 * Set up the redaction policy.
	 *
	 * @param array<string> $known_secrets Secrets which may not match a generic pattern.
	 */
	public function __construct( array $known_secrets = array() ) {
		$this->known_secrets = array_values(
			array_filter(
				array_map( 'strval', $known_secrets ),
				static function ( string $secret ): bool {
					return '' !== $secret;
				}
			)
		);

		usort(
			$this->known_secrets,
			static function ( string $left, string $right ): int {
				return strlen( $right ) <=> strlen( $left );
			}
		);
	}

	/**
	 * Redact sensitive fragments in a free-form message.
	 *
	 * @param string $message Raw message.
	 */
	public function redact( string $message ): string {
		if ( $this->known_secrets ) {
			$message = str_replace( $this->known_secrets, self::REDACTED, $message );
		}

		$patterns = array(
			'/data:image\/[a-z0-9.+-]+;base64,[a-z0-9+\/=\r\n]+/i' => self::REDACTED,
			'/\bBearer\s+[^\s,;]+/i'                     => 'Bearer ' . self::REDACTED,
			'/([?&](?:api[_-]?key|access[_-]?token|token|authorization|signature|sig|secret)=)[^&#\s]*/i' => '$1' . self::REDACTED,
			'/("(?:api[_-]?key|access[_-]?token|token|authorization|secret|password|b64_json|image_data)"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i' => '$1"' . self::REDACTED . '"',
			'/\b(api[_-]?key|access[_-]?token|token|authorization|secret|password)\b(\s*[:=]\s*)[^\s,;&]+/i' => '$1$2' . self::REDACTED,
			'/\b(\d{1,3}\.\d{1,3}\.\d{1,3})\.\d{1,3}\b/' => '$1.x',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$redacted = preg_replace( $pattern, $replacement, $message );
			if ( null !== $redacted ) {
				$message = $redacted;
			}
		}

		$ipv6_redacted = preg_replace_callback(
			'/(?<![A-Fa-f0-9:])(?:[A-Fa-f0-9]{0,4}:){2,7}[A-Fa-f0-9]{0,4}(?![A-Fa-f0-9:])/',
			static function ( array $matches ): string {
				return false !== filter_var( $matches[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
					? '[IPV6 REDACTED]'
					: $matches[0];
			},
			$message
		);

		if ( null !== $ipv6_redacted ) {
			$message = $ipv6_redacted;
		}

		return $message;
	}

	/**
	 * Recursively sanitize logger context.
	 *
	 * @param array<mixed> $context Raw context.
	 * @return array<mixed>
	 */
	public function redact_context( array $context ): array {
		$redacted = array();

		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && $this->is_sensitive_key( $key ) ) {
				$redacted[ $key ] = self::REDACTED;
				continue;
			}

			$redacted[ $key ] = $this->redact_value( $value );
		}

		return $redacted;
	}

	/**
	 * Sanitize one nested context value.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function redact_value( $value ) {
		if ( is_array( $value ) ) {
			return $this->redact_context( $value );
		}

		if ( is_string( $value ) ) {
			return $this->redact( $value );
		}

		if ( $value instanceof Throwable ) {
			return get_class( $value ) . ': ' . $this->redact( $value->getMessage() );
		}

		if ( is_object( $value ) ) {
			return '[OBJECT ' . get_class( $value ) . ']';
		}

		if ( is_resource( $value ) ) {
			return '[RESOURCE]';
		}

		return $value;
	}

	/**
	 * Determine whether a context key denotes sensitive data.
	 *
	 * @param string $key Context key.
	 */
	private function is_sensitive_key( string $key ): bool {
		return 1 === preg_match(
			'/(?:api.?key|authorization|bearer|cookie|session(?:_?id)?|token|signature|(?:^|[_-])sig(?:$|[_-])|secret|password|credential|b64_json|image(?:_data|_base64)?|(?:result|download|image)_?url)/i',
			$key
		);
	}
}
