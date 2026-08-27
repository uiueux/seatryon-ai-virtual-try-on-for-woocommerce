<?php
/**
 * Dynamic Virtual Try-On block registration.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the metadata-defined block fallback on init.
 */
final class BlockRegistrar {

	/** Register the editor script and block metadata. */
	public function register(): void {
		$plugin_path = defined( 'SEA_TRYON_PATH' ) ? (string) constant( 'SEA_TRYON_PATH' ) : '';
		$plugin_url  = defined( 'SEA_TRYON_URL' ) ? (string) constant( 'SEA_TRYON_URL' ) : '';
		$version     = defined( 'SEA_TRYON_VERSION' ) ? (string) constant( 'SEA_TRYON_VERSION' ) : '1.1.2';

		if ( '' === $plugin_path || '' === $plugin_url ) {
			return;
		}

		$asset_path = $plugin_path . 'assets/build/virtual-try-on-editor.asset.php';
		$asset      = is_readable( $asset_path ) ? require $asset_path : array();
		$asset      = is_array( $asset ) ? $asset : array();

		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$dependencies = array_values(
			array_unique(
				array_merge( $dependencies, array( 'wp-blocks', 'wp-components', 'wp-block-editor', 'wp-element', 'wp-i18n' ) )
			)
		);

		wp_register_script(
			'sea-tryon-virtual-try-on-editor',
			$plugin_url . 'assets/build/virtual-try-on-editor.js',
			$dependencies,
			isset( $asset['version'] ) ? (string) $asset['version'] : $version,
			true
		);

		wp_set_script_translations( 'sea-tryon-virtual-try-on-editor', 'seatryon-ai-virtual-try-on-for-woocommerce', $plugin_path . 'languages' );
		register_block_type_from_metadata( $plugin_path . 'blocks/virtual-try-on' );
	}
}
