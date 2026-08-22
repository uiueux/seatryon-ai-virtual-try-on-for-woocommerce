<?php
/**
 * Sea Try-On administrative health probe.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Admin\Notices;

use SeaTryOn\Security\SecretStore;
use SeaTryOn\Provider\WordPressAI\WordPressAIClient;
use SeaTryOn\Provider\WordPressAI\WordPressAIClientInterface;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Converts runtime state into stable, non-secret issue codes.
 */
final class WordPressHealthProbe implements HealthProbeInterface {

	/**
	 * Typed settings access.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Selected provider credential access.
	 *
	 * @var SecretStore
	 */
	private $secrets;

	/**
	 * Injectable runtime status adapter.
	 *
	 * @var SystemStatusInterface
	 */
	private $system;

	/**
	 * WordPress AI capability adapter.
	 *
	 * @var WordPressAIClientInterface
	 */
	private $wordpress_ai;

	/**
	 * Set up the probe.
	 *
	 * @param SettingsRepository              $settings Settings access.
	 * @param SecretStore                     $secrets  Credential access.
	 * @param SystemStatusInterface           $system       Runtime status adapter.
	 * @param WordPressAIClientInterface|null $wordpress_ai WordPress AI capability adapter.
	 */
	public function __construct( SettingsRepository $settings, SecretStore $secrets, SystemStatusInterface $system, ?WordPressAIClientInterface $wordpress_ai = null ) {
		$this->settings     = $settings;
		$this->secrets      = $secrets;
		$this->system       = $system;
		$this->wordpress_ai = $wordpress_ai ?? new WordPressAIClient();
	}

	/** {@inheritDoc} */
	public function issues(): array {
		$issues = array();

		$this->append_dependency_issue( $issues );

		$storage_status = $this->system->storage_status();
		if ( SystemStatusInterface::STORAGE_PUBLIC === $storage_status ) {
			$issues[] = new HealthIssue( HealthIssue::STORAGE_PUBLIC, HealthIssue::AUDIENCE_STORE_MANAGER );
		} elseif ( SystemStatusInterface::STORAGE_AVAILABLE !== $storage_status ) {
			$issues[] = new HealthIssue( HealthIssue::STORAGE_UNAVAILABLE, HealthIssue::AUDIENCE_STORE_MANAGER );
		}

		if ( $this->settings->is_enabled() ) {
			$this->append_provider_issues( $issues );
		}

		return $issues;
	}

	/**
	 * Append the WooCommerce dependency issue, if any.
	 *
	 * @param HealthIssue[] $issues Issue list passed by reference.
	 */
	private function append_dependency_issue( array &$issues ): void {
		$minimum = $this->system->minimum_woocommerce_version();

		if ( ! $this->system->is_woocommerce_active() ) {
			$issues[] = new HealthIssue(
				HealthIssue::WOOCOMMERCE_MISSING,
				HealthIssue::AUDIENCE_PLUGIN_MANAGER,
				array( 'minimum' => $minimum )
			);
			return;
		}

		$current = $this->system->woocommerce_version();
		if ( version_compare( $current, $minimum, '<' ) ) {
			$issues[] = new HealthIssue(
				HealthIssue::WOOCOMMERCE_TOO_OLD,
				HealthIssue::AUDIENCE_PLUGIN_MANAGER,
				array(
					'minimum' => $minimum,
					'current' => $current,
				)
			);
		}
	}

	/**
	 * Append selected-provider configuration issues.
	 *
	 * @param HealthIssue[] $issues Issue list passed by reference.
	 */
	private function append_provider_issues( array &$issues ): void {
		if ( SettingsRepository::PROVIDER_WORDPRESS_AI === $this->settings->get_provider() ) {
			if ( ! $this->wordpress_ai->supports_image_editing() ) {
				$issues[] = new HealthIssue( HealthIssue::WORDPRESS_AI_UNAVAILABLE, HealthIssue::AUDIENCE_STORE_MANAGER );
			}
			return;
		}

		if ( '' === $this->secrets->get_seaai_api_key() ) {
			$issues[] = new HealthIssue( HealthIssue::MISSING_SEAAI_KEY, HealthIssue::AUDIENCE_STORE_MANAGER );
		}

		if ( '' === $this->secrets->get_seaai_base_url() ) {
			$issues[] = new HealthIssue( HealthIssue::MISSING_SEAAI_URL, HealthIssue::AUDIENCE_STORE_MANAGER );
		}
	}
}
