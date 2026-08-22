<?php
/**
 * Provider credential access and persistence.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Security;

use InvalidArgumentException;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves secrets server-side while enforcing the selected provider boundary.
 */
final class SecretStore {

	public const MASK = '************';

	/**
	 * Typed settings access.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Shared SeaAI gateway URL policy.
	 *
	 * @var SeaAIBaseUrlValidator
	 */
	private $seaai_urls;

	/**
	 * Set up credential access.
	 *
	 * @param SettingsRepository|null    $settings   Settings repository.
	 * @param SeaAIBaseUrlValidator|null $seaai_urls SeaAI gateway URL policy.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?SeaAIBaseUrlValidator $seaai_urls = null ) {
		$this->settings   = $settings ?? new SettingsRepository();
		$this->seaai_urls = $seaai_urls ?? new SeaAIBaseUrlValidator();
	}

	/**
	 * Return only the selected provider's API key.
	 */
	public function get_active_api_key(): string {
		return SettingsRepository::PROVIDER_SEAAI === $this->settings->get_provider()
			? $this->resolve_secret( SettingsRepository::OPTION_SEAAI_API_KEY, 'SEA_TRYON_SEAAI_API_KEY' )
			: '';
	}

	/**
	 * Return a SeaAI key only while SeaAI is selected.
	 */
	public function get_seaai_api_key(): string {
		return SettingsRepository::PROVIDER_SEAAI === $this->settings->get_provider()
			? $this->get_active_api_key()
			: '';
	}

	/**
	 * Resolve the SeaAI key for an authorized administrator connection test.
	 *
	 * Unlike runtime generation, the settings screen may be testing an unsaved
	 * provider selection. This explicit diagnostic accessor therefore does not
	 * depend on the currently persisted provider while retaining all constant
	 * and filter overrides.
	 */
	public function get_seaai_api_key_for_connection_test(): string {
		return $this->resolve_secret(
			SettingsRepository::OPTION_SEAAI_API_KEY,
			'SEA_TRYON_SEAAI_API_KEY'
		);
	}

	/**
	 * Resolve the SeaAI URL only while SeaAI is selected.
	 */
	public function get_seaai_base_url(): string {
		if ( SettingsRepository::PROVIDER_SEAAI !== $this->settings->get_provider() ) {
			return '';
		}

		$url = $this->settings->get_stored_seaai_base_url();
		if ( defined( 'SEA_TRYON_SEAAI_BASE_URL' ) ) {
			$constant = constant( 'SEA_TRYON_SEAAI_BASE_URL' );
			if ( is_scalar( $constant ) && '' !== trim( (string) $constant ) ) {
				$url = trim( (string) $constant );
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sea_tryon_seaai_base_url', $url );
			$url      = is_scalar( $filtered ) ? trim( (string) $filtered ) : '';
		}

		return $this->seaai_urls->normalize( $url );
	}

	/**
	 * Save a newly submitted SeaAI key. Blank and masked values preserve the old key.
	 *
	 * @param string $submitted Submitted field value, already unslashed by the UI layer.
	 */
	public function save_seaai_api_key( string $submitted ): bool {
		return $this->save_secret( SettingsRepository::OPTION_SEAAI_API_KEY, $submitted );
	}

	/**
	 * Produce a fixed mask which cannot reveal length or fragments.
	 *
	 * @param string $secret Secret value.
	 */
	public function mask( string $secret ): string {
		return '' === $secret ? '' : self::MASK;
	}

	/**
	 * Report whether the selected provider has all required server-side values.
	 */
	public function is_active_provider_configured(): bool {
		if ( SettingsRepository::PROVIDER_SEAAI !== $this->settings->get_provider() ) {
			// WordPress owns connector credentials. Runtime capability is checked
			// before a job is accepted and again before provider dispatch.
			return true;
		}

		return '' !== $this->get_active_api_key() && '' !== $this->get_seaai_base_url();
	}

	/**
	 * Resolve option, then non-empty constant, then filter override.
	 *
	 * @param string $option        Option name.
	 * @param string $constant_name Constant name.
	 */
	private function resolve_secret( string $option, string $constant_name ): string {
		$value = $this->sanitize_secret( (string) $this->settings->options()->get( $option, '' ) );

		if ( defined( $constant_name ) ) {
			$constant = constant( $constant_name );
			if ( is_scalar( $constant ) && '' !== trim( (string) $constant ) ) {
				$value = $this->sanitize_secret( (string) $constant );
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sea_tryon_seaai_api_key', $value );
			$value    = is_scalar( $filtered ) ? $this->sanitize_secret( (string) $filtered ) : '';
		}

		return $value;
	}

	/**
	 * Persist a non-empty replacement secret with autoload disabled.
	 *
	 * @param string $option    Option name.
	 * @param string $submitted Submitted value.
	 * @throws InvalidArgumentException When the value contains only unusable characters.
	 */
	private function save_secret( string $option, string $submitted ): bool {
		$submitted = trim( $submitted );
		if ( '' === $submitted || self::MASK === $submitted ) {
			return true;
		}

		$secret = $this->sanitize_secret( $submitted );
		if ( '' === $secret ) {
			throw new InvalidArgumentException( 'The API key contains no usable characters.' );
		}

		return $this->settings->options()->update( $option, $secret, false );
	}

	/**
	 * Strip control characters which must never reach an HTTP header.
	 *
	 * @param string $secret Raw secret.
	 */
	private function sanitize_secret( string $secret ): string {
		$secret = preg_replace( '/[\x00-\x1F\x7F]/', '', trim( $secret ) );

		return null === $secret ? '' : $secret;
	}
}
