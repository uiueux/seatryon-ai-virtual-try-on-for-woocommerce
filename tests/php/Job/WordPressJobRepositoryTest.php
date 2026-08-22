<?php
/**
 * WordPress job repository tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Job;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\Job;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Job\ConcurrentJobWriteException;
use SeaTryOn\Job\WordPressJobRepository;
use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;

defined( 'ABSPATH' ) || exit;

final class WordPressJobRepositoryTest extends TestCase {
	public function test_persists_strict_json_without_php_serialized_objects(): void {
		$options = new RepositoryOptions();
		$repository = $options->repository();
		$saved = $repository->save_if_absent( $this->job() );

		$raw = $options->values['sea_tryon_job_' . $saved->id()];
		self::assertStringStartsWith( '{', $raw );
		self::assertStringNotContainsString( 'O:', $raw );
		self::assertSame( 1, $saved->revision() );
		self::assertSame( $saved->quota_identity_key(), $repository->find_by_id( $saved->id() )->quota_identity_key() );
	}

	public function test_owner_scoped_idempotency_returns_original_winner(): void {
		$options = new RepositoryOptions();
		$repository = $options->repository();
		$winner = $repository->save_if_absent( $this->job() );
		$replay = $repository->save_if_absent( $this->job( str_repeat( 'c', 32 ) ) );

		self::assertSame( $winner->id(), $replay->id() );
	}

	public function test_exact_compare_and_swap_rejects_stale_interleaving(): void {
		$options = new RepositoryOptions();
		$first_repository = $options->repository();
		$first_repository->save_if_absent( $this->job() );
		$left = $first_repository->find_by_id( str_repeat( 'b', 32 ) );
		$right_repository = $options->repository();
		$right = $right_repository->find_by_id( str_repeat( 'b', 32 ) );

		$left->start_processing( new DateTimeImmutable( '2026-08-09T00:01:00+00:00' ) );
		$first_repository->save( $left );
		$right->cancel( new DateTimeImmutable( '2026-08-09T00:02:00+00:00' ) );

		$this->expectException( ConcurrentJobWriteException::class );
		$right_repository->save( $right );
	}

	public function test_strict_hydration_rejects_schema_drift(): void {
		$options = new RepositoryOptions();
		$repository = $options->repository();
		$job = $repository->save_if_absent( $this->job() );
		$name = 'sea_tryon_job_' . $job->id();
		$data = json_decode( $options->values[ $name ], true );
		$data['unexpected'] = 'value';
		$options->values[ $name ] = (string) json_encode( $data );

		$this->expectException( InvalidArgumentException::class );
		$repository->find_by_id( $job->id() );
	}

	public function test_missing_job_prunes_stale_index_entry(): void {
		$options = new RepositoryOptions();
		$repository = $options->repository();
		$job = $repository->save_if_absent( $this->job() );
		unset( $options->values['sea_tryon_job_' . $job->id()] );

		self::assertTrue( $repository->delete( $job->id() ) );
		self::assertSame( array(), $repository->find_job_ids( 100 ) );
	}

	public function test_index_lock_contention_is_observable_and_retryable(): void {
		$options = new RepositoryOptions();
		$job = $options->repository()->save_if_absent( $this->job() );
		$contended = $options->repository( new SelectiveRepositoryLock( true ) );

		self::assertFalse( $contended->delete( $job->id() ) );
		self::assertSame( array( $job->id() ), $contended->find_job_ids( 100 ) );
		self::assertTrue( $options->repository()->delete( $job->id() ) );
		self::assertSame( array(), $options->repository()->find_job_ids( 100 ) );
	}

	public function test_stale_idempotency_pointer_self_heals_under_tuple_lock(): void {
		$options = new RepositoryOptions();
		$job = $this->job();
		$pointer = 'sea_tryon_job_idem_' . hash( 'sha256', $job->owner_hash() . '|' . $job->idempotency_fingerprint() );
		$options->values[ $pointer ] = str_repeat( 'f', 32 );

		$saved = $options->repository()->save_if_absent( $job );

		self::assertSame( $job->id(), $saved->id() );
		self::assertSame( $job->id(), $options->values[ $pointer ] );
	}

	public function test_bounded_full_index_enumeration_is_not_truncated_at_one_hundred(): void {
		$options = new RepositoryOptions();
		$index   = array();
		for ( $position = 0; $position < 101; ++$position ) {
			$index[ hash( 'sha256', (string) $position ) ] = 1786233600 + $position;
		}
		$options->values['sea_tryon_job_index_v1'] = (string) json_encode( $index );

		self::assertCount( 101, $options->repository()->find_job_ids( 5000 ) );
	}

	private function job( string $id = '' ): Job {
		$request = new CreateJobRequest(
			hash( 'sha256', 'owner' ), 'idempotency-key-1', 10, null, 'openai',
			ExperienceType::from_string( ExperienceType::CLOTHING ), 'Keep the selected product accurate.',
			str_repeat( 'a', 32 ) . '/customer.png', str_repeat( 'a', 32 ) . '/product.png',
			'user-' . hash( 'sha256', '7' )
		);
		return Job::create( '' === $id ? str_repeat( 'b', 32 ) : $id, hash( 'sha256', 'idempotency-key-1' ), $request, new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ), new DateTimeImmutable( '2026-08-10T00:00:00+00:00' ) );
	}
}

final class RepositoryOptions {
	/** @var array<string,string> */ public $values = array();
	public function repository( ?LockInterface $lock = null ): WordPressJobRepository {
		return new WordPressJobRepository(
			$lock ?? new RepositoryLock(),
			function ( string $name ) { return $this->values[ $name ] ?? null; },
			function ( string $name, string $value ): bool { if ( isset( $this->values[ $name ] ) ) { return false; } $this->values[ $name ] = $value; return true; },
			function ( string $name, string $value ): bool { $changed = ! isset( $this->values[ $name ] ) || $this->values[ $name ] !== $value; $this->values[ $name ] = $value; return $changed; },
			function ( string $name ): bool { if ( ! isset( $this->values[ $name ] ) ) { return false; } unset( $this->values[ $name ] ); return true; },
			function ( string $name, string $old, string $replacement ): bool { if ( ! isset( $this->values[ $name ] ) || $this->values[ $name ] !== $old ) { return false; } $this->values[ $name ] = $replacement; return true; }
		);
	}
}

final class RepositoryLock implements LockInterface {
	public function acquire( string $key, int $ttl ): ?LockHandle { return new LockHandle( $key, (string) $ttl, 'memory' ); }
	public function release( LockHandle $handle ): bool { return true; }
}

final class SelectiveRepositoryLock implements LockInterface {
	/** @var bool */ private $deny_index;
	public function __construct( bool $deny_index ) { $this->deny_index = $deny_index; }
	public function acquire( string $key, int $ttl ): ?LockHandle { return $this->deny_index && 'job-index' === $key ? null : new LockHandle( $key, (string) $ttl, 'memory' ); }
	public function release( LockHandle $handle ): bool { return true; }
}
