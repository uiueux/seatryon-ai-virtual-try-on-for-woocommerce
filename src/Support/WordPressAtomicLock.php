<?php
/**
 * WordPress atomic lock implementation.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Support;

use SeaTryOn\Contracts\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Uses persistent object-cache atomic add when available, otherwise add_option.
 */
final class WordPressAtomicLock implements LockInterface {

	private const CACHE_BACKEND  = 'cache';
	private const OPTION_BACKEND = 'option';
	private const CACHE_GROUP    = 'sea-tryon-locks';

	/**
	 * Clock used for expiry checks.
	 *
	 * @var ClockInterface
	 */
	private $clock;

	/**
	 * Persistent-cache detector.
	 *
	 * @var callable
	 */
	private $uses_persistent_cache;

	/**
	 * Cache add callback.
	 *
	 * @var callable
	 */
	private $cache_add;

	/**
	 * Option add callback.
	 *
	 * @var callable
	 */
	private $option_add;

	/**
	 * Option get callback.
	 *
	 * @var callable
	 */
	private $option_get;

	/**
	 * Atomic option compare-and-delete callback.
	 *
	 * @var callable
	 */
	private $option_compare_delete;

	/**
	 * Callables are injectable so atomic semantics can be tested without WordPress.
	 *
	 * @param ClockInterface $clock                 Clock.
	 * @param callable|null  $uses_persistent_cache Whether persistent object cache is active.
	 * @param callable|null  $cache_add             Atomic cache add.
	 * @param callable|null  $option_add            Atomic option add.
	 * @param callable|null  $option_get            Option read.
	 * @param callable|null  $option_compare_delete Atomic option compare-and-delete.
	 */
	public function __construct(
		ClockInterface $clock,
		?callable $uses_persistent_cache = null,
		?callable $cache_add = null,
		?callable $option_add = null,
		?callable $option_get = null,
		?callable $option_compare_delete = null
	) {
		$this->clock                 = $clock;
		$this->uses_persistent_cache = $uses_persistent_cache ?? static function (): bool {
			return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
		};
		$this->cache_add             = $cache_add ?? static function ( string $key, string $value, int $ttl ): bool {
			return wp_cache_add( $key, $value, self::CACHE_GROUP, $ttl );
		};
		$this->option_add            = $option_add ?? static function ( string $key, string $value ): bool {
			return add_option( $key, $value, '', false );
		};
		$this->option_get            = $option_get ?? static function ( string $key ) {
			return get_option( $key, null );
		};
		$this->option_compare_delete = $option_compare_delete ?? static function ( string $key, string $expected ): bool {
			global $wpdb;

			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return false;
			}

			// Options API has no compare-and-delete primitive. This conditional DELETE
			// is required so an expired owner cannot delete a replacement owner's token.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->delete(
				$wpdb->options,
				array(
					'option_name'  => $key,
					'option_value' => maybe_serialize( $expected ),
				),
				array( '%s', '%s' )
			);

			if ( 1 !== $deleted ) {
				return false;
			}

			wp_cache_delete( $key, 'options' );
			return true;
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Logical lock key.
	 * @param int    $ttl Time to live in seconds.
	 * @throws \InvalidArgumentException When the key or TTL is invalid.
	 */
	public function acquire( string $key, int $ttl ): ?LockHandle {
		if ( '' === trim( $key ) || $ttl < 1 || $ttl > 300 ) {
			throw new \InvalidArgumentException( 'Lock key and TTL must be valid.' );
		}

		$normalized = 'sea_tryon_lock_' . hash( 'sha256', $key );
		$expires_at = $this->clock->now()->getTimestamp() + $ttl;
		$value      = bin2hex( random_bytes( 16 ) ) . '|' . $expires_at;
		$backend    = call_user_func( $this->uses_persistent_cache ) ? self::CACHE_BACKEND : self::OPTION_BACKEND;

		if ( $this->add( $backend, $normalized, $value, $ttl ) ) {
			return new LockHandle( $normalized, $value, $backend );
		}

		// Persistent caches own expiry. WordPress exposes no public cache CAS-delete,
		// so touching a failed cache lock could delete a concurrent replacement.
		if ( self::CACHE_BACKEND === $backend ) {
			return null;
		}

		$current = call_user_func( $this->option_get, $normalized );
		if ( ! is_string( $current ) || ! $this->is_expired( $current ) ) {
			return null;
		}

		if ( ! call_user_func( $this->option_compare_delete, $normalized, $current ) ) {
			return null;
		}

		if ( $this->add( $backend, $normalized, $value, $ttl ) ) {
			return new LockHandle( $normalized, $value, $backend );
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Cache locks intentionally remain until their short backend TTL because the
	 * WordPress object-cache API has no atomic compare-and-delete operation.
	 *
	 * @param LockHandle $handle Acquired handle.
	 */
	public function release( LockHandle $handle ): bool {
		if ( self::CACHE_BACKEND === $handle->backend() ) {
			return false;
		}

		return (bool) call_user_func( $this->option_compare_delete, $handle->key(), $handle->value() );
	}

	/**
	 * Add a value to the selected backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key     Normalized key.
	 * @param string $value   Encoded ownership value.
	 * @param int    $ttl     Time to live in seconds.
	 * @phpstan-impure
	 */
	private function add( string $backend, string $key, string $value, int $ttl ): bool {
		if ( self::CACHE_BACKEND === $backend ) {
			return (bool) call_user_func( $this->cache_add, $key, $value, $ttl );
		}

		return (bool) call_user_func( $this->option_add, $key, $value );
	}

	/**
	 * Determine whether an encoded lock has passed its TTL.
	 *
	 * @param string $value Encoded ownership value.
	 */
	private function is_expired( string $value ): bool {
		$separator = strrpos( $value, '|' );
		if ( false === $separator ) {
			return false;
		}

		$expires_at = substr( $value, $separator + 1 );
		if ( ! ctype_digit( $expires_at ) ) {
			return false;
		}

		return (int) $expires_at <= $this->clock->now()->getTimestamp();
	}
}
