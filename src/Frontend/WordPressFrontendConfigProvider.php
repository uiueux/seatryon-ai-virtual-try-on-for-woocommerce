<?php
/**
 * WordPress frontend runtime configuration.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

use SeaTryOn\Auth\GuestActionBootstrap;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a same-origin REST bootstrap without exposing sessions or API keys.
 */
final class WordPressFrontendConfigProvider implements FrontendConfigProviderInterface {

	/**
	 * Typed plugin settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Guest action-token issuer.
	 *
	 * @var GuestActionBootstrap|null
	 */
	private $guest_bootstrap;

	/**
	 * Configure the frontend bootstrap provider.
	 *
	 * @param SettingsRepository|null   $settings        Settings repository.
	 * @param GuestActionBootstrap|null $guest_bootstrap Guest token issuer.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?GuestActionBootstrap $guest_bootstrap = null ) {
		$this->settings        = $settings ?? new SettingsRepository();
		$this->guest_bootstrap = $guest_bootstrap;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product ID.
	 */
	public function for_product( int $product_id ): array {
		$logged_in = is_user_logged_in();
		$mode      = $logged_in ? 'logged-in' : ( $this->settings->allow_guests() ? 'guest' : 'required' );
		$tokens    = array();

		if ( 'guest' === $mode && null !== $this->guest_bootstrap ) {
			$tokens = $this->guest_bootstrap->for_product( $product_id );
		}

		return array(
			'productId'      => $product_id,
			'restRoot'       => trailingslashit( rest_url( 'sea-tryon/v1' ) ),
			'guestTokenUrl'  => rest_url( 'sea-tryon/v1/guest-token' ),
			'authMode'       => $mode,
			'nonce'          => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
			'tokens'         => $tokens,
			'loginUrl'       => 'required' === $mode ? wp_login_url( get_permalink() ) : '',
			'maxUploadBytes' => 10 * MB_IN_BYTES,
		);
	}
}
