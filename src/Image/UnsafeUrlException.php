<?php
/**
 * Unsafe image URL exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Indicates an SSRF policy rejection without exposing DNS details.
 */
final class UnsafeUrlException extends RuntimeException {
}
