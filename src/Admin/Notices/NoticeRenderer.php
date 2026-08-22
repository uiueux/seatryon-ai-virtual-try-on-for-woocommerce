<?php
/**
 * Administrative diagnostic notice renderer.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders secure, translatable admin notices.
 */
final class NoticeRenderer {

	/**
	 * Runtime health probe.
	 *
	 * @var HealthProbeInterface
	 */
	private $probe;

	/**
	 * Current-user authorization adapter.
	 *
	 * @var CapabilityCheckerInterface
	 */
	private $capabilities;

	/**
	 * Existing dependency notice detector.
	 *
	 * @var DependencyNoticeRegistryInterface
	 */
	private $dependency_notices;

	/**
	 * Set up the renderer.
	 *
	 * @param HealthProbeInterface              $probe              Health probe.
	 * @param CapabilityCheckerInterface        $capabilities       Authorization adapter.
	 * @param DependencyNoticeRegistryInterface $dependency_notices Existing notice detector.
	 */
	public function __construct(
		HealthProbeInterface $probe,
		CapabilityCheckerInterface $capabilities,
		DependencyNoticeRegistryInterface $dependency_notices
	) {
		$this->probe              = $probe;
		$this->capabilities       = $capabilities;
		$this->dependency_notices = $dependency_notices;
	}

	/**
	 * Register notices for site and network administration.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'network_admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Render visible issues for the current administrator.
	 */
	public function render(): void {
		$dependency_notice_registered = $this->dependency_notices->is_registered();

		foreach ( $this->probe->issues() as $issue ) {
			if ( ! $this->capabilities->can_view( $issue ) ) {
				continue;
			}

			if ( $dependency_notice_registered && $this->is_dependency_issue( $issue ) ) {
				continue;
			}

			$message = $this->message( $issue );
			if ( '' === $message ) {
				continue;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $message )
			);
		}
	}

	/**
	 * Map an issue to an English-default, translatable message.
	 *
	 * @param HealthIssue $issue Health issue.
	 */
	private function message( HealthIssue $issue ): string {
		switch ( $issue->code() ) {
			case HealthIssue::WORDPRESS_AI_UNAVAILABLE:
				return __( 'AI Virtual Try-On is enabled, but no configured WordPress connector supports image editing. Configure an AI provider under Settings > Connectors.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			case HealthIssue::MISSING_SEAAI_KEY:
				return __( 'AI Virtual Try-On is enabled, but the selected third-party provider does not have an API key.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			case HealthIssue::MISSING_SEAAI_URL:
				return __( 'AI Virtual Try-On is enabled, but the selected third-party provider does not have a valid gateway URL.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			case HealthIssue::STORAGE_UNAVAILABLE:
				return __( 'AI Virtual Try-On cannot use its private temporary storage. Check the server temporary directory and its write permissions.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			case HealthIssue::STORAGE_PUBLIC:
				return __( 'AI Virtual Try-On cannot start because the temporary directory is inside the public web root. Configure a private temporary directory.', 'seatryon-ai-virtual-try-on-for-woocommerce' );

			case HealthIssue::WOOCOMMERCE_MISSING:
				return sprintf(
					/* translators: %s: Minimum required WooCommerce version. */
					__( 'AI Virtual Try-On requires WooCommerce %s or newer. Install and activate WooCommerce to use this plugin.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					$issue->context( 'minimum' )
				);

			case HealthIssue::WOOCOMMERCE_TOO_OLD:
				return sprintf(
					/* translators: 1: Minimum required WooCommerce version. 2: Active WooCommerce version. */
					__( 'AI Virtual Try-On requires WooCommerce %1$s or newer. The active version is %2$s.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					$issue->context( 'minimum' ),
					$issue->context( 'current' )
				);
		}

		return '';
	}

	/**
	 * Whether an issue is already covered by the bootstrap dependency notice.
	 *
	 * @param HealthIssue $issue Health issue.
	 */
	private function is_dependency_issue( HealthIssue $issue ): bool {
		return in_array(
			$issue->code(),
			array( HealthIssue::WOOCOMMERCE_MISSING, HealthIssue::WOOCOMMERCE_TOO_OLD ),
			true
		);
	}
}
