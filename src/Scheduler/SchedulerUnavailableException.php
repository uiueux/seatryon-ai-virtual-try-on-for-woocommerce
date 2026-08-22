<?php
/**
 * Scheduler unavailable exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Scheduler;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing

final class SchedulerUnavailableException extends \RuntimeException {}
