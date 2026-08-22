<?php
/**
 * Mutable test clock.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Support;

use DateTimeImmutable;
use SeaTryOn\Contracts\ClockInterface;

/**
 * Supplies deterministic instants to unit tests.
 */
final class MutableClock implements ClockInterface {

	/** @var DateTimeImmutable */
	private $now;

	/** Initialize the clock. */
	public function __construct( DateTimeImmutable $now ) {
		$this->now = $now;
	}

	/** {@inheritDoc} */
	public function now(): DateTimeImmutable {
		return $this->now;
	}

	/** Move the clock to a new instant. */
	public function set( DateTimeImmutable $now ): void {
		$this->now = $now;
	}
}
