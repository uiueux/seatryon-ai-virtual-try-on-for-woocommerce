<?php
/**
 * Filesystem operations used by private temporary storage.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Makes security-sensitive filesystem behavior injectable in tests.
 */
interface FilesystemInterface {

	/**
	 * Return the canonical path, or null when it cannot be resolved.
	 *
	 * @param string $path Path to resolve.
	 */
	public function real_path( string $path ): ?string;

	/**
	 * Whether a path is a directory.
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_directory( string $path ): bool;

	/**
	 * Whether a path is writable.
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_writable( string $path ): bool;

	/**
	 * Whether a path is a symbolic link.
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_link( string $path ): bool;

	/**
	 * Whether a path exists as a regular file.
	 *
	 * @param string $path Path to inspect.
	 */
	public function is_file( string $path ): bool;

	/**
	 * Create a private directory recursively.
	 *
	 * @param string $path Directory path.
	 * @phpstan-impure
	 */
	public function create_directory( string $path ): bool;

	/**
	 * Write a new file without overwriting an existing path.
	 *
	 * @param string $path     Destination path.
	 * @param string $contents Bytes to write.
	 */
	public function write_exclusive( string $path, string $contents ): bool;

	/**
	 * Read a complete file, or return null on failure.
	 *
	 * @param string $path File path.
	 */
	public function read( string $path ): ?string;

	/**
	 * List direct children, excluding dot entries.
	 *
	 * @param string $path Directory path.
	 * @return string[]
	 */
	public function children( string $path ): array;

	/**
	 * Return the modification timestamp, or null on failure.
	 *
	 * @param string $path Path to inspect.
	 */
	public function modified_at( string $path ): ?int;

	/**
	 * Delete a file or symbolic link.
	 *
	 * @param string $path Path to delete.
	 */
	public function delete_file( string $path ): bool;

	/**
	 * Delete an empty directory.
	 *
	 * @param string $path Directory path.
	 */
	public function delete_directory( string $path ): bool;
}
