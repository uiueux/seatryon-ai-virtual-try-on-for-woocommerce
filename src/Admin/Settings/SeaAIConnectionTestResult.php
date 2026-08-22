<?php
/**
 * SeaAI connection test result.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Carries only safe, displayable diagnostic data back to the settings UI.
 */
final class SeaAIConnectionTestResult {

	/**
	 * Whether authentication was verified.
	 *
	 * @var bool
	 */
	private $success;

	/**
	 * Stable diagnostic code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Safe merchant-facing message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * AJAX response status.
	 *
	 * @var int
	 */
	private $http_status;

	/**
	 * Set up the safe result value.
	 *
	 * @param bool   $success     Whether authentication was verified.
	 * @param string $code        Stable diagnostic code.
	 * @param string $message     Safe translated merchant message.
	 * @param int    $http_status AJAX response status.
	 */
	public function __construct( bool $success, string $code, string $message, int $http_status = 200 ) {
		$this->success     = $success;
		$this->code        = $code;
		$this->message     = $message;
		$this->http_status = $http_status;
	}

	/** Whether the test passed. */
	public function is_success(): bool {
		return $this->success;
	}

	/** Stable diagnostic code. */
	public function code(): string {
		return $this->code;
	}

	/** Safe merchant-facing message. */
	public function message(): string {
		return $this->message;
	}

	/** AJAX response status. */
	public function http_status(): int {
		return $this->http_status;
	}

	/**
	 * Return the public response payload without credentials or remote bodies.
	 *
	 * @return array{code:string,message:string}
	 */
	public function payload(): array {
		return array(
			'code'    => $this->code,
			'message' => $this->message,
		);
	}
}
