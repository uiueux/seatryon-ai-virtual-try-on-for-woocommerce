<?php
/**
 * Usage statistics administration.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Settings;

use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and securely resets the aggregate success counter.
 */
final class StatisticsController {

	public const ACTION       = 'sea_tryon_reset_statistics';
	public const NONCE_ACTION = 'sea_tryon_reset_statistics';
	public const NOTICE_KEY   = 'statistics-reset';

	/**
	 * Typed settings access.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Set up the statistics controller.
	 *
	 * @param SettingsRepository|null $settings Settings repository.
	 */
	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/** Register public WordPress/WooCommerce hooks. */
	public function register_hooks(): void {
		add_action( 'woocommerce_admin_field_sea_tryon_statistics', array( $this, 'render_field' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_reset' ) );
		add_action( 'admin_notices', array( $this, 'render_reset_notice' ) );
	}

	/**
	 * Render the read-only count and a nonce-protected reset link.
	 *
	 * @param array<string,mixed> $field WooCommerce field definition.
	 */
	public function render_field( array $field ): void {
		$title = isset( $field['title'] ) ? (string) $field['title'] : __( 'Successful generations', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		$url   = wp_nonce_url(
			add_query_arg(
				'action',
				self::ACTION,
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION
		);

		printf(
			'<tr><th scope="row" class="titledesc">%1$s</th><td><strong aria-live="polite">%2$s</strong><p class="description">%3$s</p><a class="button" href="%4$s" onclick="return window.confirm(%5$s);">%6$s</a></td></tr>',
			esc_html( $title ),
			esc_html( number_format_i18n( $this->settings->get_success_count() ) ),
			esc_html__( 'Total previews successfully generated. This value is read-only.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			esc_url( $url ),
			esc_attr( wp_json_encode( __( 'Reset the successful generation count to zero?', 'seatryon-ai-virtual-try-on-for-woocommerce' ) ) ),
			esc_html__( 'Reset statistics', 'seatryon-ai-virtual-try-on-for-woocommerce' )
		);
	}

	/** Process reset and redirect back to this settings section. */
	public function handle_reset(): void {
		wp_safe_redirect( $this->process_reset() );
		exit;
	}

	/**
	 * Enforce authorization and CSRF checks, then reset only the aggregate count.
	 *
	 * @return string Safe settings URL.
	 */
	public function process_reset(): string {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to reset Virtual Try-On statistics.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE_ACTION );
		$this->settings->options()->update( SettingsRepository::OPTION_SUCCESS_COUNT, 0, false );

		return add_query_arg(
			array(
				'page'             => 'wc-settings',
				'tab'              => 'products',
				'section'          => SettingsPage::SECTION_ID,
				'sea_tryon_notice' => self::NOTICE_KEY,
			),
			admin_url( 'admin.php' )
		);
	}

	/** Render a dismissible success notice after the safe redirect. */
	public function render_reset_notice(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		if ( ! current_user_can( 'manage_woocommerce' ) || ! SettingsPage::is_current_section() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag set by a nonce-verified action.
		$notice = isset( $_GET['sea_tryon_notice'] ) ? sanitize_key( wp_unslash( $_GET['sea_tryon_notice'] ) ) : '';
		if ( self::NOTICE_KEY !== $notice ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Virtual Try-On statistics were reset.', 'seatryon-ai-virtual-try-on-for-woocommerce' )
			. '</p></div>';
	}
}
