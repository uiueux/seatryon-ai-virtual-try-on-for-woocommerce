<?php
/**
 * Job status tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\JobStatus;

defined( 'ABSPATH' ) || exit;

final class JobStatusTest extends TestCase {

	public function test_queued_and_processing_transitions_are_explicit(): void {
		$queued     = JobStatus::from_string( JobStatus::QUEUED );
		$processing = JobStatus::from_string( JobStatus::PROCESSING );

		self::assertTrue( $queued->can_transition_to( $processing ) );
		self::assertTrue( $processing->can_transition_to( JobStatus::from_string( JobStatus::SUCCEEDED ) ) );
		self::assertFalse( $queued->can_transition_to( JobStatus::from_string( JobStatus::SUCCEEDED ) ) );
		self::assertFalse( $processing->equals( $queued ) );
	}

	public function test_completed_states_are_terminal_but_can_be_expired_for_cleanup(): void {
		$succeeded = JobStatus::from_string( JobStatus::SUCCEEDED );

		self::assertTrue( $succeeded->is_terminal() );
		self::assertTrue( $succeeded->can_transition_to( JobStatus::from_string( JobStatus::EXPIRED ) ) );
		self::assertFalse( JobStatus::from_string( JobStatus::QUEUED )->is_terminal() );
		self::assertFalse( JobStatus::from_string( JobStatus::EXPIRED )->can_transition_to( JobStatus::from_string( JobStatus::QUEUED ) ) );
	}

	public function test_rejects_unknown_status(): void {
		$this->expectException( InvalidArgumentException::class );
		JobStatus::from_string( 'waiting' );
	}
}
