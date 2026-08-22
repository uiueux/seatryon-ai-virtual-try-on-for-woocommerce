<?php
/**
 * One-use token replay store.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

interface ReplayStoreInterface {
	/** Atomically consume a token fingerprint until expiry. */
	public function consume( string $fingerprint, int $expires_at ): bool;
}
