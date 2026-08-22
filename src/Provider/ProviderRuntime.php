<?php
/**
 * Provider runtime value.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider;

use SeaTryOn\Contracts\ProviderInterface;
use SeaTryOn\DTO\ProviderRequest;

defined( 'ABSPATH' ) || exit;

/** Selected provider plus its immutable, job-derived request. */
final class ProviderRuntime {
	/**
	 * Selected provider.
	 *
	 * @var ProviderInterface
	 */
	private $provider;

	/**
	 * Immutable request.
	 *
	 * @var ProviderRequest
	 */
	private $request;

	/**
	 * Store the selected runtime pair.
	 *
	 * @param ProviderInterface $provider Selected provider.
	 * @param ProviderRequest   $request  Immutable request.
	 */
	public function __construct( ProviderInterface $provider, ProviderRequest $request ) {
		$this->provider = $provider;
		$this->request  = $request;
	}

	/** Return the selected provider. */
	public function provider(): ProviderInterface {
		return $this->provider;
	}

	/** Return the immutable request. */
	public function request(): ProviderRequest {
		return $this->request;
	}
}
