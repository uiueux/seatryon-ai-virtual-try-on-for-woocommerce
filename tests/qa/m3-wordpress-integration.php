<?php
/**
 * Destructive-but-self-restoring M3 integration smoke for a local WordPress site.
 *
 * Run from CLI only. Every option and temporary post/user is restored or removed
 * in the finally block, including when an assertion fails.
 *
 * @package SeaTryOn\Tests\QA
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

define( 'WP_ADMIN', true );

require_once __DIR__ . '/bootstrap.php';
$qa = sea_tryon_qa_bootstrap( $argv );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$plugin_file = $qa['plugin_file'];
$option_names = array(
	'sea_tryon_enabled',
	'sea_tryon_provider',
	'sea_tryon_openai_api_key',
	'sea_tryon_seaai_base_url',
	'sea_tryon_seaai_api_key',
	'sea_tryon_openai_quality',
	'sea_tryon_seaai_quality',
	'sea_tryon_allow_guests',
	'sea_tryon_logged_in_daily_limit',
	'sea_tryon_guest_daily_limit',
	'sea_tryon_debug_mode',
	'sea_tryon_success_count',
	'woocommerce_meta_box_errors',
);

$option_snapshot = array();
foreach ( $option_names as $option_name ) {
	$option_snapshot[ $option_name ] = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
			$option_name
		),
		ARRAY_A
	);
}

$active_plugins_before = get_option( 'active_plugins', array() );
$temporary_users       = array();
$temporary_products    = array();
$captured_errors       = array();

$assert = static function ( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ) use ( &$captured_errors ): bool {
		if ( 0 !== ( error_reporting() & $severity ) ) {
			$captured_errors[] = $severity . ':' . $message . '@' . $file . ':' . $line;
		}
		return false;
	},
	E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
);

try {
	require_once $plugin_file;
	\SeaTryOn\Plugin::instance()->boot();

	$assert( false !== has_filter( 'woocommerce_get_sections_products' ), 'Settings section hook was not registered.' );
	$assert( false !== has_filter( 'woocommerce_get_settings_products' ), 'Settings fields hook was not registered.' );
	$assert( false !== has_action( 'woocommerce_product_options_advanced' ), 'Classic product render hook was not registered.' );
	$assert( false !== has_action( 'woocommerce_admin_process_product_object' ), 'Classic product save hook was not registered.' );
	$assert( false !== has_action( 'admin_post_sea_tryon_reset_statistics' ), 'Statistics reset hook was not registered.' );
	$assert( false !== has_action( 'admin_notices' ), 'Admin notice hook was not registered.' );

	$administrator_id = wp_insert_user(
		array(
			'user_login' => 'sea_tryon_qa_admin_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 24, true ),
			'user_email' => 'sea-tryon-qa-admin-' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => 'administrator',
		)
	);
	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'sea_tryon_qa_sub_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 24, true ),
			'user_email' => 'sea-tryon-qa-sub-' . wp_generate_password( 8, false ) . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $administrator_id ) && ! is_wp_error( $subscriber_id ), 'Temporary users could not be created.' );
	$temporary_users = array( (int) $administrator_id, (int) $subscriber_id );

	wp_set_current_user( (int) $administrator_id );
	$assert( current_user_can( 'manage_woocommerce' ), 'Administrator lacks manage_woocommerce in the real site.' );

	update_option( 'sea_tryon_openai_api_key', 'qa-openai-secret', false );
	update_option( 'sea_tryon_seaai_api_key', 'qa-seaai-secret', false );
	update_option( 'sea_tryon_seaai_base_url', 'https://gateway.example/wp-json/seaai/v1', false );
	update_option( 'sea_tryon_provider', 'openai', false );
	update_option( 'sea_tryon_enabled', 'yes', false );
	update_option( 'sea_tryon_success_count', 17, false );

	$page   = new \SeaTryOn\Admin\Settings\SettingsPage();
	$fields = $page->build_settings();
	ob_start();
	\WC_Admin_Settings::output_fields( $fields );
	$settings_html = (string) ob_get_clean();
	$assert( false === strpos( $settings_html, 'qa-openai-secret' ), 'OpenAI key leaked in settings HTML.' );
	$assert( false === strpos( $settings_html, 'qa-seaai-secret' ), 'SeaAI key leaked in settings HTML.' );
	$assert( false !== strpos( $settings_html, \SeaTryOn\Security\SecretStore::MASK ), 'Stored keys were not masked.' );

	$data = array(
		'sea_tryon_enabled'               => 'yes',
		'sea_tryon_provider'              => 'openai',
		'sea_tryon_openai_api_key'        => '',
		'sea_tryon_openai_quality'        => 'high',
		'sea_tryon_seaai_base_url'        => 'https://gateway.example/wp-json/seaai/v1',
		'sea_tryon_seaai_api_key'         => \SeaTryOn\Security\SecretStore::MASK,
		'sea_tryon_seaai_quality'         => 'low',
		'sea_tryon_allow_guests'          => 'yes',
		'sea_tryon_logged_in_daily_limit' => '101',
		'sea_tryon_guest_daily_limit'     => '0',
		'sea_tryon_debug_mode'            => 'no',
	);
	\WC_Admin_Settings::save_fields( $fields, $data );
	$assert( 'qa-openai-secret' === get_option( 'sea_tryon_openai_api_key' ), 'Blank OpenAI key submission erased the stored key.' );
	$assert( 'qa-seaai-secret' === get_option( 'sea_tryon_seaai_api_key' ), 'Masked hidden SeaAI key submission polluted the stored key.' );
	$assert( '100' === get_option( 'sea_tryon_logged_in_daily_limit' ), 'Logged-in daily limit was not clamped.' );
	$assert( '1' === get_option( 'sea_tryon_guest_daily_limit' ), 'Guest daily limit was not clamped.' );
	$assert( 'qa-openai-secret' === ( new \SeaTryOn\Security\SecretStore() )->get_active_api_key(), 'Selected provider did not isolate the active key.' );

	$openai_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'sea_tryon_openai_api_key' ) );
	$seaai_autoload  = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'sea_tryon_seaai_api_key' ) );
	$assert( in_array( $openai_autoload, array( 'no', 'off' ), true ), 'OpenAI key option is autoloaded.' );
	$assert( in_array( $seaai_autoload, array( 'no', 'off' ), true ), 'SeaAI key option is autoloaded.' );

	$controller = new \SeaTryOn\Admin\Settings\StatisticsController();
	$_REQUEST['_wpnonce'] = wp_create_nonce( \SeaTryOn\Admin\Settings\StatisticsController::NONCE_ACTION );
	$controller->process_reset();
	$assert( 0 === (int) get_option( 'sea_tryon_success_count' ), 'Authorized statistics reset failed.' );
	$assert( 'openai' === get_option( 'sea_tryon_provider' ), 'Statistics reset altered configuration.' );

	update_option( 'sea_tryon_success_count', 23, false );
	wp_set_current_user( (int) $subscriber_id );
	$assert( ! current_user_can( 'manage_woocommerce' ), 'Subscriber unexpectedly has manage_woocommerce.' );
	$old_die_handler = static function () {
		return static function ( $message = '' ): void {
			throw new RuntimeException( 'EXPECTED_WP_DIE:' . wp_strip_all_tags( (string) $message ) );
		};
	};
	add_filter( 'wp_die_handler', $old_die_handler );
	$unauthorized_rejected = false;
	try {
		$controller->process_reset();
	} catch ( RuntimeException $exception ) {
		$unauthorized_rejected = 0 === strpos( $exception->getMessage(), 'EXPECTED_WP_DIE:' );
	}
	remove_filter( 'wp_die_handler', $old_die_handler );
	$assert( $unauthorized_rejected, 'Unauthorized statistics reset was not rejected.' );
	$assert( 23 === (int) get_option( 'sea_tryon_success_count' ), 'Unauthorized statistics reset mutated the count.' );

	$provider_before_unauthorized_save = get_option( 'sea_tryon_provider' );
	$GLOBALS['current_tab']            = 'products';
	$_POST                             = array( 'save' => 'Save changes', 'sea_tryon_provider' => 'seaai' );
	$_REQUEST                          = array( '_wpnonce' => wp_create_nonce( 'woocommerce-settings' ) );
	add_filter( 'wp_die_handler', $old_die_handler );
	$unauthorized_settings_rejected = false;
	try {
		\WC_Admin_Settings::save();
	} catch ( RuntimeException $exception ) {
		$unauthorized_settings_rejected = 0 === strpos( $exception->getMessage(), 'EXPECTED_WP_DIE:' );
	}
	remove_filter( 'wp_die_handler', $old_die_handler );
	$assert( $unauthorized_settings_rejected, 'Unauthorized WooCommerce settings save was not rejected.' );
	$assert( $provider_before_unauthorized_save === get_option( 'sea_tryon_provider' ), 'Unauthorized settings save mutated provider configuration.' );

	$settings_repository = new \SeaTryOn\Settings\SettingsRepository();
	$notice_renderer      = new \SeaTryOn\Admin\Notices\NoticeRenderer(
		new \SeaTryOn\Admin\Notices\WordPressHealthProbe(
			$settings_repository,
			new \SeaTryOn\Security\SecretStore( $settings_repository ),
			new \SeaTryOn\Admin\Notices\WordPressSystemStatus(
				new \SeaTryOn\Support\NativeFilesystem(),
				new \SeaTryOn\Domain\SystemClock()
			)
		),
		new \SeaTryOn\Admin\Notices\WordPressCapabilityChecker(),
		new \SeaTryOn\Admin\Notices\WordPressDependencyNoticeRegistry()
	);
	ob_start();
	$notice_renderer->render();
	$subscriber_notices = (string) ob_get_clean();
	$assert( false === strpos( $subscriber_notices, 'selected OpenAI provider' ), 'Unauthorized subscriber saw configuration diagnostics.' );

	wp_set_current_user( (int) $administrator_id );
	$product_fields = new \SeaTryOn\Admin\Product\ProductFields();
	$readable_image_id = 0;
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 50, 'fields' => 'ids' ) ) as $attachment_id ) {
		$attached_file = get_attached_file( $attachment_id );
		if ( wp_attachment_is_image( $attachment_id ) && is_string( $attached_file ) && is_file( $attached_file ) && is_readable( $attached_file ) ) {
			$readable_image_id = (int) $attachment_id;
			break;
		}
	}
	$assert( 0 !== $readable_image_id, 'The local site has no readable image attachment for the real product-image smoke.' );
	foreach ( array( 'simple', 'variable' ) as $product_type ) {
		$product = 'variable' === $product_type ? new \WC_Product_Variable() : new \WC_Product_Simple();
		$product->set_name( 'Sea Try-On QA ' . $product_type );
		$product->set_status( 'draft' );
		$product->set_image_id( $readable_image_id );
		$product_id = $product->save();
		$temporary_products[] = $product_id;

		$_POST = array(
			'woocommerce_meta_nonce'              => wp_create_nonce( 'woocommerce_save_data' ),
			'_sea_tryon_enabled'                  => 'yes',
			'_sea_tryon_prompt'                   => 'Keep the <strong>product</strong> accurate.',
			'_sea_tryon_experience_type'          => 'furniture',
		);
		$product_fields->save( $product );
		$product->save();
		$reloaded = wc_get_product( $product_id );
		$assert( $reloaded instanceof \WC_Product, ucfirst( $product_type ) . ' product could not be reloaded.' );
		$assert( 'yes' === $reloaded->get_meta( '_sea_tryon_enabled', true ), ucfirst( $product_type ) . ' enabled meta did not persist.' );
		$assert( 'Keep the product accurate.' === $reloaded->get_meta( '_sea_tryon_prompt', true ), ucfirst( $product_type ) . ' prompt was not sanitized/persisted.' );
		$assert( 'furniture' === $reloaded->get_meta( '_sea_tryon_experience_type', true ), ucfirst( $product_type ) . ' experience meta did not persist.' );

		$GLOBALS['post'] = get_post( $product_id );
		ob_start();
		$product_fields->render();
		$product_html = (string) ob_get_clean();
		$assert( false !== strpos( $product_html, 'Enable Virtual Try-On' ), ucfirst( $product_type ) . ' fields did not render in the Classic editor.' );
		$assert( false !== strpos( $product_html, 'Keep the product accurate.' ), ucfirst( $product_type ) . ' stored prompt did not render.' );
	}

	$invalid_product = new \WC_Product_Simple();
	$invalid_product->set_name( 'Sea Try-On QA invalid' );
	$invalid_product->set_status( 'draft' );
	$invalid_product_id = $invalid_product->save();
	$temporary_products[] = $invalid_product_id;
	\WC_Admin_Meta_Boxes::$meta_box_errors = array();
	$_POST = array(
		'woocommerce_meta_nonce'     => wp_create_nonce( 'woocommerce_save_data' ),
		'_sea_tryon_enabled'         => 'yes',
		'_sea_tryon_prompt'          => 'Valid prompt but no product image.',
		'_sea_tryon_experience_type' => 'auto',
	);
	$product_fields->save( $invalid_product );
	$assert( '' === $invalid_product->get_meta( '_sea_tryon_enabled', true ), 'Product without an image was enabled.' );
	$assert( ! empty( \WC_Admin_Meta_Boxes::$meta_box_errors ), 'Missing product image did not produce an inline error.' );

	update_option( 'sea_tryon_enabled', 'yes', false );
	update_option( 'sea_tryon_provider', 'openai', false );
	delete_option( 'sea_tryon_openai_api_key' );
	ob_start();
	$notice_renderer->render();
	$admin_notices = (string) ob_get_clean();
	$assert( false !== strpos( $admin_notices, 'selected OpenAI provider' ), 'Authorized manager did not see incomplete provider diagnostics.' );
	$assert( false === strpos( $admin_notices, 'qa-openai-secret' ), 'Diagnostic notice leaked an API key.' );

	$assert( $active_plugins_before === get_option( 'active_plugins', array() ), 'Active plugins changed during integration smoke.' );
	$assert( array() === $captured_errors, 'Plugin paths emitted warning/notice/deprecation: ' . implode( ' | ', $captured_errors ) );

	echo "M3_WORDPRESS_INTEGRATION=PASS\n";
} finally {
	restore_error_handler();
	$_POST    = array();
	$_REQUEST = array();

	if ( class_exists( 'WC_Admin_Meta_Boxes', false ) ) {
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
	}

	foreach ( array_reverse( $temporary_products ) as $product_id ) {
		wp_delete_post( $product_id, true );
	}
	foreach ( array_reverse( $temporary_users ) as $user_id ) {
		wp_delete_user( $user_id );
	}

	foreach ( $option_snapshot as $option_name => $row ) {
		delete_option( $option_name );
		if ( is_array( $row ) ) {
			$wpdb->replace(
				$wpdb->options,
				array(
					'option_id'    => (int) $row['option_id'],
					'option_name'  => $row['option_name'],
					'option_value' => $row['option_value'],
					'autoload'     => $row['autoload'],
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}
		wp_cache_delete( $option_name, 'options' );
	}

	wp_set_current_user( 0 );
}
