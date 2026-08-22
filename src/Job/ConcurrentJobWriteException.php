<?php
/**
 * Concurrent write exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort

/** Raised when optimistic job persistence detects a stale aggregate. */
final class ConcurrentJobWriteException extends \RuntimeException {}
