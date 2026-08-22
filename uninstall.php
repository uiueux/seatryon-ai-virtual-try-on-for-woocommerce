<?php
/**
 * Sea Try-On uninstall entrypoint.
 *
 * @package SeaTryOn
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sea_tryon_uninstall_autoloader = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $sea_tryon_uninstall_autoloader ) ) {
	require_once $sea_tryon_uninstall_autoloader;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'SeaTryOn\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$file = __DIR__ . '/src/' . str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

SeaTryOn\Lifecycle\Uninstaller::uninstall();
