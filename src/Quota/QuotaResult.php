<?php
/**
 * Quota consumption result.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result returned by a provider-dispatch quota attempt.
 */
final class QuotaResult {

	/**
	 * Whether dispatch may proceed.
	 *
	 * @var bool
	 */
	private $allowed;

	/**
	 * Whether a unit was newly consumed.
	 *
	 * @var bool
	 */
	private $consumed;

	/**
	 * Remaining units.
	 *
	 * @var int
	 */
	private $remaining;

	/**
	 * Next reset time.
	 *
	 * @var DateTimeImmutable
	 */
	private $resets_at;

	/**
	 * Initialize the result.
	 *
	 * @param bool              $allowed   Whether dispatch may proceed.
	 * @param bool              $consumed  Whether a unit was newly consumed.
	 * @param int               $remaining Remaining units.
	 * @param DateTimeImmutable $resets_at Next reset time.
	 */
	public function __construct( bool $allowed, bool $consumed, int $remaining, DateTimeImmutable $resets_at ) {
		$this->allowed   = $allowed;
		$this->consumed  = $consumed;
		$this->remaining = max( 0, $remaining );
		$this->resets_at = $resets_at;
	}

	/** Whether the provider dispatch may proceed. */
	public function is_allowed(): bool {
		return $this->allowed;
	}

	/** Whether this call newly consumed one unit. */
	public function was_consumed(): bool {
		return $this->consumed;
	}

	/** Units remaining after this operation. */
	public function remaining(): int {
		return $this->remaining;
	}

	/** Next reset instant in the configured site timezone. */
	public function resets_at(): DateTimeImmutable {
		return $this->resets_at;
	}
}
