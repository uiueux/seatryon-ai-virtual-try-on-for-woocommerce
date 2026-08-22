<?php
/**
 * Retry-After parser tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Http;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Http\RetryAfterParser;

defined( 'ABSPATH' ) || exit;

final class RetryAfterParserTest extends TestCase {

	public function test_parses_delta_and_http_date_without_sleeping(): void {
		$parser = new RetryAfterParser();

		self::assertSame( 45, $parser->parse( '45', 1000 ) );
		self::assertSame( 120, $parser->parse( gmdate( 'D, d M Y H:i:s', 1120 ) . ' GMT', 1000 ) );
		self::assertSame( 3600, $parser->parse( '999999', 1000 ) );
		self::assertSame( 0, $parser->parse( gmdate( 'D, d M Y H:i:s', 900 ) . ' GMT', 1000 ) );
		self::assertNull( $parser->parse( "12\r\nX-Evil: yes", 1000 ) );
		self::assertNull( $parser->parse( 'tomorrowish', 1000 ) );
	}
}
