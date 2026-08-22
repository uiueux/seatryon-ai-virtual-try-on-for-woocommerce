<?php
/**
 * WordPress AI image result.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\WordPressAI;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/** Carries an inline image returned by the WordPress AI Client. */
final class WordPressAIImage {

	/**
	 * Decoded image bytes.
	 *
	 * @var string
	 */
	private $bytes;

	/**
	 * Declared image MIME type.
	 *
	 * @var string
	 */
	private $mime_type;

	/**
	 * Create an inline image result.
	 *
	 * @param string $bytes     Decoded image bytes.
	 * @param string $mime_type Declared result MIME type.
	 * @throws InvalidArgumentException When bytes or MIME are invalid.
	 */
	public function __construct( string $bytes, string $mime_type ) {
		if ( '' === $bytes || 0 !== strpos( strtolower( $mime_type ), 'image/' ) ) {
			throw new InvalidArgumentException( 'A valid inline AI image is required.' );
		}

		$this->bytes     = $bytes;
		$this->mime_type = strtolower( $mime_type );
	}

	/** Return decoded image bytes. */
	public function bytes(): string {
		return $this->bytes;
	}

	/** Return the declared MIME type. */
	public function mime_type(): string {
		return $this->mime_type;
	}
}
