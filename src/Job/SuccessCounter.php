<?php
/**
 * Idempotent success counter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** Uses an atomic per-job marker so replayed callbacks count success at most once. */
final class SuccessCounter {
	/** @var callable */ private $add_marker;
	/** @var callable */ private $increment;
	/** @var callable */ private $delete_marker;

	public function __construct( ?callable $add_marker = null, ?callable $increment = null, ?callable $delete_marker = null ) {
		$this->add_marker    = $add_marker ?? static function ( string $name ): bool {
			return add_option( $name, '1', '', false );
		};
		$this->increment     = $increment ?? static function (): bool {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return false; }
			$name = 'sea_tryon_success_count';
			$sql  = $wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1, autoload = 'off'",
				$name
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $sql );
			if ( false !== $result ) {
				wp_cache_delete( $name, 'options' );
				return true; }
			return false;
		};
		$this->delete_marker = $delete_marker ?? static function ( string $name ): bool {
			return delete_option( $name );
		};
	}

	public function increment_once( string $job_id ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) ) {
			return false; }
		$marker = 'sea_tryon_success_job_' . hash( 'sha256', $job_id );
		if ( ! call_user_func( $this->add_marker, $marker ) ) {
			return false; }
		if ( call_user_func( $this->increment ) ) {
			return true; }
		call_user_func( $this->delete_marker, $marker );
		return false;
	}

	/**
	 * Remove the replay marker when its bounded job record is deleted.
	 *
	 * @param string $job_id Opaque job identifier.
	 */
	public function forget( string $job_id ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) ) {
			return false;
		}
		return (bool) call_user_func( $this->delete_marker, 'sea_tryon_success_job_' . hash( 'sha256', $job_id ) );
	}
}
