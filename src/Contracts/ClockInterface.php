<?php
/**
 * Clock contract for deterministic domain services.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Contracts;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the current instant without coupling domain code to the system clock.
 */
interface ClockInterface {

	/**
	 * Return the current instant.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable;
}
