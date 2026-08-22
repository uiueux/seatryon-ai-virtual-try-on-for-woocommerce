<?php
/**
 * REST authentication exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort

/** Carries a stable public error without exposing authentication material. */
final class AuthException extends RuntimeException {

	/** @var string */
	private $error_code;

	/** @var int */
	private $http_status;

	/**
	 * @param string $error_code  Stable public code.
	 * @param string $message     Safe English message.
	 * @param int    $http_status HTTP response status.
	 */
	public function __construct( string $error_code, string $message, int $http_status ) {
		parent::__construct( $message );
		$this->error_code  = $error_code;
		$this->http_status = $http_status;
	}

	/** Return the stable public code. */
	public function error_code(): string {
		return $this->error_code;
	}

	/** Return the HTTP status. */
	public function http_status(): int {
		return $this->http_status;
	}
}
