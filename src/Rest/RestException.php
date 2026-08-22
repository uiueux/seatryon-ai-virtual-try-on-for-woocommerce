<?php
/**
 * Stable REST application exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing

final class RestException extends RuntimeException {
	/** @var string */ private $error_code;
	/** @var int */ private $http_status;
	/** @var array<string,mixed> */ private $error_data;
	/** @var string */ private $diagnostic_code;
	/**
	 * Create a safe application exception.
	 *
	 * @param string              $error_code  Stable public error code.
	 * @param string              $message     Translated public message.
	 * @param int                 $http_status HTTP response status.
	 * @param array<string,mixed> $error_data      Additional public fields.
	 * @param string              $diagnostic_code Private log-safe diagnostic code.
	 */
	public function __construct( string $error_code, string $message, int $http_status, array $error_data = array(), string $diagnostic_code = '' ) {
		parent::__construct( $message );
		$this->error_code      = $error_code;
		$this->http_status     = $http_status;
		$this->error_data      = $error_data;
		$this->diagnostic_code = $diagnostic_code;
	}
	public function error_code(): string {
		return $this->error_code; }
	public function http_status(): int {
		return $this->http_status; }
	/** @return array<string,mixed> */
	public function error_data(): array {
		return $this->error_data; }
	public function diagnostic_code(): string {
		return $this->diagnostic_code; }
}
