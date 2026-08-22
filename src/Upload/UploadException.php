<?php
/**
 * Uploaded image exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionComment.ParamCommentFullStop

/** Carries a stable upload error and status. */
final class UploadException extends RuntimeException {

	/** @var string */
	private $error_code;

	/** @var int */
	private $http_status;

	/** @var string */
	private $diagnostic_code;

	/** @param string $error_code Public code. @param string $message Safe message. @param int $http_status HTTP status. @param string $diagnostic_code Private log-safe diagnostic code. */
	public function __construct( string $error_code, string $message, int $http_status, string $diagnostic_code = '' ) {
		parent::__construct( $message );
		$this->error_code      = $error_code;
		$this->http_status     = $http_status;
		$this->diagnostic_code = $diagnostic_code;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	public function diagnostic_code(): string {
		return $this->diagnostic_code;
	}
}
