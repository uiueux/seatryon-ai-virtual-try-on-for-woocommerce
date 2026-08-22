<?php
/**
 * Temporary storage exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when private storage cannot safely fulfill an operation.
 */
final class StorageException extends \RuntimeException {
}
