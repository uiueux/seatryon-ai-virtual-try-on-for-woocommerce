<?php
/**
 * Full private temporary storage purge contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Storage;

defined( 'ABSPATH' ) || exit;

/** Allows lifecycle cleanup to remove all private scopes without root recursion. */
interface PurgeableTemporaryStorageInterface extends TemporaryStorageInterface {
	/** Delete every validated top-level private scope and return the number removed. */
	public function purge_all(): int;
}
