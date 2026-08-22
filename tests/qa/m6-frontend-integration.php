<?php
/**
 * Self-restoring server-rendered storefront integration for M6.
 *
 * @package SeaTryOn\Tests\QA
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

require_once __DIR__ . '/bootstrap.php';
$qa = sea_tryon_qa_bootstrap( $argv );
require_once ABSPATH . 'wp-admin/includes/post.php';

$plugin_file = $qa['plugin_file'];
$fixture     = __DIR__ . '/fixtures/readable-product.png';
$options     = array( 'sea_tryon_enabled', 'sea_tryon_provider', 'sea_tryon_openai_api_key', 'sea_tryon_allow_guests' );
$snapshots   = array();
$product_id  = 0;
$attachment  = 0;
$errors      = array();
$old_query   = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
$old_post    = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

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
	$assert( is_readable( $fixture ), 'Readable product fixture is missing.' );
	require_once $plugin_file;
	\SeaTryOn\Plugin::instance()->boot();
	if ( 0 === did_action( 'action_scheduler_init' ) ) {
		do_action( 'action_scheduler_init' );
	}

	update_option( 'sea_tryon_enabled', 'yes', false );
	update_option( 'sea_tryon_provider', 'openai', false );
	update_option( 'sea_tryon_openai_api_key', 'test-frontend-provider-placeholder', false );
	update_option( 'sea_tryon_allow_guests', 'no', false );
	wp_set_current_user( 0 );

	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Sea Try-On Frontend Fixture',
			'post_status'    => 'inherit',
		),
		$fixture
	);
	$assert( is_int( $attachment ) && $attachment > 0, 'Temporary attachment could not be created.' );
	update_post_meta( $attachment, '_wp_attached_file', '../plugins/sea-tryon/tests/qa/fixtures/readable-product.png' );

	$product = new WC_Product_Simple();
	$product->set_name( 'Sea Try-On Frontend Product' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '25' );
	$product->set_image_id( $attachment );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_ENABLED, 'yes' );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_EXPERIENCE_TYPE, \SeaTryOn\Domain\ExperienceType::CLOTHING );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_PROMPT, 'Private merchant prompt that must not reach HTML.' );
	$product_id = $product->save();

	$GLOBALS['wp_query'] = new WP_Query(
		array(
			'p'         => $product_id,
			'post_type' => 'product',
		)
	);
	$GLOBALS['post'] = get_post( $product_id );
	setup_postdata( $GLOBALS['post'] );
	$GLOBALS['product'] = wc_get_product( $product_id );
	$assert( is_product(), 'Temporary product query was not recognized as a product page.' );
	$settings = new \SeaTryOn\Settings\SettingsRepository();
	$assert( ( new \SeaTryOn\Security\SecretStore( $settings ) )->is_active_provider_configured(), 'Selected Provider configuration was not readable.' );
	$attached_path = get_attached_file( $attachment, true );
	$assert( is_string( $attached_path ) && is_readable( $attached_path ), 'Attachment path was not readable: ' . ( is_string( $attached_path ) ? $attached_path : 'none' ) );
	$assert( 'yes' === $GLOBALS['product']->get_meta( \SeaTryOn\Admin\Product\ProductFields::META_ENABLED, true ), 'Product enable flag was not persisted.' );
	$assert( $GLOBALS['product']->is_purchasable(), 'Temporary product was not purchasable.' );
	$assert( ( new \SeaTryOn\Frontend\VisibilityRules( $settings ) )->allows( $GLOBALS['product'], true, false ), 'Temporary product did not pass frontend visibility rules.' );

	if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'sea-tryon/virtual-try-on' ) ) {
		( new \SeaTryOn\Frontend\BlockRegistrar() )->register();
	}
	$assert( WP_Block_Type_Registry::get_instance()->is_registered( 'sea-tryon/virtual-try-on' ), 'Dynamic fallback block was not registered.' );

	do_action( 'wp_enqueue_scripts' );
	$assert( wp_script_is( 'sea-tryon-frontend', 'enqueued' ), 'Frontend script was not conditionally enqueued.' );
	$assert( wp_style_is( 'sea-tryon-frontend', 'enqueued' ), 'Frontend style was not conditionally enqueued.' );
	$inline = wp_scripts()->get_data( 'sea-tryon-frontend', 'before' );
	$inline = is_array( $inline ) ? implode( "\n", $inline ) : (string) $inline;
	$assert( false !== strpos( $inline, '"authMode":"required"' ), 'Guest-disabled frontend did not receive login-required mode.' );
	$assert( false === strpos( $inline, 'test-frontend-provider-placeholder' ), 'Provider key leaked into frontend configuration.' );

	ob_start();
	do_action( 'woocommerce_after_add_to_cart_form' );
	do_action( 'woocommerce_after_add_to_cart_form' );
	$trigger = (string) ob_get_clean();
	$assert( 1 === substr_count( $trigger, 'data-sea-tryon-open' ), 'Automatic trigger was missing or duplicated.' );
	$assert( false === strpos( $trigger, 'Private merchant prompt' ), 'Merchant prompt leaked into trigger HTML.' );
	$assert( false !== strpos( $trigger, 'type="button"' ), 'Trigger could submit the add-to-cart form.' );

	ob_start();
	do_action( 'wp_footer' );
	$footer = (string) ob_get_clean();
	$assert( 1 === substr_count( $footer, 'data-sea-tryon-root' ), 'Modal root was missing or duplicated.' );
	$assert( false !== strpos( $footer, 'role="dialog"' ) && false !== strpos( $footer, 'aria-live="polite"' ), 'Accessible dialog semantics were incomplete.' );
	$assert( false !== strpos( $footer, 'data-sea-tryon-login' ) && false !== strpos( $footer, 'data-sea-tryon-workflow' ), 'Login and workflow states were not rendered.' );
	$assert( false !== strpos( $footer, 'data-sea-tryon-file-name' ), 'Selected filename region was not rendered.' );
	$assert( false === strpos( $footer, 'Private merchant prompt' ), 'Merchant prompt leaked into modal HTML.' );

	$assert( array() === $errors, 'Frontend integration emitted PHP warnings, notices, or deprecations: ' . implode( ' | ', $errors ) );
	echo "M6_FRONTEND_INTEGRATION=PASS\n";
} finally {
	restore_error_handler();
	wp_reset_postdata();
	$GLOBALS['wp_query'] = $old_query;
	$GLOBALS['post']     = $old_post;
	unset( $GLOBALS['product'] );
	if ( $product_id > 0 ) {
		wp_delete_post( $product_id, true );
	}
	if ( $attachment > 0 ) {
		// Delete only the attachment post; the committed QA fixture is retained.
		wp_delete_post( $attachment, true );
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
	wp_set_current_user( 0 );
}
