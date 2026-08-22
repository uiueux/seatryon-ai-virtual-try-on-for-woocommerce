<?php
/**
 * WordPress privacy integration.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Privacy;

use SeaTryOn\Domain\Job;
use SeaTryOn\Job\JobCleanupService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers policy guidance and personal data exporter/eraser callbacks.
 */
final class PrivacyHooks {

	private const EXPORTER_KEY = 'sea-tryon-temporary-jobs';
	private const PAGE_SIZE    = 50;

	/**
	 * Personal job locator.
	 *
	 * @var PersonalJobLocatorInterface
	 */
	private $jobs;

	/**
	 * Job/file/action cleanup service.
	 *
	 * @var JobCleanupService
	 */
	private $cleanup;

	/**
	 * Email-to-user resolver.
	 *
	 * @var callable
	 */
	private $user_resolver;

	/**
	 * Create the privacy integration.
	 *
	 * @param PersonalJobLocatorInterface $jobs          Personal job locator.
	 * @param JobCleanupService           $cleanup       Job/file/action cleanup service.
	 * @param callable|null               $user_resolver Resolve a user from an email address.
	 */
	public function __construct( PersonalJobLocatorInterface $jobs, JobCleanupService $cleanup, ?callable $user_resolver = null ) {
		$this->jobs          = $jobs;
		$this->cleanup       = $cleanup;
		$this->user_resolver = $user_resolver ?? static function ( string $email ) {
			return get_user_by( 'email', $email );
		};
	}

	/** Register WordPress privacy hooks. */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/** Publish suggested text to the WordPress Privacy Policy Guide. */
	public function add_policy_content(): void {
		$content  = '<p>' . __( 'When a customer uses Virtual Try-On, the customer image, the selected product image, the product prompt, a temporary generated result, an anonymous job identifier, and limited technical diagnostics are processed to create the preview, prevent abuse, and troubleshoot failures.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) . '</p>';
		$content .= '<p>' . __( 'The customer image, product image, and prompt are sent to the AI provider selected by the store. The store should identify that provider and link to its current privacy and data-retention policies. Provider retention is outside this plugin\'s control.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) . '</p>';
		$content .= '<p>' . __( 'Temporary input images, result images, and job records are stored privately by this site for no more than 24 hours and may be removed sooner after delivery, failure, cancellation, deactivation, or a verified erasure request. These images are not added to the WordPress Media Library.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) . '</p>';
		$content .= '<p>' . __( 'The plugin does not send customer names, email addresses, orders, postal addresses, uploaded images, or telemetry to the plugin developer. AI-generated previews may be inaccurate and do not guarantee fit, size, color, or appearance.', 'seatryon-ai-virtual-try-on-for-woocommerce' ) . '</p>';

		if ( function_exists( 'wp_kses_post' ) ) {
			$content = wp_kses_post( $content );
		}

		wp_add_privacy_policy_content( __( 'AI Virtual Try-On for WooCommerce', 'seatryon-ai-virtual-try-on-for-woocommerce' ), $content );
	}

	/**
	 * Register the temporary-job personal data exporter.
	 *
	 * @param array<string,array<string,mixed>> $exporters Existing exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[ self::EXPORTER_KEY ] = array(
			'exporter_friendly_name' => __( 'Virtual Try-On temporary jobs', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register the temporary-job personal data eraser.
	 *
	 * @param array<string,array<string,mixed>> $erasers Existing erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[ self::EXPORTER_KEY ] = array(
			'eraser_friendly_name' => __( 'Virtual Try-On temporary jobs', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Export non-sensitive metadata for temporary jobs attributable to a user.
	 *
	 * @param string $email_address Account email address.
	 * @param int    $page          One-based exporter page.
	 * @return array<string,mixed>
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$user_id = $this->resolve_user_id( $email_address );
		if ( null === $user_id ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		try {
			$jobs = $this->jobs->find_for_user( $user_id, max( 1, $page ), self::PAGE_SIZE );
		} catch ( \Throwable $exception ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();
		foreach ( $jobs as $job ) {
			$data[] = array(
				'group_id'    => self::EXPORTER_KEY,
				'group_label' => __( 'Virtual Try-On temporary jobs', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'item_id'     => 'sea-tryon-job-' . $job->id(),
				'data'        => $this->export_job_metadata( $job ),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $jobs ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Erase every currently indexed temporary job attributable to a user.
	 *
	 * @param string $email_address Account email address.
	 * @param int    $page          Eraser page (accepted for WordPress compatibility).
	 * @return array<string,mixed>
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		unset( $page );
		$result  = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		$user_id = $this->resolve_user_id( $email_address );
		if ( null === $user_id ) {
			return $result;
		}

		try {
			$jobs = $this->jobs->find_all_for_user( $user_id );
		} catch ( \Throwable $exception ) {
			$result['items_retained'] = true;
			$result['messages'][]     = __( 'Some temporary Virtual Try-On data could not be located safely. Please contact the site administrator.', 'seatryon-ai-virtual-try-on-for-woocommerce' );
			return $result;
		}

		foreach ( $jobs as $job ) {
			if ( $this->cleanup->delete_job( $job->id() ) ) {
				$result['items_removed'] = true;
			} else {
				$result['items_retained'] = true;
			}
		}

		if ( $result['items_retained'] ) {
			$result['messages'][] = __( 'Some temporary Virtual Try-On data could not be erased. Please contact the site administrator.', 'seatryon-ai-virtual-try-on-for-woocommerce' );
		}

		return $result;
	}

	/**
	 * Build export-safe metadata without prompts, image bytes, references, or paths.
	 *
	 * @param Job $job Temporary job.
	 * @return array<int,array<string,string|int>>
	 */
	private function export_job_metadata( Job $job ): array {
		$data = array(
			array(
				'name'  => __( 'Job ID', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->id(),
			),
			array(
				'name'  => __( 'Status', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->status()->value(),
			),
			array(
				'name'  => __( 'Product ID', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->product_id(),
			),
			array(
				'name'  => __( 'AI provider', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->provider(),
			),
			array(
				'name'  => __( 'Created at', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->created_at()->format( DATE_ATOM ),
			),
			array(
				'name'  => __( 'Expires at', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->expires_at()->format( DATE_ATOM ),
			),
		);

		if ( null !== $job->variation_id() ) {
			$data[] = array(
				'name'  => __( 'Variation ID', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->variation_id(),
			);
		}

		if ( null !== $job->completed_at() ) {
			$data[] = array(
				'name'  => __( 'Completed at', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
				'value' => $job->completed_at()->format( DATE_ATOM ),
			);
		}

		return $data;
	}

	/**
	 * Resolve an account email to a positive WordPress user ID.
	 *
	 * @param string $email_address Account email address.
	 */
	private function resolve_user_id( string $email_address ): ?int {
		$user = call_user_func( $this->user_resolver, $email_address );
		if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
			return null;
		}

		$user_id = (int) $user->ID;

		return $user_id > 0 ? $user_id : null;
	}
}
