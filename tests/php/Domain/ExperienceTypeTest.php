<?php
/**
 * Experience type tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

final class ExperienceTypeTest extends TestCase {

	public function test_exposes_selectable_values_and_retains_legacy_jewelry_support(): void {
		self::assertCount( 19, ExperienceType::values() );
		self::assertCount( 18, ExperienceType::selectable_values() );
		self::assertContains( ExperienceType::EARRINGS, ExperienceType::selectable_values() );
		self::assertContains( ExperienceType::BODY_CHAINS, ExperienceType::selectable_values() );
		self::assertNotContains( ExperienceType::JEWELRY, ExperienceType::selectable_values() );
		self::assertContains( ExperienceType::JEWELRY, ExperienceType::values() );
		self::assertSame( ExperienceType::PRODUCT_PLACEMENT, ExperienceType::from_string( ' PRODUCT_PLACEMENT ' )->value() );
	}

	public function test_classifies_person_scene_and_auto_types(): void {
		$person = ExperienceType::from_string( ExperienceType::WIG );
		$scene  = ExperienceType::from_string( ExperienceType::FURNITURE );
		$auto   = ExperienceType::from_string( ExperienceType::AUTO );

		self::assertTrue( $person->is_person() );
		self::assertFalse( $person->is_scene() );
		self::assertTrue( $scene->is_scene() );
		self::assertFalse( $scene->is_person() );
		self::assertFalse( $auto->is_person() );
		self::assertFalse( $auto->is_scene() );
		self::assertTrue( $person->equals( ExperienceType::from_string( ExperienceType::WIG ) ) );
	}

	public function test_classifies_each_jewelry_subtype_as_a_person_experience(): void {
		$types = array(
			ExperienceType::EARRINGS,
			ExperienceType::RINGS,
			ExperienceType::NECKLACES,
			ExperienceType::BRACELETS,
			ExperienceType::NOSE_RINGS,
			ExperienceType::BELLY_BUTTON_RINGS,
			ExperienceType::HAIR_ACCESSORIES,
			ExperienceType::ANKLETS,
			ExperienceType::BROOCHES_PINS,
			ExperienceType::LIP_RINGS,
			ExperienceType::TONGUE_RINGS,
			ExperienceType::BODY_CHAINS,
			ExperienceType::JEWELRY,
		);

		foreach ( $types as $type ) {
			self::assertTrue( ExperienceType::from_string( $type )->is_person(), $type );
		}
	}

	public function test_rejects_unknown_type(): void {
		$this->expectException( InvalidArgumentException::class );
		ExperienceType::from_string( 'shoes' );
	}
}
