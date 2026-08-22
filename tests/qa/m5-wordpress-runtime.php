<?php
/**
 * Self-restoring M5 runtime registration smoke against the local WordPress site.
 *
 * @package SeaTryOn\Tests\QA
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

require_once __DIR__ . '/bootstrap.php';
$qa = sea_tryon_qa_bootstrap( $argv );

$plugin_file = $qa['plugin_file'];
$action_scheduler_was_initialized = did_action( 'action_scheduler_init' ) > 0;
if ( ! $action_scheduler_was_initialized ) {
	do_action( 'action_scheduler_init' );
}
$had_cleanup = function_exists( 'as_has_scheduled_action' )
	? (bool) as_has_scheduled_action( 'sea_tryon_cleanup_jobs', array(), 'sea-tryon' )
	: false;
$errors      = array();

$assert = static function ( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ) use ( &$errors ): bool {
		if ( 0 !== ( error_reporting() & $severity ) ) {
			$errors[] = $severity . ':' . $message . '@' . $file . ':' . $line;
		}
		return false;
	},
	E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
);

try {
	require_once $plugin_file;
	\SeaTryOn\Plugin::instance()->boot();

	$assert( false !== has_action( 'sea_tryon_process_job' ), 'Worker callback was not registered.' );
	$assert( false !== has_action( 'sea_tryon_cleanup_jobs' ), 'Cleanup callback was not registered.' );
	$assert( false !== has_action( 'rest_api_init' ), 'REST registration hook was not registered.' );
	$assert( false !== has_filter( 'wp_privacy_personal_data_exporters' ), 'Privacy exporter was not registered.' );
	$assert( false !== has_filter( 'wp_privacy_personal_data_erasers' ), 'Privacy eraser was not registered.' );
	$assert( false !== has_action( 'woocommerce_after_add_to_cart_form' ), 'Product-page trigger was not registered.' );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	foreach ( array( '/sea-tryon/v1/jobs', '/sea-tryon/v1/jobs/(?P<id>[a-f0-9]{32,128})', '/sea-tryon/v1/jobs/(?P<id>[a-f0-9]{32,128})/result', '/sea-tryon/v1/guest-token' ) as $route ) {
		$assert( isset( $routes[ $route ] ), 'Expected REST route was not discoverable: ' . $route );
	}

	$clock   = new \SeaTryOn\Domain\SystemClock();
	$storage = \SeaTryOn\Storage\WordPressTemporaryStorageFactory::create( new \SeaTryOn\Support\NativeFilesystem(), $clock );
	$root    = wp_normalize_path( $storage->root_path() );
	$public  = wp_normalize_path( isset( $_SERVER['DOCUMENT_ROOT'] ) ? (string) $_SERVER['DOCUMENT_ROOT'] : ABSPATH );
	$assert( 0 !== strpos( strtolower( $root ), trailingslashit( strtolower( $public ) ) ), 'Private storage resolved inside the public document root.' );
	$assert( array() === $errors, 'Runtime emitted PHP warnings, notices, or deprecations: ' . implode( ' | ', $errors ) );

	echo "M5_WORDPRESS_RUNTIME=PASS\n";
} finally {
	restore_error_handler();
	if ( ! $had_cleanup && function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'sea_tryon_cleanup_jobs', array(), 'sea-tryon' );
	}
}
