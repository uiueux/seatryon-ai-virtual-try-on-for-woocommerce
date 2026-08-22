<?php
/**
 * Logger wrapper tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Logging;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Security\Redactor;

defined( 'ABSPATH' ) || exit;

final class LoggerTest extends TestCase {

	public function test_wc_logger_receives_only_redacted_data_and_fixed_source(): void {
		$backend = new RecordingLogger();
		$logger  = new Logger( $backend, new Redactor( array( 'private-key' ) ) );

		$logger->error(
			'Provider failed with private-key',
			array(
				'source' => 'attacker-controlled',
				'token'  => 'context-token',
				'url'    => 'https://x.test/result?api_key=query-key',
			)
		);

		self::assertCount( 1, $backend->records );
		$encoded = json_encode( $backend->records[0] );
		self::assertIsString( $encoded );
		self::assertStringNotContainsString( 'private-key', $encoded );
		self::assertStringNotContainsString( 'context-token', $encoded );
		self::assertStringNotContainsString( 'query-key', $encoded );
		self::assertSame( 'sea-tryon', $backend->records[0]['context']['source'] );
	}

	public function test_debug_is_opt_in(): void {
		$backend = new RecordingLogger();
		$logger  = new Logger( $backend, new Redactor(), false );
		$logger->debug( 'hidden' );
		$logger->log( 'debug', 'also hidden' );
		self::assertCount( 0, $backend->records );

		( new Logger( $backend, new Redactor(), true ) )->debug( 'visible' );
		self::assertCount( 1, $backend->records );
	}

	public function test_missing_woocommerce_logger_is_safe_no_op(): void {
		$logger = new Logger( null, new Redactor() );

		$logger->error( 'No backend is available', array( 'api_key' => 'never-output' ) );
		self::assertTrue( true );
	}
}

final class RecordingLogger {

	/** @var array<int,array{level:string,message:string,context:array<mixed>}> */
	public $records = array();

	/** @param array<mixed> $context Context. */
	public function log( string $level, string $message, array $context ): void {
		$this->records[] = compact( 'level', 'message', 'context' );
	}
}
