<?php
/**
 * Standalone plugin loading smoke check for development environments.
 *
 * Usage: php tests/php/runtime-smoke.php missing|old|supported
 *
 * @package SeaTryOn\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$sea_tryon_test_mode = isset( $argv[1] ) ? $argv[1] : 'missing';

if ( ! in_array( $sea_tryon_test_mode, array( 'missing', 'old', 'supported' ), true ) ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR );

/**
 * Stub plugin_basename().
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_basename( $file ) {
	return basename( $file );
}

/**
 * Stub plugin_dir_path().
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_dir_path( $file ) {
	return dirname( $file ) . DIRECTORY_SEPARATOR;
}

/**
 * Stub plugin_dir_url().
 *
 * @return string
 */
function plugin_dir_url() {
	return 'https://example.test/plugins/sea-tryon/';
}

/**
 * Capture the activation callback.
 *
 * @param string   $file     Plugin file.
 * @param callable $callback Activation callback.
 */
function register_activation_hook( $file, $callback ) {
	unset( $file );
	$GLOBALS['sea_tryon_test_activation_callback'] = $callback;
}

/**
 * Capture the deactivation callback.
 *
 * @param string   $file     Plugin file.
 * @param callable $callback Deactivation callback.
 */
function register_deactivation_hook( $file, $callback ) {
	unset( $file );
	$GLOBALS['sea_tryon_test_deactivation_callback'] = $callback;
}

/**
 * Capture registered actions.
 *
 * @param string   $hook          Hook name.
 * @param callable $callback      Hook callback.
 * @param int      $priority      Hook priority.
 * @param int      $accepted_args Accepted argument count.
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['sea_tryon_test_actions'][ $hook ][] = $callback;
}

/**
 * Capture fired actions.
 *
 * @param string $hook Hook name.
 */
function do_action( $hook ) {
	$GLOBALS['sea_tryon_test_fired'][] = $hook;
}

/**
 * Capture an option created during activation.
 *
 * @param string $option     Option name.
 * @param mixed  $value      Option value.
 * @param string $deprecated Deprecated argument.
 * @param bool   $autoload   Whether WordPress should autoload the option.
 * @return bool
 */
function add_option( $option, $value = '', $deprecated = '', $autoload = true ) {
	unset( $deprecated );
	$GLOBALS['sea_tryon_test_options'][ $option ] = array(
		'value'    => $value,
		'autoload' => $autoload,
	);

	return true;
}

if ( 'missing' !== $sea_tryon_test_mode ) {
	/**
	 * WooCommerce runtime marker.
	 */
	class WooCommerce {
	}

	define( 'WC_VERSION', 'old' === $sea_tryon_test_mode ? '10.8.9' : '10.9.4' );
}

require dirname( __DIR__, 2 ) . '/sea-tryon.php';

foreach ( $GLOBALS['sea_tryon_test_actions']['plugins_loaded'] as $sea_tryon_test_callback ) {
	call_user_func( $sea_tryon_test_callback );
}

if (
	! is_callable( $GLOBALS['sea_tryon_test_activation_callback'] )
	|| ! is_callable( $GLOBALS['sea_tryon_test_deactivation_callback'] )
) {
	exit( 2 );
}

if ( empty( $GLOBALS['sea_tryon_test_actions']['before_woocommerce_init'] ) ) {
	exit( 6 );
}

call_user_func( $GLOBALS['sea_tryon_test_activation_callback'], false );
call_user_func( $GLOBALS['sea_tryon_test_deactivation_callback'] );

if (
	empty( $GLOBALS['sea_tryon_test_options']['sea_tryon_data_version'] )
	|| false !== $GLOBALS['sea_tryon_test_options']['sea_tryon_data_version']['autoload']
) {
	exit( 5 );
}

if (
	'supported' === $sea_tryon_test_mode
	&& ! in_array( 'sea_tryon_loaded', $GLOBALS['sea_tryon_test_fired'], true )
) {
	exit( 3 );
}

if (
	'supported' !== $sea_tryon_test_mode
	&& empty( $GLOBALS['sea_tryon_test_actions']['admin_notices'] )
) {
	exit( 4 );
}

echo 'Runtime smoke ' . $sea_tryon_test_mode . ": OK\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
