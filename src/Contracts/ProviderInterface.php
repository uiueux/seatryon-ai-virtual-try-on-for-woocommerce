<?php
/**
 * Image provider contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Contracts;

use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\DTO\ProviderResult;
use SeaTryOn\Domain\ProviderException;

defined( 'ABSPATH' ) || exit;

/**
 * Generates one normalized private result from a try-on request.
 */
interface ProviderInterface {

	/**
	 * Generate an image result.
	 *
	 * Implementations normalize failures into ProviderException and must not
	 * return raw image bytes, credentials, or unrestricted provider responses.
	 *
	 * @param ProviderRequest $request Normalized provider request.
	 * @return ProviderResult
	 * @throws ProviderException When the provider request cannot be completed.
	 */
	public function generate( ProviderRequest $request ): ProviderResult;
}
