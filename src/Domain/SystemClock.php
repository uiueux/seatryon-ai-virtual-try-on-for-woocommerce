<?php
/**
 * UTC system clock.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use DateTimeImmutable;
use DateTimeZone;
use SeaTryOn\Contracts\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Production clock implementation independent of WordPress timezone state.
 */
final class SystemClock implements ClockInterface {

	/** Return the current UTC instant. */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
