<?php
/**
 * Runtime dependency checks.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps WooCommerce-dependent services from loading on unsupported sites.
 */
final class Dependencies {

	/**
	 * Determine whether the supported WooCommerce runtime is loaded.
	 */
	public static function is_satisfied(): bool {
		return self::is_woocommerce_active()
			&& version_compare( self::woocommerce_version(), self::minimum_woocommerce_version(), '>=' );
	}

	/**
	 * Determine whether WooCommerce is active and loaded.
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
	}

	/**
	 * Get the active WooCommerce version, if available.
	 */
	public static function woocommerce_version(): string {
		return defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/**
	 * Get the minimum supported WooCommerce version.
	 */
	public static function minimum_woocommerce_version(): string {
		return defined( 'SEA_TRYON_MINIMUM_WOOCOMMERCE_VERSION' )
			? (string) SEA_TRYON_MINIMUM_WOOCOMMERCE_VERSION
			: '10.9';
	}

	/**
	 * Register notices for administrators who can resolve the dependency issue.
	 */
	public static function register_admin_notices(): void {
		add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );
		add_action( 'network_admin_notices', array( self::class, 'render_admin_notice' ) );
	}

	/**
	 * Render a translatable dependency notice.
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_network_plugins' ) ) {
			return;
		}

		$minimum_version = self::minimum_woocommerce_version();

		if ( ! self::is_woocommerce_active() ) {
			$message = sprintf(
				/* translators: %s: Minimum required WooCommerce version. */
				__( 'AI Virtual Try-On for WooCommerce requires WooCommerce %s or newer. Install and activate WooCommerce to use this plugin.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				$minimum_version
			);
		} else {
			$message = sprintf(
				/* translators: 1: Minimum required WooCommerce version. 2: Active WooCommerce version. */
				__( 'AI Virtual Try-On for WooCommerce requires WooCommerce %1$s or newer. The active version is %2$s.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				$minimum_version,
				self::woocommerce_version()
			);
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
