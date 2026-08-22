<?php
/**
 * Trigger rendering context.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Identifies the public adapter requesting shared trigger markup.
 */
final class RenderContext {

	public const AUTOMATIC = 'automatic';
	public const BLOCK     = 'block';

	/**
	 * Stable adapter source.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Create a rendering context.
	 *
	 * @param string $source Adapter source.
	 */
	private function __construct( string $source ) {
		$this->source = $source;
	}

	/** Create an automatic-hook context. */
	public static function automatic(): self {
		return new self( self::AUTOMATIC );
	}

	/** Create a dynamic-block context. */
	public static function block(): self {
		return new self( self::BLOCK );
	}

	/** Return the stable adapter identifier. */
	public function source(): string {
		return $this->source;
	}
}
