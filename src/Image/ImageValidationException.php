<?php
/**
 * Image validation exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Image;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a stable, non-sensitive image validation failure.
 */
final class ImageValidationException extends RuntimeException {

	/**
	 * Stable reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Set up a safe validation failure.
	 *
	 * @param string $reason  Stable reason.
	 * @param string $message Safe message.
	 */
	public function __construct( string $reason, string $message ) {
		$this->reason = $reason;
		parent::__construct( $message );
	}

	/** Return the stable validation reason. */
	public function reason(): string {
		return $this->reason;
	}
}
