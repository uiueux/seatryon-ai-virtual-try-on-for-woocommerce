<?php
/**
 * Provider contract and DTO tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Contracts;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Contracts\ProviderInterface;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Domain\ProviderException;
use SeaTryOn\DTO\ProviderError;
use SeaTryOn\DTO\ProviderRequest;
use SeaTryOn\DTO\ProviderResult;

defined( 'ABSPATH' ) || exit;

final class ProviderContractTest extends TestCase {

	public function test_provider_interface_returns_normalized_private_result(): void {
		$request  = $this->request();
		$provider = new class() implements ProviderInterface {
			public function generate( ProviderRequest $request ): ProviderResult {
				return new ProviderResult( 'results/job-result.png', 'image/png', 2048, 'safe-request-id' );
			}
		};

		$result = $provider->generate( $request );

		self::assertSame( 'results/job-result.png', $result->result_reference() );
		self::assertSame( 'image/png', $result->mime_type() );
		self::assertSame( 2048, $result->byte_size() );
		self::assertSame( 'safe-request-id', $result->provider_request_id() );
	}

	/**
	 * Provider request IDs reject URLs carrying query tokens.
	 */
	public function test_provider_result_rejects_url_query_token_as_request_id(): void {
		$this->expectException( InvalidArgumentException::class );
		new ProviderResult( 'results/job-result.png', 'image/png', 2048, 'https://provider.test/result?token=secret' );
	}

	/**
	 * Provider request IDs accept the documented safe ASCII shape.
	 */
	public function test_provider_result_accepts_safe_request_id(): void {
		$result = new ProviderResult( 'results/job-result.png', 'image/png', 2048, 'req_01J.ABC:def-123' );

		self::assertSame( 'req_01J.ABC:def-123', $result->provider_request_id() );
	}

	public function test_provider_exception_carries_normalized_error(): void {
		$error     = new ProviderError( 'provider_upstream_failure', 'The image service is temporarily unavailable.', true, 15, 502 );
		$exception = new ProviderException( $error );

		self::assertSame( $error, $exception->provider_error() );
		self::assertSame( $error->message(), $exception->getMessage() );
		self::assertTrue( $error->is_retryable() );
		self::assertSame( 15, $error->retry_after_seconds() );
		self::assertSame( 502, $error->http_status() );
		self::assertSame( 'provider_upstream_failure', $error->code() );
	}

	/** Provider references are retained only in a strict diagnostic field. */
	public function test_provider_error_accepts_safe_diagnostic_reference(): void {
		$error = new ProviderError( 'provider_contract_error', 'Safe contract failure.', false, null, 200, 'task_01J.ABC:def-123' );

		self::assertSame( 'task_01J.ABC:def-123', $error->diagnostic_reference() );
		self::assertStringNotContainsString( 'task_01J', $error->message() );
	}

	/** URLs and token-shaped query data cannot become diagnostic references. */
	public function test_provider_error_rejects_unsafe_diagnostic_reference(): void {
		$this->expectException( InvalidArgumentException::class );
		new ProviderError( 'provider_contract_error', 'Safe contract failure.', false, null, 200, 'https://provider.test/task?token=secret' );
	}

	public function test_provider_request_normalizes_allowlisted_options(): void {
		$request = $this->request( ' HIGH ', '1536x1024' );

		self::assertSame( 'job-0000000000000001', $request->job_id() );
		self::assertSame( 'inputs/customer-a', $request->customer_image_reference() );
		self::assertSame( 'inputs/product-a', $request->product_image_reference() );
		self::assertSame( 'Keep the product accurate.', $request->prompt() );
		self::assertSame( ExperienceType::CLOTHING, $request->experience_type()->value() );
		self::assertSame( 'high', $request->quality() );
		self::assertSame( '1536x1024', $request->size() );
	}

	/**
	 * @dataProvider provideInvalidProviderRequests
	 *
	 * @param string $quality Invalid quality.
	 * @param string $size    Invalid size.
	 */
	public function test_provider_request_rejects_uncontrolled_options( string $quality, string $size ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->request( $quality, $size );
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public function provideInvalidProviderRequests(): array {
		return array(
			'quality' => array( 'ultra', 'auto' ),
			'size'    => array( 'low', '999x999' ),
		);
	}

	/**
	 * @dataProvider provideInvalidProviderErrors
	 *
	 * @param string   $code        Error code.
	 * @param string   $message     Error message.
	 * @param bool     $retryable   Retry flag.
	 * @param int|null $retry_after Retry delay.
	 * @param int|null $status      HTTP status.
	 */
	public function test_provider_error_rejects_unsafe_or_inconsistent_values(
		string $code,
		string $message,
		bool $retryable,
		?int $retry_after,
		?int $status
	): void {
		$this->expectException( InvalidArgumentException::class );
		new ProviderError( $code, $message, $retryable, $retry_after, $status );
	}

	/**
	 * @return array<string,array{string,string,bool,int|null,int|null}>
	 */
	public function provideInvalidProviderErrors(): array {
		return array(
			'code'                => array( 'Bad Code', 'Safe.', false, null, 400 ),
			'message'             => array( 'provider_error', '', false, null, 400 ),
			'delay without retry' => array( 'provider_error', 'Safe.', false, 10, 429 ),
			'negative delay'      => array( 'provider_error', 'Safe.', true, -1, 429 ),
			'bad status'          => array( 'provider_error', 'Safe.', false, null, 700 ),
		);
	}

	/**
	 * @dataProvider provideInvalidResults
	 *
	 * @param string $reference Result reference.
	 * @param string $mime      MIME type.
	 * @param int    $size      Byte size.
	 */
	public function test_result_rejects_public_or_invalid_output( string $reference, string $mime, int $size ): void {
		$this->expectException( InvalidArgumentException::class );
		new ProviderResult( $reference, $mime, $size );
	}

	/**
	 * @return array<string,array{string,string,int}>
	 */
	public function provideInvalidResults(): array {
		return array(
			'empty reference' => array( '', 'image/png', 1 ),
			'public URL'      => array( 'https://example.test/result.png', 'image/png', 1 ),
			'traversal'       => array( '../result.png', 'image/png', 1 ),
			'bad MIME'        => array( 'results/a', 'image/webp', 1 ),
			'empty bytes'     => array( 'results/a', 'image/png', 0 ),
		);
	}

	private function request( string $quality = 'low', string $size = 'auto' ): ProviderRequest {
		return new ProviderRequest(
			'job-0000000000000001',
			'inputs/customer-a',
			'inputs/product-a',
			'Keep the product accurate.',
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			$quality,
			$size
		);
	}
}
