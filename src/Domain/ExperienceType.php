<?php
/**
 * Virtual try-on experience type value object.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * PHP 7.4-compatible replacement for an experience enum.
 */
final class ExperienceType {

	public const AUTO               = 'auto';
	public const CLOTHING           = 'clothing';
	public const HATS               = 'hats';
	public const SHOES              = 'shoes';
	public const HANDBAGS           = 'handbags';
	public const EARRINGS           = 'earrings';
	public const RINGS              = 'rings';
	public const NECKLACES          = 'necklaces';
	public const BRACELETS          = 'bracelets';
	public const NOSE_RINGS         = 'nose_rings';
	public const BELLY_BUTTON_RINGS = 'belly_button_rings';
	public const HAIR_ACCESSORIES   = 'hair_accessories';
	public const ANKLETS            = 'anklets';
	public const BROOCHES_PINS      = 'brooches_pins';
	public const LIP_RINGS          = 'lip_rings';
	public const TONGUE_RINGS       = 'tongue_rings';
	public const BODY_CHAINS        = 'body_chains';
	public const GLASSES            = 'glasses';
	public const WIG                = 'wig';
	public const FURNITURE          = 'furniture';
	public const PRODUCT_PLACEMENT  = 'product_placement';

	/** Legacy value retained so existing products remain usable. */
	public const JEWELRY = 'jewelry';

	/**
	 * Experience type value.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Construct a validated experience type.
	 *
	 * @param string $value Valid experience type value.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * Create a validated experience type.
	 *
	 * @param string $value Raw experience type.
	 * @return self
	 * @throws InvalidArgumentException When the value is unsupported.
	 */
	public static function from_string( string $value ): self {
		$value = strtolower( trim( $value ) );

		if ( ! in_array( $value, self::values(), true ) ) {
			throw new InvalidArgumentException( 'Unsupported experience type.' );
		}

		return new self( $value );
	}

	/**
	 * Return all supported serialized values.
	 *
	 * @return string[]
	 */
	public static function values(): array {
		return array_merge( self::selectable_values(), array( self::JEWELRY ) );
	}

	/**
	 * Return values available to merchants for new product configuration.
	 *
	 * @return string[]
	 */
	public static function selectable_values(): array {
		return array(
			self::AUTO,
			self::CLOTHING,
			self::HATS,
			self::SHOES,
			self::HANDBAGS,
			self::EARRINGS,
			self::RINGS,
			self::NECKLACES,
			self::BRACELETS,
			self::NOSE_RINGS,
			self::BELLY_BUTTON_RINGS,
			self::HAIR_ACCESSORIES,
			self::ANKLETS,
			self::BROOCHES_PINS,
			self::LIP_RINGS,
			self::TONGUE_RINGS,
			self::BODY_CHAINS,
			self::GLASSES,
			self::WIG,
			self::FURNITURE,
			self::PRODUCT_PLACEMENT,
		);
	}

	/**
	 * Return the serialized value.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Whether this type expects a room or target scene image.
	 *
	 * @return bool
	 */
	public function is_scene(): bool {
		return in_array( $this->value, self::scene_values(), true );
	}

	/**
	 * Whether this type explicitly expects a person image.
	 *
	 * @return bool
	 */
	public function is_person(): bool {
		return in_array( $this->value, self::person_values(), true );
	}

	/**
	 * Return values that require a person photo.
	 *
	 * @return string[]
	 */
	public static function person_values(): array {
		return array(
			self::CLOTHING,
			self::HATS,
			self::SHOES,
			self::HANDBAGS,
			self::EARRINGS,
			self::RINGS,
			self::NECKLACES,
			self::BRACELETS,
			self::NOSE_RINGS,
			self::BELLY_BUTTON_RINGS,
			self::HAIR_ACCESSORIES,
			self::ANKLETS,
			self::BROOCHES_PINS,
			self::LIP_RINGS,
			self::TONGUE_RINGS,
			self::BODY_CHAINS,
			self::JEWELRY,
			self::GLASSES,
			self::WIG,
		);
	}

	/**
	 * Return values that require a room or target-scene photo.
	 *
	 * @return string[]
	 */
	public static function scene_values(): array {
		return array( self::FURNITURE, self::PRODUCT_PLACEMENT );
	}

	/**
	 * Compare two experience types.
	 *
	 * @param self $other Other value.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}
}
