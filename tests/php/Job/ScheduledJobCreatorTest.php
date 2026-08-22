<?php
/**
 * Scheduled job creator tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Job;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SeaTryOn\Contracts\IdGeneratorInterface;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\IdempotencyKey;
use SeaTryOn\Domain\JobService;
use SeaTryOn\DTO\CreateJobRequest;
use SeaTryOn\Job\JobSchedulingException;
use SeaTryOn\Job\ScheduledJobCreator;
use SeaTryOn\Job\WordPressJobRepository;
use SeaTryOn\Scheduler\ActionSchedulerInterface;
use SeaTryOn\Scheduler\JobScheduler;
use SeaTryOn\Support\LockHandle;
use SeaTryOn\Support\LockInterface;
use SeaTryOn\Tests\Support\MutableClock;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing

final class ScheduledJobCreatorTest extends TestCase {
	public function test_enqueue_failure_retains_original_queued_job_for_idempotent_replay(): void {
		$clock      = new MutableClock( new DateTimeImmutable( '2026-08-09T00:00:00+00:00' ) );
		$options    = new RetainedRepositoryOptions();
		$repository = $options->repository();
		$actions    = new FailingCreateActionScheduler();
		$creator    = new ScheduledJobCreator(
			new JobService( $repository, $clock, new RetainedIdGenerator() ),
			new JobScheduler( $actions ),
			$clock
		);
		$request = $this->request();

		try {
			$creator->create( $request );
			self::fail( 'The scheduler failure must be wrapped.' );
		} catch ( JobSchedulingException $exception ) {
			self::assertSame( RetainedIdGenerator::JOB_ID, $exception->job_id() );
			self::assertTrue( $exception->owns_customer_image_reference( $request->customer_image_reference() ) );
			self::assertFalse( $exception->owns_customer_image_reference( str_repeat( 'f', 32 ) . '/customer.png' ) );
			self::assertStringNotContainsString( $request->customer_image_reference(), $exception->getMessage() );
		}

		self::assertNotNull( $repository->find_by_id( RetainedIdGenerator::JOB_ID ) );
		self::assertSame( array( RetainedIdGenerator::JOB_ID ), $repository->find_job_ids( 100 ) );
		self::assertNotNull(
			$repository->find_by_idempotency_fingerprint(
				$request->owner_hash(),
				( new IdempotencyKey( $request->idempotency_key() ) )->fingerprint()
			)
		);

		$actions->fail = false;
		$replayed      = $creator->create( $request );
		self::assertSame( RetainedIdGenerator::JOB_ID, $replayed->id() );
		self::assertSame( 2, $actions->schedule_calls );
	}

	private function request(): CreateJobRequest {
		return new CreateJobRequest(
			hash( 'sha256', 'retained-owner' ),
			'retained-key-0001',
			10,
			null,
			'openai',
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			'Keep the product accurate.',
			str_repeat( 'a', 32 ) . '/customer.png',
			str_repeat( 'a', 32 ) . '/product.png',
			'user-' . hash( 'sha256', '7' )
		);
	}
}

final class RetainedIdGenerator implements IdGeneratorInterface {
	public const JOB_ID = 'dddddddddddddddddddddddddddddddd';
	public function generate(): string { return self::JOB_ID; }
}

final class RetainedRepositoryOptions {
	/** @var array<string,string> */ private $values = array();
	public function repository(): WordPressJobRepository {
		return new WordPressJobRepository(
			new RetainedLock(),
			function ( string $name ) { return $this->values[ $name ] ?? null; },
			function ( string $name, string $value ): bool {
				if ( isset( $this->values[ $name ] ) ) { return false; }
				$this->values[ $name ] = $value;
				return true;
			},
			function ( string $name, string $value ): bool { $this->values[ $name ] = $value; return true; },
			function ( string $name ): bool {
				if ( ! isset( $this->values[ $name ] ) ) { return false; }
				unset( $this->values[ $name ] );
				return true;
			},
			function ( string $name, string $old, string $replacement ): bool {
				if ( ! isset( $this->values[ $name ] ) || $old !== $this->values[ $name ] ) { return false; }
				$this->values[ $name ] = $replacement;
				return true;
			}
		);
	}
}

final class FailingCreateActionScheduler implements ActionSchedulerInterface {
	/** @var bool */ public $fail = true;
	/** @var int */ public $schedule_calls = 0;
	public function is_available(): bool { return true; }
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int {
		++$this->schedule_calls;
		if ( $this->fail ) { throw new RuntimeException( 'Action Scheduler test failure.' ); }
		return 123;
	}
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int { return 1; }
	public function has_scheduled( string $hook, array $args, string $group ): bool { return false; }
	public function unschedule_all( string $hook, array $args, string $group ): int { return 0; }
}

final class RetainedLock implements LockInterface {
	public function acquire( string $key, int $ttl ): ?LockHandle { return new LockHandle( $key, (string) $ttl, 'memory' ); }
	public function release( LockHandle $handle ): bool { return true; }
}
