<?php
/**
 * Multipart file value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Http;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Holds one ordered binary multipart part.
 */
final class MultipartFile {

	/**
	 * Form field name.
	 *
	 * @var string
	 */
	private $field_name;

	/**
	 * Safe generated filename.
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Validated MIME type.
	 *
	 * @var string
	 */
	private $mime;

	/**
	 * Binary contents.
	 *
	 * @var string
	 */
	private $contents;

	/**
	 * Set up one multipart file.
	 *
	 * @param string $field_name Form field name.
	 * @param string $filename   Safe generated filename.
	 * @param string $mime       MIME type.
	 * @param string $contents   Binary contents.
	 * @throws InvalidArgumentException When metadata could inject multipart headers.
	 */
	public function __construct( string $field_name, string $filename, string $mime, string $contents ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.\[\]-]{1,64}$/', $field_name ) ) {
			throw new InvalidArgumentException( 'The multipart field name is invalid.' );
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $filename ) || false !== strpbrk( $filename, "\r\n\\\"" ) ) {
			throw new InvalidArgumentException( 'The multipart filename is invalid.' );
		}

		if ( 1 !== preg_match( '~^[a-z0-9][a-z0-9!#$&^_.+-]{0,63}/[a-z0-9][a-z0-9!#$&^_.+-]{0,63}$~i', $mime ) ) {
			throw new InvalidArgumentException( 'The multipart MIME type is invalid.' );
		}

		$this->field_name = $field_name;
		$this->filename   = $filename;
		$this->mime       = strtolower( $mime );
		$this->contents   = $contents;
	}

	/** Return the field name. */
	public function field_name(): string {
		return $this->field_name;
	}

	/** Return the generated filename. */
	public function filename(): string {
		return $this->filename;
	}

	/** Return the MIME type. */
	public function mime(): string {
		return $this->mime;
	}

	/** Return the binary contents. */
	public function contents(): string {
		return $this->contents;
	}
}
