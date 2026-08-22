<?php
/**
 * Validated product field submission.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Product;

use InvalidArgumentException;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

/**
 * Holds an atomic, normalized Classic Product Editor submission.
 */
final class ProductFieldSubmission {

	public const MAX_PROMPT_LENGTH = 2000;

	/**
	 * Whether Virtual Try-On is enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Sanitized merchant prompt.
	 *
	 * @var string
	 */
	private $prompt;

	/**
	 * Normalized experience type.
	 *
	 * @var string
	 */
	private $experience_type;

	/**
	 * Construct validated values.
	 *
	 * @param bool   $enabled         Whether the feature is enabled.
	 * @param string $prompt          Sanitized merchant prompt.
	 * @param string $experience_type Experience type value.
	 */
	private function __construct( bool $enabled, string $prompt, string $experience_type ) {
		$this->enabled         = $enabled;
		$this->prompt          = $prompt;
		$this->experience_type = $experience_type;
	}

	/**
	 * Validate and normalize explicit request values.
	 *
	 * UTF-8 is checked before sanitization so invalid byte sequences cannot be
	 * silently discarded and saved as a different prompt.
	 *
	 * @param string   $enabled_raw     Submitted checkbox value.
	 * @param string   $prompt_raw      Submitted prompt before sanitization.
	 * @param string   $experience_raw  Submitted experience type.
	 * @param callable $prompt_sanitizer WordPress-compatible textarea sanitizer.
	 * @return self
	 * @throws ProductFieldValidationException When any value is invalid.
	 */
	public static function from_raw(
		string $enabled_raw,
		string $prompt_raw,
		string $experience_raw,
		callable $prompt_sanitizer
	): self {
		if ( ! in_array( $enabled_raw, array( 'yes', 'no' ), true ) ) {
			throw new ProductFieldValidationException( ProductFieldValidationException::INVALID_ENABLED ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal reason code, never rendered.
		}

		if ( 1 !== preg_match( '//u', $prompt_raw ) ) {
			throw new ProductFieldValidationException( ProductFieldValidationException::INVALID_UTF8 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal reason code, never rendered.
		}

		$prompt = $prompt_sanitizer( $prompt_raw );

		if ( ! is_string( $prompt ) ) {
			throw new ProductFieldValidationException( ProductFieldValidationException::INVALID_UTF8 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal reason code, never rendered.
		}

		$prompt = trim( $prompt );

		if ( self::string_length( $prompt ) > self::MAX_PROMPT_LENGTH ) {
			throw new ProductFieldValidationException( ProductFieldValidationException::PROMPT_TOO_LONG ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal reason code, never rendered.
		}

		try {
			$experience_type = ExperienceType::from_string( $experience_raw )->value();
		} catch ( InvalidArgumentException $exception ) {
			throw new ProductFieldValidationException( ProductFieldValidationException::INVALID_EXPERIENCE ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal reason code, never rendered.
		}

		return new self( 'yes' === $enabled_raw, $prompt, $experience_type );
	}

	/**
	 * Whether the feature is enabled.
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Return the sanitized product prompt.
	 */
	public function prompt(): string {
		return $this->prompt;
	}

	/**
	 * Return the normalized experience type.
	 */
	public function experience_type(): string {
		return $this->experience_type;
	}

	/**
	 * Count Unicode code points without requiring the mbstring extension.
	 *
	 * @param string $value Valid UTF-8 value.
	 */
	private static function string_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		$matched = preg_match_all( '/./us', $value, $characters );

		return false === $matched ? strlen( $value ) : $matched;
	}
}
