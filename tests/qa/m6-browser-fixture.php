<?php
/**
 * Set up or remove a self-restoring browser fixture for M6.
 *
 * @package SeaTryOn\Tests\QA
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

require_once __DIR__ . '/bootstrap.php';
$qa = sea_tryon_qa_bootstrap( $argv );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$mode        = isset( $argv[1] ) ? (string) $argv[1] : '';
$theme_slug  = isset( $argv[2] ) ? sanitize_key( (string) $argv[2] ) : '';
$state_name  = 'sea_tryon_qa_m6_browser_state';
$plugin      = 'sea-tryon/sea-tryon.php';
$option_names = array(
	'sea_tryon_enabled',
	'sea_tryon_provider',
	'sea_tryon_openai_api_key',
	'sea_tryon_allow_guests',
	'sea_tryon_data_version',
	'woocommerce_coming_soon',
	'woocommerce_store_pages_only',
	'template',
	'stylesheet',
);
global $wpdb;

if ( 'setup' === $mode ) {
	if ( null !== get_option( $state_name, null ) ) {
		throw new RuntimeException( 'M6 browser fixture already exists.' );
	}
	$options = array();
	foreach ( $option_names as $name ) {
		$options[ $name ] = $wpdb->get_row(
			$wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ),
			ARRAY_A
		);
	}
	$active = get_option( 'active_plugins', array() );
	if ( ! in_array( $plugin, $active, true ) ) {
		$result = activate_plugin( $plugin, '', false, true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'The QA plugin fixture could not be activated.' );
		}
	}

	update_option( 'sea_tryon_enabled', 'yes', false );
	update_option( 'sea_tryon_provider', 'openai', false );
	update_option( 'sea_tryon_openai_api_key', 'test-browser-provider-placeholder', false );
	update_option( 'sea_tryon_allow_guests', 'no', false );
	update_option( 'woocommerce_coming_soon', 'no', false );
	update_option( 'woocommerce_store_pages_only', 'no', false );
	if ( '' !== $theme_slug ) {
		$theme = wp_get_theme( $theme_slug );
		if ( ! $theme->exists() ) {
			throw new RuntimeException( 'Requested QA theme is not installed.' );
		}
		update_option( 'template', $theme->get_template(), true );
		update_option( 'stylesheet', $theme->get_stylesheet(), true );
	}

	$fixture    = __DIR__ . '/fixtures/readable-product.png';
	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Sea Try-On Browser Fixture',
			'post_status'    => 'inherit',
		),
		$fixture
	);
	update_post_meta( $attachment, '_wp_attached_file', '../plugins/sea-tryon/tests/qa/fixtures/readable-product.png' );

	$product = new WC_Product_Simple();
	$product->set_name( 'Virtual Try-On Browser QA' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '25' );
	$product->set_image_id( $attachment );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_ENABLED, 'yes' );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_EXPERIENCE_TYPE, \SeaTryOn\Domain\ExperienceType::CLOTHING );
	$product->update_meta_data( \SeaTryOn\Admin\Product\ProductFields::META_PROMPT, 'Browser-only private merchant prompt.' );
	$product_id = $product->save();

	add_option(
		$state_name,
		array(
			'active_plugins' => $active,
			'options'        => $options,
			'product_id'     => $product_id,
			'attachment_id'  => $attachment,
		),
		'',
		false
	);

	echo esc_url_raw( get_permalink( $product_id ) ) . "\n";
	exit( 0 );
}

if ( 'cleanup' === $mode ) {
	$state = get_option( $state_name, null );
	if ( ! is_array( $state ) ) {
		echo "M6_BROWSER_FIXTURE=ABSENT\n";
		exit( 0 );
	}
	if ( ! empty( $state['product_id'] ) ) {
		wp_delete_post( (int) $state['product_id'], true );
	}
	if ( ! empty( $state['attachment_id'] ) ) {
		wp_delete_post( (int) $state['attachment_id'], true );
	}
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'sea-tryon' );
	}
	foreach ( $state['options'] as $name => $row ) {
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
	update_option( 'active_plugins', $state['active_plugins'] );
	delete_option( $state_name );
	echo "M6_BROWSER_FIXTURE=CLEAN\n";
	exit( 0 );
}

throw new InvalidArgumentException( 'Use setup or cleanup.' );
