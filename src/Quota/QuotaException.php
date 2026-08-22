<?php
/**
 * Quota exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when quota state cannot be updated safely.
 */
final class QuotaException extends \RuntimeException {
}
