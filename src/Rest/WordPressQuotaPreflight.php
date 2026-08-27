<?php
/**
 * Read-only create-time quota preflight.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use DateTimeZone;
use SeaTryOn\Auth\RequestIdentity;
use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Quota\QuotaStoreInterface;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Gives immediate 429 feedback; the worker remains the atomic charging authority. */
final class WordPressQuotaPreflight implements QuotaPreflightInterface {
	/**
	 * Quota persistence.
	 *
	 * @var QuotaStoreInterface
	 */
	private $store;

	/**
	 * Typed settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Current time source.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * Site timezone.
	 *
	 * @var DateTimeZone
	 */
	private $timezone;
	public function __construct( QuotaStoreInterface $store, SettingsRepository $settings, ClockInterface $clock, DateTimeZone $timezone ) {
		$this->store    = $store;
		$this->settings = $settings;
		$this->clock    = $clock;
		$this->timezone = $timezone;
	}

	public function assert_available( RequestIdentity $identity ): void {
		if ( $identity->is_quota_exempt() ) {
			return;
		}

		$now   = $this->clock->now()->setTimezone( $this->timezone );
		$limit = $identity->is_logged_in() ? $this->settings->get_logged_in_daily_limit() : $this->settings->get_guest_daily_limit();

		foreach ( $identity->quota_identity_keys() as $identity_key ) {
			$state = $this->store->load( $identity_key );
			if ( null === $state ) {
				continue;
			}
			if ( ! isset( $state['bucket'], $state['count'] ) || ! is_string( $state['bucket'] ) || ! is_int( $state['count'] ) || $state['count'] < 0 ) {
				throw new RestException( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503, array(), 'quota_state_invalid' );
			}
			if ( $state['bucket'] !== $now->format( 'Y-m-d' ) ) {
				continue;
			}
			if ( $state['count'] >= $limit ) {
				throw new RestException(
					'quota_exceeded',
					__( 'You have reached today’s try-on limit. Please try again tomorrow.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
					429,
					array( 'reset_at' => $now->setTime( 0, 0, 0 )->modify( '+1 day' )->format( DATE_ATOM ) )
				);
			}
		}
	}
}
