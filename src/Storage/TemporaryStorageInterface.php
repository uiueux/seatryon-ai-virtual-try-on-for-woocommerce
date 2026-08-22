<?php
/**
 * Private temporary storage contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Stores personal image data without creating public URLs or attachments.
 */
interface TemporaryStorageInterface {

	/** Create a high-entropy storage scope and return its relative identifier. */
	public function create_scope(): string;

	/**
	 * Write bytes into a scope and return a storage-relative identifier.
	 *
	 * @param string $scope_id Scope identifier.
	 * @param string $role File purpose without user-provided names.
	 * @param string $contents Bytes to store.
	 * @param string $extension Validated file extension.
	 */
	public function write( string $scope_id, string $role, string $contents, string $extension ): string;

	/**
	 * Read bytes identified by a trusted storage-relative identifier.
	 *
	 * @param string $storage_id Storage-relative identifier.
	 */
	public function read( string $storage_id ): string;

	/**
	 * Resolve an existing identifier for internal server-side processing.
	 *
	 * @param string $storage_id Storage-relative identifier.
	 */
	public function absolute_path( string $storage_id ): string;

	/**
	 * Delete one stored file eagerly.
	 *
	 * @param string $storage_id Storage-relative identifier.
	 */
	public function delete( string $storage_id ): bool;

	/**
	 * Delete an entire scope eagerly.
	 *
	 * @param string $scope_id Scope identifier.
	 */
	public function delete_scope( string $scope_id ): bool;

	/** Delete scopes older than the configured TTL and return the number removed. */
	public function cleanup_expired(): int;

	/** Return the canonical private root for diagnostics only. */
	public function root_path(): string;
}
