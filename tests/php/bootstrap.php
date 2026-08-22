<?php
/**
 * PHPUnit bootstrap.
 *
 * @package SeaTryOn\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR );
}

$sea_tryon_test_autoloader = dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! is_readable( $sea_tryon_test_autoloader ) ) {
	throw new RuntimeException( 'Composer dependencies are required. Run composer install before PHPUnit.' );
}

require_once $sea_tryon_test_autoloader;

