<?php
/**
 * WordPress one-use token replay store.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Auth;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

/** Uses add_option as the database-level atomic uniqueness barrier. */
final class WordPressReplayStore implements ReplayStoreMaintenanceInterface {

	private const PREFIX = 'sea_tryon_replay_';

	/**
	 * Option reader.
	 *
	 * @var callable
	 */
	private $get;

	/**
	 * Atomic option creator.
	 *
	 * @var callable
	 */
	private $add;

	/**
	 * Atomic expected-value deletion callback.
	 *
	 * @var callable
	 */
	private $compare_delete;

	/**
	 * Unix timestamp resolver.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * Bounded plugin option finder.
	 *
	 * @var callable
	 */
	private $find_candidates;

	/**
	 * Configure atomic WordPress option operations.
	 *
	 * @param callable|null $get            Read an option.
	 * @param callable|null $add            Add an option atomically.
	 * @param callable|null $compare_delete Delete only the expected option value.
	 * @param callable|null $now             Unix timestamp resolver.
	 * @param callable|null $find_candidates Find bounded replay options.
	 */
	public function __construct( ?callable $get = null, ?callable $add = null, ?callable $compare_delete = null, ?callable $now = null, ?callable $find_candidates = null ) {
		$this->get             = $get ?? static function ( string $name ) {
			return get_option( $name, null );
		};
		$this->add             = $add ?? static function ( string $name, int $expires_at ): bool {
			return add_option( $name, $expires_at, '', false );
		};
		$this->compare_delete  = $compare_delete ?? static function ( string $name, $expected ): bool {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return false;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-delete is the replay safety boundary.
			$deleted = $wpdb->delete(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => maybe_serialize( $expected ),
				),
				array( '%s', '%s' )
			);
			if ( 1 === $deleted ) {
				wp_cache_delete( $name, 'options' );
				return true;
			}

			return false;
		};
		$this->now             = $now ?? 'time';
		$this->find_candidates = $find_candidates ?? static function ( int $limit ): array {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return array();
			}
			$pattern = $wpdb->esc_like( self::PREFIX ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed core table and bounded prepared option lookup.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d", $pattern, $limit ), ARRAY_A );

			return is_array( $rows ) ? $rows : array();
		};
	}

	/** {@inheritDoc} */
	public function consume( string $fingerprint, int $expires_at ): bool {
		$now = (int) call_user_func( $this->now );
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || $expires_at <= $now ) {
			return false;
		}

		$name     = self::PREFIX . $fingerprint;
		$existing = call_user_func( $this->get, $name );
		if ( null !== $existing ) {
			$existing_expiry = is_int( $existing )
				? $existing
				: ( is_string( $existing ) && ctype_digit( $existing ) ? (int) $existing : 0 );
			if ( $existing_expiry < 1 || $existing_expiry > $now || ! call_user_func( $this->compare_delete, $name, $existing ) ) {
				return false;
			}
		}

		// Autoload is disabled; the value contains no session or token material.
		return (bool) call_user_func( $this->add, $name, $expires_at );
	}

	/** {@inheritDoc} */
	public function cleanup_expired( int $limit = 100 ): int {
		$limit = max( 1, min( 500, $limit ) );
		$now   = (int) call_user_func( $this->now );
		$rows  = call_user_func( $this->find_candidates, $limit );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$removed = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['option_name'], $row['option_value'] ) || ! is_string( $row['option_name'] ) ) {
				continue;
			}
			$name = $row['option_name'];
			if ( 1 !== preg_match( '/^sea_tryon_replay_[a-f0-9]{64}$/D', $name ) ) {
				continue;
			}
			$value  = $row['option_value'];
			$expiry = is_int( $value ) ? $value : ( is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0 );
			if ( $expiry > $now ) {
				continue;
			}
			if ( call_user_func( $this->compare_delete, $name, $value ) ) {
				++$removed;
			}
		}

		return $removed;
	}
}
