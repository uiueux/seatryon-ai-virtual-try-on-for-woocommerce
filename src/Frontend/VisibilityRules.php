<?php
/**
 * Product-page Virtual Try-On visibility rules.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use WC_Product;
use SeaTryOn\Admin\Product\ProductFields;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Security\SecretStore;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the authoritative server-side product eligibility rules.
 */
final class VisibilityRules {

	/**
	 * Typed global settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Product image availability.
	 *
	 * @var ProductImageReaderInterface
	 */
	private $images;

	/**
	 * Selected Provider configuration boundary.
	 *
	 * @var SecretStore
	 */
	private $secrets;

	/**
	 * Set up server-side visibility checks.
	 *
	 * @param SettingsRepository|null          $settings Settings repository.
	 * @param ProductImageReaderInterface|null $images   Product image reader.
	 * @param SecretStore|null                 $secrets  Selected Provider configuration.
	 */
	public function __construct(
		?SettingsRepository $settings = null,
		?ProductImageReaderInterface $images = null,
		?SecretStore $secrets = null
	) {
		$this->settings = $settings ?? new SettingsRepository();
		$this->images   = $images ?? new WordPressProductImageReader();
		$this->secrets  = $secrets ?? new SecretStore( $this->settings );
	}

	/**
	 * Decide whether a product may expose the customer trigger.
	 *
	 * Display filters are intentionally applied only after these checks by the
	 * renderer, so an extension cannot use a presentation filter to bypass them.
	 *
	 * @param WC_Product $product      Product resolved for the single page.
	 * @param bool       $single_page  Whether this is the single-product context.
	 * @param bool       $logged_in    Whether the current visitor is logged in.
	 */
	public function allows( WC_Product $product, bool $single_page, bool $logged_in ): bool {
		if ( ! $single_page || ! $this->settings->is_enabled() || ! $this->secrets->is_active_provider_configured() ) {
			return false;
		}

		// Logged-out customers still see the feature entry when guest use is
		// disabled; the modal presents the required login action. Authorization
		// remains enforced by the REST layer.
		unset( $logged_in );

		if ( 'yes' !== $product->get_meta( ProductFields::META_ENABLED, true ) ) {
			return false;
		}

		if ( ! $product->is_purchasable() || ! $this->images->has_readable_image( $product ) ) {
			return false;
		}

		$experience = (string) $product->get_meta( ProductFields::META_EXPERIENCE_TYPE, true );

		return in_array( $experience, ExperienceType::values(), true );
	}
}
