<?php
/**
 * Job scheduler tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Scheduler;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Scheduler\ActionSchedulerInterface;
use SeaTryOn\Scheduler\JobScheduler;

defined( 'ABSPATH' ) || exit;

final class JobSchedulerTest extends TestCase {
	public function test_deactivation_uses_group_cancel_for_variable_argument_actions(): void {
		$adapter = new GroupRecordingScheduler();
		( new JobScheduler( $adapter ) )->unschedule_all_plugin_actions();

		self::assertSame( array( '', array(), JobScheduler::GROUP ), $adapter->unscheduled[0] );
	}

	public function test_ensure_cleanup_schedules_one_unique_hourly_action(): void {
		$adapter = new GroupRecordingScheduler();
		( new JobScheduler( $adapter ) )->ensure_cleanup( 1000 );

		self::assertSame( 3600, $adapter->recurring[0]['interval'] );
		self::assertSame( JobScheduler::CLEANUP_HOOK, $adapter->recurring[0]['hook'] );
		self::assertTrue( $adapter->recurring[0]['unique'] );
	}
}

final class GroupRecordingScheduler implements ActionSchedulerInterface {
	/** @var array<int,array<mixed>> */ public $unscheduled = array();
	/** @var array<int,array<string,mixed>> */ public $recurring = array();
	public function is_available(): bool { return true; }
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int { return 1; }
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int { $this->recurring[] = compact( 'timestamp', 'interval', 'hook', 'args', 'group', 'unique' ); return 1; }
	public function has_scheduled( string $hook, array $args, string $group ): bool { return false; }
	public function unschedule_all( string $hook, array $args, string $group ): int { $this->unscheduled[] = array( $hook, $args, $group ); return 1; }
}
