<?php
/**
 * HTTP client contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Provides an injectable, WordPress-backed HTTP boundary.
 */
interface HttpClientInterface {

	/**
	 * Send one bounded request.
	 *
	 * @param HttpRequest $request Validated request value object.
	 * @throws TransportException When the request cannot be completed safely.
	 */
	public function request( HttpRequest $request ): HttpResponse;
}
