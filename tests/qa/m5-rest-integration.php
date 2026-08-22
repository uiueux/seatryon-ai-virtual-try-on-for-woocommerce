<?php
/**
 * Self-restoring logged-in REST lifecycle integration for M5.
 *
 * @package SeaTryOn\Tests\QA
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

require_once __DIR__ . '/bootstrap.php';
$qa = sea_tryon_qa_bootstrap( $argv );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$plugin_file = $qa['plugin_file'];
$options     = array( 'sea_tryon_enabled', 'sea_tryon_provider', 'sea_tryon_openai_api_key', 'sea_tryon_allow_guests' );
$snapshots   = array();
$users       = array();
$product_id  = 0;
$attachment  = 0;
$source_file = '';
$job_id      = '';
$owner_user  = 0;
$errors      = array();
$replay_options = array();
$guest_cookie_before = isset( $_COOKIE['sea_tryon_session'] ) ? $_COOKIE['sea_tryon_session'] : null;

global $wpdb;
foreach ( $options as $name ) {
	$snapshots[ $name ] = $wpdb->get_row(
		$wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ),
		ARRAY_A
	);
}

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
	if ( 0 === did_action( 'action_scheduler_init' ) ) {
		do_action( 'action_scheduler_init' );
	}
	do_action( 'rest_api_init' );

	update_option( 'sea_tryon_enabled', 'yes', false );
	update_option( 'sea_tryon_provider', 'openai', false );
	update_option( 'sea_tryon_openai_api_key', 'test-integration-provider-placeholder', false );
	update_option( 'sea_tryon_allow_guests', 'yes', false );

	$owner_user = wp_insert_user(
		array(
			'user_login' => 'sea_tryon_m5_owner_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 24, true ),
			'user_email' => 'sea-tryon-m5-owner-' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	$other_user = wp_insert_user(
		array(
			'user_login' => 'sea_tryon_m5_other_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 24, true ),
			'user_email' => 'sea-tryon-m5-other-' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $owner_user ) && ! is_wp_error( $other_user ), 'Temporary users could not be created.' );
	$owner_user = (int) $owner_user;
	$other_user = (int) $other_user;
	$users      = array( $owner_user, $other_user );

	$canvas = imagecreatetruecolor( 64, 64 );
	$assert( false !== $canvas, 'PNG fixture canvas could not be created.' );
	$color = imagecolorallocate( $canvas, 96, 132, 180 );
	imagefill( $canvas, 0, 0, $color );
	ob_start();
	imagepng( $canvas );
	$image_bytes = ob_get_clean();
	imagedestroy( $canvas );
	$assert( is_string( $image_bytes ) && '' !== $image_bytes, 'PNG fixture could not be encoded.' );
	$upload = wp_upload_bits( 'sea-tryon-m5-product.png', null, $image_bytes );
	$assert( empty( $upload['error'] ) && is_string( $upload['file'] ), 'Product image fixture could not be stored.' );
	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Sea Try-On M5 Fixture',
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	$assert( is_int( $attachment ) && $attachment > 0, 'Product image attachment could not be created.' );

	$product = new WC_Product_Simple();
	$product->set_name( 'Sea Try-On M5 Product' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '25' );
	$product->set_image_id( $attachment );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_ENABLED, 'yes' );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_EXPERIENCE_TYPE, \SeaTryOn\Domain\ExperienceType::CLOTHING );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_PROMPT, 'Keep the garment shape and color accurate.' );
	$product_id = $product->save();
	$assert( $product_id > 0, 'Temporary product could not be created.' );

	$attachment_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
	$source_file      = wp_tempnam( 'sea-tryon-m5-customer.png' );
	$assert( is_string( $source_file ) && false !== file_put_contents( $source_file, $image_bytes ), 'Customer upload fixture could not be created.' );

	wp_set_current_user( $owner_user );
	$create = new WP_REST_Request( 'POST', '/sea-tryon/v1/jobs' );
	$create->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$create->set_param( 'product_id', $product_id );
	$create->set_param( 'consent', true );
	$create->set_param( 'idempotency_key', 'm5integrationkey1234567890' );
	$create->set_file_params(
		array(
			'image' => array(
				'name'     => 'customer.png',
				'tmp_name' => $source_file,
				'size'     => filesize( $source_file ),
				'error'    => UPLOAD_ERR_OK,
				'type'     => 'image/png',
			),
		)
	);
	$created = rest_get_server()->dispatch( $create );
	$assert( 202 === $created->get_status(), 'Create endpoint did not return 202: ' . $created->get_status() . ' ' . wp_json_encode( $created->get_data() ) );
	$created_data = $created->get_data();
	$job_id       = is_array( $created_data ) && isset( $created_data['id'] ) ? (string) $created_data['id'] : '';
	$assert( 1 === preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ), 'Create endpoint did not return an opaque job ID.' );
	$assert( $attachment_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ), 'REST create added an input or result to the Media Library.' );

	$status = new WP_REST_Request( 'GET', '/sea-tryon/v1/jobs/' . $job_id );
	$status->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$assert( 200 === rest_get_server()->dispatch( $status )->get_status(), 'Owner could not read job status.' );

	wp_set_current_user( $other_user );
	$foreign = new WP_REST_Request( 'GET', '/sea-tryon/v1/jobs/' . $job_id );
	$foreign->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$assert( 404 === rest_get_server()->dispatch( $foreign )->get_status(), 'A different user could discover the job.' );

	wp_set_current_user( $owner_user );
	$delete = new WP_REST_Request( 'DELETE', '/sea-tryon/v1/jobs/' . $job_id );
	$delete->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$assert( 204 === rest_get_server()->dispatch( $delete )->get_status(), 'Owner delete did not return 204.' );
	$assert( null === get_option( 'sea_tryon_job_' . $job_id, null ), 'Deleted job metadata remains.' );
	$job_id = '';

	wp_set_current_user( 0 );
	unset( $_COOKIE[ \SeaTryOn\Auth\GuestSessionManager::COOKIE_NAME ] );
	$guest_session = ( new \SeaTryOn\Auth\GuestSessionManager() )->ensure();
	$assert( 43 === strlen( $guest_session ), 'Guest session was not created.' );
	$token_request = new WP_REST_Request( 'POST', '/sea-tryon/v1/guest-token' );
	$token_request->set_header( 'Origin', home_url( '/' ) );
	$token_request->set_param( 'product_id', $product_id );
	$token_response = rest_get_server()->dispatch( $token_request );
	$assert( 200 === $token_response->get_status(), 'Guest token refresh did not return 200.' );
	$guest_tokens = $token_response->get_data();
	$assert( is_array( $guest_tokens ) && isset( $guest_tokens['create'], $guest_tokens['status'], $guest_tokens['delete'] ), 'Guest action tokens were incomplete.' );
	$replay_options[] = 'sea_tryon_replay_' . hash( 'sha256', (string) $guest_tokens['create'] );
	$replay_options[] = 'sea_tryon_replay_' . hash( 'sha256', (string) $guest_tokens['delete'] );

	$guest_create = new WP_REST_Request( 'POST', '/sea-tryon/v1/jobs' );
	$guest_create->set_header( 'Origin', home_url( '/' ) );
	$guest_create->set_header( 'X-Sea-TryOn-Token', (string) $guest_tokens['create'] );
	$guest_create->set_param( 'product_id', $product_id );
	$guest_create->set_param( 'consent', true );
	$guest_create->set_param( 'idempotency_key', 'm5guestintegration1234567890' );
	$guest_create->set_file_params(
		array(
			'image' => array(
				'name'     => 'guest-customer.png',
				'tmp_name' => $source_file,
				'size'     => filesize( $source_file ),
				'error'    => UPLOAD_ERR_OK,
				'type'     => 'image/png',
			),
		)
	);
	$guest_created = rest_get_server()->dispatch( $guest_create );
	$assert( 202 === $guest_created->get_status(), 'Guest create did not return 202.' );
	$guest_data = $guest_created->get_data();
	$job_id     = is_array( $guest_data ) && isset( $guest_data['id'] ) ? (string) $guest_data['id'] : '';
	$assert( '' !== $job_id, 'Guest create returned no job.' );

	$replay = new WP_REST_Request( 'POST', '/sea-tryon/v1/jobs' );
	$replay->set_header( 'Origin', home_url( '/' ) );
	$replay->set_header( 'X-Sea-TryOn-Token', (string) $guest_tokens['create'] );
	$replay->set_param( 'product_id', $product_id );
	$replay->set_param( 'consent', true );
	$replay->set_param( 'idempotency_key', 'm5guestreplay123456789012345' );
	$replay->set_file_params(
		array(
			'image' => array(
				'name'     => 'guest-customer.png',
				'tmp_name' => $source_file,
				'size'     => filesize( $source_file ),
				'error'    => UPLOAD_ERR_OK,
				'type'     => 'image/png',
			),
		)
	);
	$assert( 403 === rest_get_server()->dispatch( $replay )->get_status(), 'Consumed guest create token was replayed.' );

	$guest_status = new WP_REST_Request( 'GET', '/sea-tryon/v1/jobs/' . $job_id );
	$guest_status->set_header( 'Origin', home_url( '/' ) );
	$guest_status->set_header( 'X-Sea-TryOn-Token', (string) $guest_tokens['status'] );
	$assert( 200 === rest_get_server()->dispatch( $guest_status )->get_status(), 'Guest owner could not read job status.' );

	$_COOKIE[ \SeaTryOn\Auth\GuestSessionManager::COOKIE_NAME ] = str_repeat( 'B', 43 );
	$cross_session = new WP_REST_Request( 'GET', '/sea-tryon/v1/jobs/' . $job_id );
	$cross_session->set_header( 'Origin', home_url( '/' ) );
	$cross_session->set_header( 'X-Sea-TryOn-Token', (string) $guest_tokens['status'] );
	$assert( 403 === rest_get_server()->dispatch( $cross_session )->get_status(), 'A different guest session reused the owner token.' );
	$_COOKIE[ \SeaTryOn\Auth\GuestSessionManager::COOKIE_NAME ] = $guest_session;

	$guest_delete = new WP_REST_Request( 'DELETE', '/sea-tryon/v1/jobs/' . $job_id );
	$guest_delete->set_header( 'Origin', home_url( '/' ) );
	$guest_delete->set_header( 'X-Sea-TryOn-Token', (string) $guest_tokens['delete'] );
	$assert( 204 === rest_get_server()->dispatch( $guest_delete )->get_status(), 'Guest owner delete did not return 204.' );
	$job_id = '';
	$assert( $attachment_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ), 'Guest flow added an input or result to the Media Library.' );

	$assert( array() === $errors, 'REST lifecycle emitted PHP warnings, notices, or deprecations: ' . implode( ' | ', $errors ) );
	echo "M5_REST_INTEGRATION=PASS\n";
} finally {
	restore_error_handler();

	if ( '' !== $job_id ) {
		try {
			$clock      = new \SeaTryOn\Domain\SystemClock();
			$storage    = \SeaTryOn\Storage\WordPressTemporaryStorageFactory::create( new \SeaTryOn\Support\NativeFilesystem(), $clock );
			$lock       = \SeaTryOn\Job\WordPressJobLockFactory::create( $clock );
			$repository = new \SeaTryOn\Job\WordPressJobRepository( $lock );
			$cleanup    = new \SeaTryOn\Job\JobCleanupService( $repository, $clock, $storage, new \SeaTryOn\Scheduler\JobScheduler(), null, $lock );
			$cleanup->delete_job( $job_id );
		} catch ( Throwable $exception ) {
			unset( $exception );
		}
	}
	if ( '' !== $source_file && is_file( $source_file ) ) {
		wp_delete_file( $source_file );
	}
	if ( $product_id > 0 ) {
		wp_delete_post( $product_id, true );
	}
	if ( $attachment > 0 ) {
		wp_delete_attachment( $attachment, true );
	}
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $snapshots as $name => $row ) {
		if ( null === $row ) {
			delete_option( $name );
			continue;
		}
		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => $row['option_name'],
				'option_value' => $row['option_value'],
				'autoload'     => $row['autoload'],
			),
			array( '%s', '%s', '%s' )
		);
		wp_cache_delete( $name, 'options' );
	}
	foreach ( $replay_options as $replay_option ) {
		delete_option( $replay_option );
	}
	if ( null === $guest_cookie_before ) {
		unset( $_COOKIE[ \SeaTryOn\Auth\GuestSessionManager::COOKIE_NAME ] );
	} else {
		$_COOKIE[ \SeaTryOn\Auth\GuestSessionManager::COOKIE_NAME ] = $guest_cookie_before;
	}
	wp_set_current_user( 0 );
}
