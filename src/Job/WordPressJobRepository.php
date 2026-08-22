<?php
/**
 * JSON-only WordPress option job repository.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Job;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use SeaTryOn\Domain\Job;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing

/** Persists private jobs as non-autoloaded, strictly versioned JSON options. */
final class WordPressJobRepository implements JobRepositoryMaintenanceInterface {
	private const JOB_PREFIX     = 'sea_tryon_job_';
	private const IDEM_PREFIX    = 'sea_tryon_job_idem_';
	private const INDEX_OPTION   = 'sea_tryon_job_index_v1';
	private const MAX_INDEX_SIZE = 5000;

	/** @var LockInterface */
	private $lock;
	/** @var callable */
	private $get;
	/** @var callable */
	private $add;
	/** @var callable */
	private $update;
	/** @var callable */
	private $delete_option;
	/** @var callable */
	private $compare_update;
	/** @var array<string,string> Object hash to exact loaded JSON. */
	private $loaded_json = array();

	/** Callbacks are injectable for deterministic race tests. */
	public function __construct(
		LockInterface $lock,
		?callable $get = null,
		?callable $add = null,
		?callable $update = null,
		?callable $delete_option = null,
		?callable $compare_update = null
	) {
		$this->lock           = $lock;
		$this->get            = $get ?? static function ( string $name ) {
			return get_option( $name, null );
		};
		$this->add            = $add ?? static function ( string $name, string $value ): bool {
			return add_option( $name, $value, '', false );
		};
		$this->update         = $update ?? static function ( string $name, string $value ): bool {
			return update_option( $name, $value, false );
		};
		$this->delete_option  = $delete_option ?? static function ( string $name ): bool {
			return delete_option( $name );
		};
		$this->compare_update = $compare_update ?? static function ( string $name, string $old, string $replacement ): bool {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return false; }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$changed = $wpdb->update(
				$wpdb->options,
				array(
					'option_value' => $replacement,
					'autoload'     => 'off',
				),
				array(
					'option_name'  => $name,
					'option_value' => $old,
				),
				array( '%s', '%s' ),
				array( '%s', '%s' )
			);
			if ( 1 === $changed ) {
				wp_cache_delete( $name, 'options' );
				return true; }
			return false;
		};
	}

	public function find_by_id( string $job_id ): ?Job {
		if ( 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $job_id ) ) {
			return null; }
		$json = call_user_func( $this->get, self::JOB_PREFIX . $job_id );
		if ( ! is_string( $json ) || '' === $json ) {
			return null; }
		$job = $this->decode( $json );
		if ( $job->id() !== $job_id ) {
			throw new RuntimeException( 'Stored job identifier does not match its opaque key.' ); }
		$this->loaded_json[ spl_object_hash( $job ) ] = $json;
		return $job;
	}

	public function find_by_idempotency_fingerprint( string $owner_hash, string $idempotency_fingerprint ): ?Job {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $owner_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $idempotency_fingerprint ) ) {
			return null; }
		$pointer = call_user_func( $this->get, $this->idempotency_option( $owner_hash, $idempotency_fingerprint ) );
		return is_string( $pointer ) ? $this->find_by_id( $pointer ) : null;
	}

	public function save_if_absent( Job $job ): Job {
		$lock = $this->lock->acquire( 'job-idem:' . $job->owner_hash() . ':' . $job->idempotency_fingerprint(), 15 );
		if ( null === $lock ) {
			throw new ConcurrentJobWriteException( 'The idempotency key is busy.' ); }
		try {
			$existing = $this->find_by_idempotency_fingerprint( $job->owner_hash(), $job->idempotency_fingerprint() );
			if ( null !== $existing ) {
				return $existing; }
			$job->mark_persisted_revision( 1 );
			$json = $this->encode( $job );
			if ( ! call_user_func( $this->add, self::JOB_PREFIX . $job->id(), $json ) ) {
				$winner = $this->find_by_id( $job->id() );
				if ( null !== $winner ) {
					return $winner; }
				throw new ConcurrentJobWriteException( 'The job identifier could not be reserved.' );
			}
			$pointer_name = $this->idempotency_option( $job->owner_hash(), $job->idempotency_fingerprint() );
			if ( ! call_user_func( $this->add, $pointer_name, $job->id() ) ) {
				$winner = $this->find_by_idempotency_fingerprint( $job->owner_hash(), $job->idempotency_fingerprint() );
				if ( null !== $winner ) {
					call_user_func( $this->delete_option, self::JOB_PREFIX . $job->id() );
					return $winner; }
				$stale_pointer = call_user_func( $this->get, $pointer_name );
				$stale_removed = is_string( $stale_pointer ) && call_user_func( $this->delete_option, $pointer_name );
				if ( ! $stale_removed || ! $this->reserve_idempotency_pointer( $pointer_name, $job->id() ) ) {
					call_user_func( $this->delete_option, self::JOB_PREFIX . $job->id() );
					throw new ConcurrentJobWriteException( 'The idempotency pointer could not be reserved.' );
				}
			}
			try {
				$this->add_to_index( $job ); } catch ( \Throwable $exception ) {
				call_user_func( $this->delete_option, $pointer_name );
				call_user_func( $this->delete_option, self::JOB_PREFIX . $job->id() );
				throw $exception;
				}
				$this->loaded_json[ spl_object_hash( $job ) ] = $json;
				return $job;
		} finally {
			$this->lock->release( $lock ); }
	}

	public function save( Job $job ): void {
		$object_key = spl_object_hash( $job );
		if ( ! isset( $this->loaded_json[ $object_key ] ) ) {
			throw new ConcurrentJobWriteException( 'The job must be loaded by this repository before saving.' ); }
		$old           = $this->loaded_json[ $object_key ];
		$next_revision = $job->revision() + 1;
		$job->mark_persisted_revision( $next_revision );
		$new = $this->encode( $job );
		if ( ! call_user_func( $this->compare_update, self::JOB_PREFIX . $job->id(), $old, $new ) ) {
			$job->mark_persisted_revision( $next_revision - 1 );
			throw new ConcurrentJobWriteException( 'The job was changed by another request.' );
		}
		$this->loaded_json[ $object_key ] = $new;
	}

	public function find_expired_ids( DateTimeImmutable $now, int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$index = $this->read_index();
		$ids   = array();
		foreach ( $index as $id => $expires ) {
			if ( $expires <= $now->getTimestamp() ) {
				$ids[] = $id;
				if ( count( $ids ) >= $limit ) {
					break; }
			}
		}
		return $ids;
	}

	public function find_job_ids( int $limit ): array {
		return array_slice( array_keys( $this->read_index() ), 0, max( 1, min( self::MAX_INDEX_SIZE, $limit ) ) );
	}

	public function delete( string $job_id ): bool {
		$job = $this->find_by_id( $job_id );
		if ( null === $job ) {
			return $this->remove_from_index( $job_id ); }
		$lock = $this->lock->acquire( 'job-delete:' . $job_id, 15 );
		if ( null === $lock ) {
			return false; }
		try {
			call_user_func( $this->delete_option, $this->idempotency_option( $job->owner_hash(), $job->idempotency_fingerprint() ) );
			$deleted       = (bool) call_user_func( $this->delete_option, self::JOB_PREFIX . $job_id );
			$index_removed = $this->remove_from_index( $job_id );
			return $deleted && $index_removed;
		} finally {
			$this->lock->release( $lock ); }
	}

	private function add_to_index( Job $job ): void {
		$handle = $this->lock->acquire( 'job-index', 15 );
		if ( null === $handle ) {
			throw new ConcurrentJobWriteException( 'The bounded job index is busy.' ); }
		try {
			$index = $this->read_index();
			if ( ! isset( $index[ $job->id() ] ) && count( $index ) >= self::MAX_INDEX_SIZE ) {
				throw new RuntimeException( 'The bounded job index is full.' ); }
			$index[ $job->id() ] = $job->expires_at()->getTimestamp();
			$this->write_index( $index );
		} finally {
			$this->lock->release( $handle ); }
	}

	private function remove_from_index( string $job_id ): bool {
		$handle = $this->lock->acquire( 'job-index', 15 );
		if ( null === $handle ) {
			return false; }
		try {
			$index = $this->read_index();
			unset( $index[ $job_id ] );
			$this->write_index( $index );
			return true;
		} finally {
			$this->lock->release( $handle ); }
	}

	/** @return array<string,int> */
	private function read_index(): array {
		$json = call_user_func( $this->get, self::INDEX_OPTION );
		if ( null === $json ) {
			return array(); }
		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'The job index is malformed.' ); }
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || count( $data ) > self::MAX_INDEX_SIZE ) {
			throw new RuntimeException( 'The job index is malformed or unbounded.' ); }
		$clean = array();
		foreach ( $data as $id => $expires ) {
			if ( ! is_string( $id ) || 1 !== preg_match( '/^[a-f0-9]{32,128}$/D', $id ) || ! is_int( $expires ) ) {
				throw new RuntimeException( 'The job index contains invalid data.' ); }
			$clean[ $id ] = $expires;
		}
		asort( $clean, SORT_NUMERIC );
		return $clean;
	}

	/** @param array<string,int> $index */
	private function write_index( array $index ): void {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $index, JSON_UNESCAPED_SLASHES ) : json_encode( $index, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit-test fallback.
		if ( false === $json ) {
			throw new RuntimeException( 'The job index could not be encoded.' ); }
		$current = call_user_func( $this->get, self::INDEX_OPTION );
		$ok      = null === $current ? call_user_func( $this->add, self::INDEX_OPTION, $json ) : call_user_func( $this->update, self::INDEX_OPTION, $json );
		if ( ! $ok && call_user_func( $this->get, self::INDEX_OPTION ) !== $json ) {
			throw new RuntimeException( 'The job index could not be saved.' ); }
	}

	private function idempotency_option( string $owner_hash, string $fingerprint ): string {
		return self::IDEM_PREFIX . hash( 'sha256', $owner_hash . '|' . $fingerprint ); }
	/** Retry a pointer reservation after a confirmed stale pointer was removed. */
	private function reserve_idempotency_pointer( string $pointer_name, string $job_id ): bool {
		return (bool) call_user_func( $this->add, $pointer_name, $job_id );
	}
	private function encode( Job $job ): string {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $job->snapshot(), JSON_UNESCAPED_SLASHES ) : json_encode( $job->snapshot(), JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit-test fallback.
		if ( false === $json ) {
			throw new RuntimeException( 'The job could not be encoded.' ); }
		return $json;
	}
	private function decode( string $json ): Job {
		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			throw new InvalidArgumentException( 'Stored job JSON is invalid.' ); }
		return Job::from_snapshot( $data );
	}
}
