<?php
/**
 * WordPress AI Client provider adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\WordPressAI;

use SeaTryOn\Contracts\ProviderInterface;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\DTO\ProviderResult;
use SeaTryOn\Image\ImageValidationException;
use SeaTryOn\Image\ImageValidator;
use SeaTryOn\Storage\StorageException;
use SeaTryOn\Storage\TemporaryStorageInterface;

defined( 'ABSPATH' ) || exit;

/** Generates an image through the site-level WordPress AI provider registry. */
final class WordPressAIProvider implements ProviderInterface {

	/**
	 * Private temporary storage.
	 *
	 * @var TemporaryStorageInterface
	 */
	private $storage;

	/**
	 * Core AI Client boundary.
	 *
	 * @var WordPressAIClientInterface
	 */
	private $client;

	/**
	 * Binary image validator.
	 *
	 * @var ImageValidator
	 */
	private $image_validator;

	/**
	 * Configure provider dependencies.
	 *
	 * @param TemporaryStorageInterface  $storage         Private storage.
	 * @param WordPressAIClientInterface $client          Core AI Client boundary.
	 * @param ImageValidator|null        $image_validator Binary image validator.
	 */
	public function __construct( TemporaryStorageInterface $storage, WordPressAIClientInterface $client, ?ImageValidator $image_validator = null ) {
		$this->storage         = $storage;
		$this->client          = $client;
		$this->image_validator = $image_validator ?? new ImageValidator();
	}

	/**
	 * Generate and store one validated image result.
	 *
	 * @param ProviderRequest $request Normalized provider request.
	 * @throws ProviderException When input, generation, validation, or storage fails.
	 */
	public function generate( ProviderRequest $request ): ProviderResult {
		$customer = $this->read_input( $request->customer_image_reference() );
		$product  = $this->read_input( $request->product_image_reference() );
		$result   = $this->client->generate_image( $request->prompt(), array( $customer, $product ) );

		try {
			$image = $this->image_validator->validate( $result->bytes(), $result->mime_type() );
		} catch ( ImageValidationException $exception ) {
			$code = 'image_decoder_unavailable' === $exception->reason() ? 'provider_image_validation_unavailable' : 'provider_invalid_response';
			throw new ProviderException( new ProviderError( $code, 'The WordPress AI Client returned an invalid image.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		if ( ! in_array( $image->mime(), array( 'image/png', 'image/jpeg' ), true ) ) {
			throw new ProviderException( new ProviderError( 'provider_invalid_response', 'The WordPress AI Client returned an unsupported image format.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		$scope = strstr( $request->customer_image_reference(), '/', true );
		if ( false === $scope || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $scope ) ) {
			throw new ProviderException( new ProviderError( 'provider_storage_error', 'The private result scope is invalid.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		try {
			$reference = $this->storage->write( $scope, 'result', $image->bytes(), $image->extension() );
		} catch ( StorageException $exception ) {
			throw new ProviderException( new ProviderError( 'provider_storage_error', 'The generated image could not be stored privately.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}

		return new ProviderResult( $reference, $image->mime(), strlen( $image->bytes() ) );
	}

	/**
	 * Read and validate one private input.
	 *
	 * @param string $reference Private storage identifier.
	 * @return \SeaTryOn\Image\ValidatedImage
	 * @throws ProviderException When the input is unavailable or invalid.
	 */
	private function read_input( string $reference ) {
		try {
			return $this->image_validator->validate( $this->storage->read( $reference ) );
		} catch ( StorageException | ImageValidationException $exception ) {
			throw new ProviderException( new ProviderError( 'provider_input_unavailable', 'A private input image is unavailable or invalid.', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal provider error.
		}
	}
}
