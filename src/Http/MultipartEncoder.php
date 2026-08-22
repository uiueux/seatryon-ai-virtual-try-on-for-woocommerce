<?php
/**
 * Safe multipart encoder.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Encodes text and ordered file parts without caller-controlled header syntax.
 */
final class MultipartEncoder {

	/**
	 * Encode multipart text and file parts.
	 *
	 * @param array<string,mixed> $fields Text fields in insertion order.
	 * @param array<mixed>        $files  Ordered file parts; repeated names are preserved.
	 * @throws InvalidArgumentException When a part is invalid.
	 */
	public function encode( array $fields, array $files ): MultipartPayload {
		$boundary = 'sea_tryon_' . bin2hex( random_bytes( 24 ) );
		$body     = '';

		foreach ( $fields as $name => $value ) {
			$this->assert_field_name( $name );
			if ( ! is_string( $value ) && ! is_int( $value ) ) {
				throw new InvalidArgumentException( 'Multipart text values must be strings or integers.' );
			}

			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n";
			$body .= (string) $value . "\r\n";
		}

		foreach ( $files as $file ) {
			if ( ! $file instanceof MultipartFile ) {
				throw new InvalidArgumentException( 'Every multipart file must be a MultipartFile.' );
			}

			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $file->field_name() . '"; filename="' . $file->filename() . "\"\r\n";
			$body .= 'Content-Type: ' . $file->mime() . "\r\n\r\n";
			$body .= $file->contents() . "\r\n";
		}

		$body .= '--' . $boundary . "--\r\n";

		return new MultipartPayload( $body, $boundary );
	}

	/**
	 * Validate a text-part field name.
	 *
	 * @param string $name Field name.
	 * @throws InvalidArgumentException When the field name is invalid.
	 */
	private function assert_field_name( string $name ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.\[\]-]{1,64}$/', $name ) ) {
			throw new InvalidArgumentException( 'The multipart field name is invalid.' );
		}
	}
}
