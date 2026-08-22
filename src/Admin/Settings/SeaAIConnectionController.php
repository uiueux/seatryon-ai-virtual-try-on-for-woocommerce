<?php
/**
 * SeaAI connection test AJAX controller.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Authorizes and dispatches settings-screen connection tests.
 */
final class SeaAIConnectionController {

	public const AJAX_ACTION  = 'sea_tryon_test_seaai_connection';
	public const NONCE_ACTION = 'sea_tryon_test_seaai_connection';

	/**
	 * Connection test service.
	 *
	 * @var SeaAIConnectionTester
	 */
	private $tester;

	/**
	 * Set up the AJAX controller.
	 *
	 * @param SeaAIConnectionTester|null $tester Connection test service.
	 */
	public function __construct( ?SeaAIConnectionTester $tester = null ) {
		$this->tester = $tester ?? new SeaAIConnectionTester();
	}

	/** Register the authenticated WordPress AJAX action. */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle' ) );
	}

	/** Validate authorization and return a safe JSON result. */
	public function handle(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You do not have permission to test SeaAI credentials.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$base_url = isset( $_POST['base_url'] ) && is_string( $_POST['base_url'] )
			? sanitize_text_field( wp_unslash( $_POST['base_url'] ) )
			: '';
		// A generic text sanitizer can corrupt credentials. The tester strips
		// control characters and enforces the exact SeaAI key format instead.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$api_key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] )
			? wp_unslash( $_POST['api_key'] )
			: '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$result = $this->tester->test( $base_url, $api_key );
		if ( $result->is_success() ) {
			wp_send_json_success( $result->payload(), $result->http_status() );
		}

		wp_send_json_error( $result->payload(), $result->http_status() );
	}
}
