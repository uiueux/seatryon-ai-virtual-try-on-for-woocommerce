<?php
/**
 * Create job request tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Contracts;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\DTO\CreateJobRequest;

defined( 'ABSPATH' ) || exit;

final class CreateJobRequestTest extends TestCase {

	public function test_normalizes_provider_and_exposes_validated_values(): void {
		$request = $this->makeRequest( ' SEAAI ' );

		self::assertSame( hash( 'sha256', 'owner-hash' ), $request->owner_hash() );
		self::assertSame( 'idempotency-0001', $request->idempotency_key() );
		self::assertSame( 10, $request->product_id() );
		self::assertNull( $request->variation_id() );
		self::assertSame( 'seaai', $request->provider() );
		self::assertSame( ExperienceType::FURNITURE, $request->experience_type()->value() );
		self::assertSame( 'Place the chair by the window.', $request->prompt() );
		self::assertSame( 'inputs/customer', $request->customer_image_reference() );
		self::assertSame( 'inputs/product', $request->product_image_reference() );
	}

	/**
	 * @dataProvider provideInvalidCoreValues
	 *
	 * @param string $owner      Owner hash.
	 * @param int    $product_id Product ID.
	 * @param int|null $variation_id Variation ID.
	 * @param string $provider   Provider slug.
	 */
	public function test_rejects_invalid_core_values( string $owner, int $product_id, ?int $variation_id, string $provider ): void {
		$this->expectException( InvalidArgumentException::class );
		new CreateJobRequest(
			$owner,
			'idempotency-0001',
			$product_id,
			$variation_id,
			$provider,
			ExperienceType::from_string( ExperienceType::AUTO ),
			'Use the product.',
			'inputs/customer',
			'inputs/product'
		);
	}

	/**
	 * @return array<string,array{string,int,int|null,string}>
	 */
	public function provideInvalidCoreValues(): array {
		return array(
			'raw owner' => array( 'customer@example.test', 10, null, 'openai' ),
			'uppercase owner hash' => array( 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 10, null, 'openai' ),
			'product'   => array( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 0, null, 'openai' ),
			'variation' => array( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 10, 0, 'openai' ),
			'provider'  => array( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 10, null, 'other' ),
		);
	}

	public function test_rejects_missing_prompt(): void {
		$this->expectException( InvalidArgumentException::class );
		new CreateJobRequest( str_repeat( 'a', 64 ), 'idempotency-0001', 10, null, 'openai', ExperienceType::from_string( ExperienceType::AUTO ), ' ', 'inputs/a', 'inputs/b' );
	}

	public function test_rejects_missing_image_reference(): void {
		$this->expectException( InvalidArgumentException::class );
		new CreateJobRequest( str_repeat( 'a', 64 ), 'idempotency-0001', 10, null, 'openai', ExperienceType::from_string( ExperienceType::AUTO ), 'Use product.', '', 'inputs/b' );
	}

	public function test_accepts_one_way_unlimited_quota_identity(): void {
		$request = new CreateJobRequest(
			str_repeat( 'a', 64 ),
			'idempotency-0001',
			10,
			null,
			'openai',
			ExperienceType::from_string( ExperienceType::AUTO ),
			'Use product.',
			'inputs/a',
			'inputs/b',
			'unlimited-' . hash( 'sha256', '4' )
		);

		self::assertStringStartsWith( 'unlimited-', $request->quota_identity_key() );
	}

	private function makeRequest( string $provider ): CreateJobRequest {
		return new CreateJobRequest(
			hash( 'sha256', 'owner-hash' ),
			'idempotency-0001',
			10,
			null,
			$provider,
			ExperienceType::from_string( ExperienceType::FURNITURE ),
			'Place the chair by the window.',
			'inputs/customer',
			'inputs/product'
		);
	}
}
