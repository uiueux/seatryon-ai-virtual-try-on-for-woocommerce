<?php
/**
 * Recording HTTP client test double.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Http;

use SeaTryOn\Http\HttpClientInterface;
use SeaTryOn\Http\HttpRequest;
use SeaTryOn\Http\HttpResponse;

defined( 'ABSPATH' ) || exit;

final class RecordingHttpClient implements HttpClientInterface {

	/** @var array<HttpRequest> */
	public $requests = array();

	/** @var HttpResponse */
	private $response;

	public function __construct( HttpResponse $response ) {
		$this->response = $response;
	}

	public function request( HttpRequest $request ): HttpResponse {
		$this->requests[] = $request;

		return $this->response;
	}
}
