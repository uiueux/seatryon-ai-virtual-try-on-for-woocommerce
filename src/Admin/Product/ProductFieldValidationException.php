<?php
/**
 * Product field validation exception.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Product;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Identifies a stable validation failure without exposing submitted content.
 */
final class ProductFieldValidationException extends InvalidArgumentException {

	public const INVALID_ENABLED       = 'invalid_enabled';
	public const INVALID_UTF8          = 'invalid_utf8';
	public const PROMPT_TOO_LONG       = 'prompt_too_long';
	public const INVALID_EXPERIENCE    = 'invalid_experience';
	public const INVALID_PRODUCT_IMAGE = 'invalid_product_image';
	public const MISSING_PRODUCT_IMAGE = 'missing_product_image';

	/**
	 * Stable failure reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Create an exception for a validation reason.
	 *
	 * @param string $reason Stable reason constant.
	 */
	public function __construct( string $reason ) {
		parent::__construct();
		$this->reason = $reason;
	}

	/**
	 * Return the stable validation reason.
	 */
	public function reason(): string {
		return $this->reason;
	}
}
