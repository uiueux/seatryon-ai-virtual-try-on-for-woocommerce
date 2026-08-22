<?php
/**
 * WordPress AI Client adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\WordPressAI;

use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\Image\ValidatedImage;
use Throwable;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\FileTypeEnum;

defined( 'ABSPATH' ) || exit;

/** Uses the site-level provider registry and credentials managed by WordPress. */
final class WordPressAIClient implements WordPressAIClientInterface {

	private const MAX_RESULT_BYTES = 10485760;

	/** Valid one-pixel PNG used only for local capability matching. */
	private const PROBE_IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

	/**
	 * Prompt builder factory override.
	 *
	 * @var callable|null
	 */
	private $prompt_factory;

	/**
	 * Set up the core client boundary.
	 *
	 * @param callable|null $prompt_factory Test-only prompt builder factory.
	 */
	public function __construct( ?callable $prompt_factory = null ) {
		$this->prompt_factory = $prompt_factory;
	}

	/** {@inheritDoc} */
	public function supports_image_editing(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return false;
		}

		try {
			$probe   = new ValidatedImage( base64_decode( self::PROBE_IMAGE, true ), 'image/png', 1, 1, 'png' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixed local image fixture for capability matching.
			$builder = $this->build_prompt( 'Edit these two images.', array( $probe, $probe ) );

			return true === $builder->is_supported_for_image_generation();
		} catch ( Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/**
	 * Generate one inline image through the matching site connector.
	 *
	 * @param string           $prompt Prompt text.
	 * @param ValidatedImage[] $images Ordered input images.
	 * @throws ProviderException When no connector matches or generation fails.
	 */
	public function generate_image( string $prompt, array $images ): WordPressAIImage {
		try {
			$builder = $this->build_prompt( $prompt, $images );
			if ( true !== $builder->is_supported_for_image_generation() ) {
				throw new ProviderException( new ProviderError( 'provider_auth_missing', 'No configured WordPress AI connector supports image editing.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
			}

			$result = $builder->generate_image();
		} catch ( ProviderException $exception ) {
			throw $exception;
		} catch ( Throwable $exception ) {
			unset( $exception );
			throw new ProviderException( new ProviderError( 'provider_unavailable', 'The WordPress AI Client could not generate an image.', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			throw new ProviderException( $this->map_error( $result ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Normalized provider error DTO.
		}

		if ( ! $result instanceof File || ! $result->isInline() || ! $result->isImage() ) {
			throw new ProviderException( $this->invalid_response() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		$base64 = $result->getBase64Data();
		if ( ! is_string( $base64 ) || '' === $base64 || strlen( $base64 ) > 4 * (int) ceil( self::MAX_RESULT_BYTES / 3 ) ) {
			throw new ProviderException( $this->invalid_response() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- WordPress AI Client inline files are base64 encoded.
		if ( false === $bytes || strlen( $bytes ) > self::MAX_RESULT_BYTES ) {
			throw new ProviderException( $this->invalid_response() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		return new WordPressAIImage( $bytes, $result->getMimeType() );
	}

	/**
	 * Build a provider-agnostic prompt with two inline images.
	 *
	 * @param string           $prompt Prompt text.
	 * @param ValidatedImage[] $images Ordered input images.
	 * @return \WP_AI_Client_Prompt_Builder
	 * @throws \InvalidArgumentException When exactly two images are not supplied.
	 */
	private function build_prompt( string $prompt, array $images ) {
		if ( 2 !== count( $images ) || ! $images[0] instanceof ValidatedImage || ! $images[1] instanceof ValidatedImage ) {
			throw new \InvalidArgumentException( 'Exactly two validated input images are required.' );
		}

		$builder = null === $this->prompt_factory
			? wp_ai_client_prompt()
			: call_user_func( $this->prompt_factory );

		$builder
			->with_text( $prompt )
			->with_file( new File( base64_encode( $images[0]->bytes() ), $images[0]->mime() ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Core AI Client inline files require base64.
			->with_file( new File( base64_encode( $images[1]->bytes() ), $images[1]->mime() ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Core AI Client inline files require base64.
			->as_output_file_type( FileTypeEnum::inline() );

		return $builder;
	}

	/**
	 * Normalize a WordPress AI Client error without exposing provider details.
	 *
	 * @param \WP_Error $error WordPress error.
	 */
	private function map_error( $error ): ProviderError {
		$code   = (string) $error->get_error_code();
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( 401 === $status || 403 === $status ) {
			return new ProviderError( 'provider_auth_rejected', 'The configured WordPress AI connector rejected its credentials.', false, null, $status );
		}

		if ( 429 === $status ) {
			return new ProviderError( 'provider_unavailable', 'The configured WordPress AI connector is rate limited.', true, null, $status );
		}

		if ( 'prompt_network_error' === $code || 'prompt_upstream_server_error' === $code || $status >= 500 ) {
			return new ProviderError( 'provider_unavailable', 'The configured WordPress AI connector is temporarily unavailable.', true, null, $status > 0 ? $status : null );
		}

		if ( 'prompt_client_error' === $code || 'prompt_token_limit_reached' === $code || 'prompt_invalid_argument' === $code ) {
			return new ProviderError( 'provider_invalid_request', 'The configured WordPress AI connector rejected the image request.', false, null, $status > 0 ? $status : null );
		}

		return new ProviderError( 'provider_auth_missing', 'No configured WordPress AI connector can process this image request.', false, null, $status > 0 ? $status : null );
	}

	/** Return the stable invalid-result error. */
	private function invalid_response(): ProviderError {
		return new ProviderError( 'provider_invalid_response', 'The WordPress AI Client returned an invalid image.', false );
	}
}
