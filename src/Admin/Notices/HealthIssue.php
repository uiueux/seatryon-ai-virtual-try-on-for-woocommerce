<?php
/**
 * Administrative health issue value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Carries a stable issue code and non-secret display context.
 */
final class HealthIssue {

	public const WORDPRESS_AI_UNAVAILABLE = 'wordpress_ai_unavailable';
	public const MISSING_SEAAI_KEY        = 'missing_seaai_key';
	public const MISSING_SEAAI_URL        = 'missing_seaai_url';
	public const STORAGE_UNAVAILABLE      = 'storage_unavailable';
	public const STORAGE_PUBLIC           = 'storage_public';
	public const WOOCOMMERCE_MISSING      = 'woocommerce_missing';
	public const WOOCOMMERCE_TOO_OLD      = 'woocommerce_too_old';

	public const AUDIENCE_STORE_MANAGER  = 'store_manager';
	public const AUDIENCE_PLUGIN_MANAGER = 'plugin_manager';

	/**
	 * Stable issue code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Intended administrative audience.
	 *
	 * @var string
	 */
	private $audience;

	/**
	 * Non-secret message context.
	 *
	 * @var array<string,string>
	 */
	private $context;

	/**
	 * Create an issue.
	 *
	 * @param string               $code     Stable issue code.
	 * @param string               $audience Administrative audience.
	 * @param array<string,string> $context  Non-secret message context.
	 */
	public function __construct( string $code, string $audience, array $context = array() ) {
		$this->code     = $code;
		$this->audience = $audience;
		$this->context  = $context;
	}

	/**
	 * Return the stable issue code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Return the intended administrative audience.
	 */
	public function audience(): string {
		return $this->audience;
	}

	/**
	 * Return a context value without notices.
	 *
	 * @param string $name Context key.
	 */
	public function context( string $name ): string {
		return isset( $this->context[ $name ] ) ? $this->context[ $name ] : '';
	}
}
