<?php
/**
 * Normalized provider exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use RuntimeException;
use SeaTryOn\DTO\ProviderError;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps provider-specific failures behind one stable domain boundary.
 */
final class ProviderException extends RuntimeException {

	/**
	 * Normalized provider error.
	 *
	 * @var ProviderError
	 */
	private $provider_error;

	/**
	 * Create an exception from a normalized safe error.
	 *
	 * @param ProviderError $provider_error Normalized error.
	 */
	public function __construct( ProviderError $provider_error ) {
		parent::__construct( $provider_error->message() );
		$this->provider_error = $provider_error;
	}

	/** Get the normalized provider error. */
	public function provider_error(): ProviderError {
		return $this->provider_error;
	}
}
