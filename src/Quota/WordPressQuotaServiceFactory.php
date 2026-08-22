<?php
/**
 * WordPress quota service factory.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Quota;

use SeaTryOn\Contracts\ClockInterface;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the WordPress site timezone to the quota domain service.
 */
final class WordPressQuotaServiceFactory {

	/**
	 * Create the daily quota service for the current site.
	 *
	 * @param QuotaStoreInterface $store Quota persistence.
	 * @param LockInterface       $lock  Atomic identity lock.
	 * @param ClockInterface      $clock Clock.
	 */
	public static function create( QuotaStoreInterface $store, LockInterface $lock, ClockInterface $clock ): QuotaService {
		return new QuotaService( $store, $lock, $clock, wp_timezone() );
	}
}
