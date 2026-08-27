<?php
/**
 * Plugin Name: SeaTryon – AI Virtual Try-On for WooCommerce
 * Description: Generate AI-powered virtual try-on and product placement previews on WooCommerce product pages.
 * Version: 1.1.2
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: SeaTheme
 * Text Domain: seatryon-ai-virtual-try-on-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 10.9
 * WC tested up to: 10.9
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SeaTryOn
 */

/**
 * Plugin bootstrap.
 *
 * @package SeaTryOn
 */

defined( 'ABSPATH' ) || exit;

define( 'SEA_TRYON_VERSION', '1.1.2' );
define( 'SEA_TRYON_MINIMUM_WORDPRESS_VERSION', '7.0' );
define( 'SEA_TRYON_MINIMUM_WOOCOMMERCE_VERSION', '10.9' );
define( 'SEA_TRYON_FILE', __FILE__ );
define( 'SEA_TRYON_BASENAME', plugin_basename( SEA_TRYON_FILE ) );
define( 'SEA_TRYON_PATH', plugin_dir_path( SEA_TRYON_FILE ) );
define( 'SEA_TRYON_URL', plugin_dir_url( SEA_TRYON_FILE ) );

$sea_tryon_composer_autoloader = SEA_TRYON_PATH . 'vendor/autoload.php';

if ( is_readable( $sea_tryon_composer_autoloader ) ) {
	require_once $sea_tryon_composer_autoloader;
} else {
	/**
	 * First-party PSR-4 fallback used when Composer's autoloader is unavailable.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	spl_autoload_register(
		static function ( $class_name ) {
			$namespace_prefix = 'SeaTryOn\\';

			if ( 0 !== strpos( $class_name, $namespace_prefix ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $namespace_prefix ) );

			if ( false === $relative_class || '' === $relative_class ) {
				return;
			}

			$class_file = SEA_TRYON_PATH . 'src/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

			if ( is_readable( $class_file ) ) {
				require_once $class_file;
			}
		}
	);
}

register_activation_hook( SEA_TRYON_FILE, array( SeaTryOn\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( SEA_TRYON_FILE, array( SeaTryOn\Lifecycle\Deactivator::class, 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				SEA_TRYON_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		SeaTryOn\Plugin::instance()->boot();
	},
	20
);
