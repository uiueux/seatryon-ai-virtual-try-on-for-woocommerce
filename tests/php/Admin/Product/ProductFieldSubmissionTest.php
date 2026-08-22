<?php
/**
 * Product field submission tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Admin\Product;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Admin\Product\ProductFieldSubmission;
use SeaTryOn\Admin\Product\ProductFieldValidationException;

defined( 'ABSPATH' ) || exit;

final class ProductFieldSubmissionTest extends TestCase {

	/**
	 * Valid fields are normalized as one submission.
	 */
	public function test_valid_submission_is_normalized(): void {
		$submission = ProductFieldSubmission::from_raw(
			'yes',
			"  Keep the <strong>blue</strong> jacket.\r\nNatural light.  ",
			'CLOTHING',
			static function ( string $prompt ): string {
				return str_replace( "\r\n", "\n", strip_tags( $prompt ) );
			}
		);

		self::assertTrue( $submission->is_enabled() );
		self::assertSame( "Keep the blue jacket.\nNatural light.", $submission->prompt() );
		self::assertSame( 'clothing', $submission->experience_type() );
	}

	/**
	 * Enabled products may save an empty optional prompt.
	 */
	public function test_enabled_submission_allows_empty_prompt(): void {
		$submission = ProductFieldSubmission::from_raw( 'yes', '   ', 'auto', 'strval' );

		self::assertTrue( $submission->is_enabled() );
		self::assertSame( '', $submission->prompt() );
		self::assertSame( 'auto', $submission->experience_type() );
	}

	/**
	 * @dataProvider provide_invalid_values
	 *
	 * @param string $enabled    Enabled value.
	 * @param string $prompt     Prompt value.
	 * @param string $experience Experience value.
	 * @param string $reason     Expected reason.
	 */
	public function test_invalid_values_are_rejected_atomically(
		string $enabled,
		string $prompt,
		string $experience,
		string $reason
	): void {
		try {
			ProductFieldSubmission::from_raw( $enabled, $prompt, $experience, 'strval' );
			self::fail( 'Expected product field validation to fail.' );
		} catch ( ProductFieldValidationException $exception ) {
			self::assertSame( $reason, $exception->reason() );
		}
	}

	/**
	 * Invalid field cases.
	 *
	 * @return array<string,array{string,string,string,string}>
	 */
	public function provide_invalid_values(): array {
		return array(
			'invalid checkbox value' => array( 'on', 'Prompt', 'auto', ProductFieldValidationException::INVALID_ENABLED ),
			'invalid experience'     => array( 'no', '', 'portrait', ProductFieldValidationException::INVALID_EXPERIENCE ),
			'invalid UTF-8'           => array( 'yes', "Invalid \xC3\x28", 'auto', ProductFieldValidationException::INVALID_UTF8 ),
		);
	}

	/**
	 * The limit counts Unicode characters, not UTF-8 bytes.
	 */
	public function test_prompt_length_uses_unicode_characters(): void {
		$accepted = str_repeat( '界', ProductFieldSubmission::MAX_PROMPT_LENGTH );
		$result   = ProductFieldSubmission::from_raw( 'yes', $accepted, 'auto', 'strval' );

		self::assertSame( $accepted, $result->prompt() );

		try {
			ProductFieldSubmission::from_raw( 'yes', $accepted . '界', 'auto', 'strval' );
			self::fail( 'Expected an overlong Unicode prompt to fail.' );
		} catch ( ProductFieldValidationException $exception ) {
			self::assertSame( ProductFieldValidationException::PROMPT_TOO_LONG, $exception->reason() );
		}
	}
}
