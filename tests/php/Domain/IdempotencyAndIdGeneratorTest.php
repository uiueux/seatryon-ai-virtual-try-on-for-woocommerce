<?php
/**
 * Identifier and idempotency tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\CSPRNGIdGenerator;
use SeaTryOn\Domain\IdempotencyKey;

defined( 'ABSPATH' ) || exit;

final class IdempotencyAndIdGeneratorTest extends TestCase {

	public function test_csprng_generator_returns_distinct_128_bit_hexadecimal_ids(): void {
		$generator = new CSPRNGIdGenerator();
		$first     = $generator->generate();
		$second    = $generator->generate();

		self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $first );
		self::assertNotSame( $first, $second );
	}

	public function test_idempotency_key_exposes_only_stable_sha256_fingerprint(): void {
		$key         = 'request-00000001';
		$idempotency = new IdempotencyKey( $key );

		self::assertSame( hash( 'sha256', $key ), $idempotency->fingerprint() );
		self::assertStringNotContainsString( $key, $idempotency->fingerprint() );
	}

	/**
	 * @dataProvider provideInvalidKeys
	 */
	public function test_rejects_invalid_idempotency_keys( string $key ): void {
		$this->expectException( InvalidArgumentException::class );
		new IdempotencyKey( $key );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function provideInvalidKeys(): array {
		return array(
			'too short'   => array( 'short' ),
			'too long'    => array( str_repeat( 'a', 129 ) ),
			'whitespace'  => array( 'request key 0001' ),
			'non-ascii'   => array( 'request-key-测试123' ),
		);
	}
}
