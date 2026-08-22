<?php
/**
 * Prompt composer tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Prompt;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeaTryOn\Domain\ExperienceType;
use SeaTryOn\Prompt\PromptComposer;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies controlled person and scene prompt composition.
 */
final class PromptComposerTest extends TestCase {

	/** @var PromptComposer */
	private $composer;

	protected function setUp(): void {
		$this->composer = new PromptComposer();
	}

	/**
	 * @dataProvider providePromptExpectations
	 *
	 * @param string $type     Experience type.
	 * @param string $expected Expected controlled phrase.
	 */
	public function test_composes_controlled_english_prompt_for_every_mode( string $type, string $expected ): void {
		$prompt = $this->composer->compose( ExperienceType::from_string( $type ), 'Keep the blue stitching visible.' );

		self::assertStringContainsString( $expected, $prompt );
		self::assertStringContainsString( 'Keep the selected product\'s color, shape, material', $prompt );
		self::assertStringEndsWith( "Product-specific direction:\nKeep the blue stitching visible.", $prompt );
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public function providePromptExpectations(): array {
		return array(
			'auto'               => array( ExperienceType::AUTO, 'person or scene' ),
			'clothing'           => array( ExperienceType::CLOTHING, 'Dress the person' ),
			'earrings'           => array( ExperienceType::EARRINGS, 'earlobe or ear piercing position' ),
			'rings'              => array( ExperienceType::RINGS, 'appropriate finger' ),
			'necklaces'          => array( ExperienceType::NECKLACES, 'neck and upper chest' ),
			'bracelets'          => array( ExperienceType::BRACELETS, 'appropriate wrist' ),
			'nose rings'         => array( ExperienceType::NOSE_RINGS, 'nostril or septum position' ),
			'belly button rings' => array( ExperienceType::BELLY_BUTTON_RINGS, 'person\'s navel' ),
			'hair accessories'   => array( ExperienceType::HAIR_ACCESSORIES, 'in or around the person\'s hair' ),
			'anklets'            => array( ExperienceType::ANKLETS, 'ankle\'s contour' ),
			'brooches and pins'  => array( ExperienceType::BROOCHES_PINS, 'fabric contact' ),
			'lip rings'          => array( ExperienceType::LIP_RINGS, 'without distorting facial anatomy' ),
			'tongue rings'       => array( ExperienceType::TONGUE_RINGS, 'without distorting oral anatomy' ),
			'body chains'        => array( ExperienceType::BODY_CHAINS, 'connection points, tension, drape' ),
			'legacy jewelry'     => array( ExperienceType::JEWELRY, 'jewelry or accessory' ),
			'glasses'            => array( ExperienceType::GLASSES, 'glasses naturally' ),
			'wig'                => array( ExperienceType::WIG, 'wig naturally' ),
			'furniture'          => array( ExperienceType::FURNITURE, 'furniture naturally' ),
			'product placement'  => array( ExperienceType::PRODUCT_PLACEMENT, 'target scene' ),
		);
	}

	public function test_strips_html_and_control_characters_from_merchant_prompt(): void {
		$prompt = $this->composer->compose(
			ExperienceType::from_string( ExperienceType::CLOTHING ),
			"<strong>Keep</strong>\x00  the seams."
		);

		self::assertStringContainsString( 'Keep the seams.', $prompt );
		self::assertStringNotContainsString( '<strong>', $prompt );
		self::assertStringNotContainsString( "\x00", $prompt );
	}

	public function test_empty_merchant_prompt_uses_only_controlled_templates(): void {
		$prompt = $this->composer->compose( ExperienceType::from_string( ExperienceType::CLOTHING ), ' <br> ' );

		self::assertStringContainsString( 'Dress the person', $prompt );
		self::assertStringNotContainsString( 'Product-specific direction:', $prompt );
	}

	public function test_rejects_prompt_over_two_thousand_characters(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->composer->compose( ExperienceType::from_string( ExperienceType::AUTO ), str_repeat( 'x', 2001 ) );
	}

	public function test_returns_person_scene_and_auto_upload_instructions(): void {
		self::assertStringContainsString( 'person who will wear', $this->composer->upload_instruction( ExperienceType::from_string( ExperienceType::NOSE_RINGS ) ) );
		self::assertStringContainsString( 'room or target scene', $this->composer->upload_instruction( ExperienceType::from_string( ExperienceType::FURNITURE ) ) );
		self::assertStringContainsString( 'person, room, or target scene', $this->composer->upload_instruction( ExperienceType::from_string( ExperienceType::AUTO ) ) );
	}
}
