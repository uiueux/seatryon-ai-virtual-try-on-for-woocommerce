<?php
/**
 * Controlled English prompt composer.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Prompt;

use InvalidArgumentException;
use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

/**
 * Composes provider prompts without depending on WordPress or WooCommerce.
 */
final class PromptComposer {

	private const COMMON_FIDELITY = 'Use the first reference image as the person or target scene and the second reference image as the selected product. Create exactly one realistic image. Keep the selected product\'s color, shape, material, branding, proportions, and visible details accurate. Do not add unrelated products, text, logos, watermarks, borders, or a collage.';

	private const AUTO_TEMPLATE = 'Place or apply the selected product naturally in the uploaded person or scene, following the product-specific direction. Preserve the subject or scene, composition, perspective, background, and lighting wherever the requested placement does not require a change.';

	private const CLOTHING_TEMPLATE = 'Dress the person in the selected clothing naturally. Preserve the person\'s identity, face, skin tone, hair, pose, body proportions, hands, background, camera angle, and lighting. Adjust only the garment fit, drape, folds, and occlusion needed for a physically plausible result.';

	private const HATS_TEMPLATE = 'Fit the selected hat naturally on the person\'s head. Preserve the person\'s identity, face, skin tone, hair outside the placement area, expression, head pose, body proportions, background, camera angle, and lighting. Keep the hat\'s size, shape, structure, brim orientation, material, color, details, shadows, and occlusion physically plausible.';

	private const SHOES_TEMPLATE = 'Fit the selected shoes naturally on the person\'s feet. Preserve the person\'s identity, body proportions, pose, leg and foot anatomy, clothing outside the placement area, background, camera angle, and lighting. Keep the shoe or pair\'s size, orientation, shape, material, color, details, ground contact, shadows, and occlusion physically plausible.';

	private const HANDBAGS_TEMPLATE = 'Place the selected handbag naturally in the person\'s hand or on the appropriate shoulder according to the product design. Preserve the person\'s identity, face, skin tone, body and hand anatomy, pose, clothing outside the placement area, background, camera angle, and lighting. Keep the handbag\'s scale, strap position and tension, orientation, shape, material, color, details, contact, shadows, and occlusion physically plausible.';

	private const EARRINGS_TEMPLATE = 'Place the selected earrings naturally on the person\'s ears. Preserve the person\'s identity, face, skin tone, hair, expression, head pose, background, camera angle, and lighting. Match the product\'s intended single or pair design, align it with the appropriate earlobe or ear piercing position, and keep scale, orientation, metal or gemstone details, reflections, shadows, and occlusion physically plausible.';

	private const RINGS_TEMPLATE = 'Place the selected ring naturally on the appropriate finger. Preserve the person\'s identity, hand anatomy, skin tone, pose, fingernails, background, camera angle, and lighting. Follow the finger\'s perspective and contour, and keep the ring\'s size, orientation, setting, metal or gemstone details, reflections, shadows, and occlusion physically plausible.';

	private const NECKLACES_TEMPLATE = 'Place the selected necklace naturally around the person\'s neck and upper chest. Preserve the person\'s identity, face, skin tone, hair, pose, clothing, background, camera angle, and lighting. Keep the chain or strand drape, clasp direction, pendant position, scale, reflections, shadows, and occlusion physically plausible.';

	private const BRACELETS_TEMPLATE = 'Place the selected bracelet naturally around the appropriate wrist. Preserve the person\'s identity, hand and wrist anatomy, skin tone, pose, clothing, background, camera angle, and lighting. Follow the wrist\'s contour and perspective, and keep the bracelet\'s fit, orientation, material details, reflections, shadows, and occlusion physically plausible.';

	private const NOSE_RINGS_TEMPLATE = 'Fit the selected nose ring naturally at the appropriate nostril or septum position indicated by the product design. Preserve the person\'s identity, facial features, skin tone, expression, head pose, hair, background, camera angle, and lighting. Keep the attachment point, scale, orientation, metal or gemstone details, reflections, shadows, and occlusion physically plausible.';

	private const BELLY_BUTTON_RINGS_TEMPLATE = 'Fit the selected belly button ring naturally at the person\'s navel. Preserve the person\'s identity, skin tone, body anatomy, pose, clothing outside the placement area, background, camera angle, and lighting. Keep the piercing position, scale, orientation, metal or gemstone details, reflections, shadows, and occlusion physically plausible.';

	private const HAIR_ACCESSORIES_TEMPLATE = 'Place the selected hair accessory naturally in or around the person\'s hair according to the product design. Preserve the person\'s identity, facial features, skin tone, expression, head pose, hairstyle outside the attachment area, background, camera angle, and lighting. Keep the attachment, scale, orientation, material details, reflections, shadows, and occlusion physically plausible.';

	private const ANKLETS_TEMPLATE = 'Place the selected anklet naturally around the appropriate ankle. Preserve the person\'s identity, foot and ankle anatomy, skin tone, leg pose, footwear, clothing, background, camera angle, and lighting. Follow the ankle\'s contour and perspective, and keep the anklet\'s fit, drape, clasp direction, material details, reflections, shadows, and occlusion physically plausible.';

	private const BROOCHES_PINS_TEMPLATE = 'Attach the selected brooch or pin naturally to the appropriate area of the person\'s clothing according to the product design. Preserve the person\'s identity, pose, clothing structure, fabric texture and pattern outside the attachment area, background, camera angle, and lighting. Keep the attachment point, scale, orientation, material details, reflections, shadows, fabric contact, and occlusion physically plausible.';

	private const LIP_RINGS_TEMPLATE = 'Fit the selected lip ring naturally at the appropriate lip piercing position indicated by the product design. Preserve the person\'s identity, facial features, lips, teeth, skin tone, expression, head pose, background, camera angle, and lighting. Keep the attachment point, scale, orientation, metal or gemstone details, reflections, shadows, and occlusion physically plausible without distorting facial anatomy.';

	private const TONGUE_RINGS_TEMPLATE = 'Fit the selected tongue ring naturally at the appropriate tongue piercing position indicated by the product design. Preserve the person\'s identity, mouth, lips, teeth, tongue anatomy, skin tone, expression, head pose, background, camera angle, and lighting. Keep the piercing position, scale, orientation, metal or gemstone details, reflections, shadows, and occlusion physically plausible without distorting oral anatomy.';

	private const BODY_CHAINS_TEMPLATE = 'Drape the selected body chain naturally over and around the appropriate torso, shoulder, waist, or hip area indicated by the product design. Preserve the person\'s identity, body anatomy, skin tone, pose, clothing outside the placement area, background, camera angle, and lighting. Keep the chain\'s connection points, tension, drape, scale, orientation, material details, reflections, shadows, and occlusion physically plausible.';

	/** Legacy fallback for products saved before jewelry was split into specific types. */
	private const JEWELRY_TEMPLATE = 'Place the selected jewelry or accessory naturally on the appropriate part of the person. Preserve the person\'s identity, face, skin tone, hair, pose, body proportions, background, camera angle, and lighting. Keep attachment, scale, reflections, shadows, and occlusion physically plausible.';

	private const GLASSES_TEMPLATE = 'Fit the selected glasses naturally on the person\'s face. Preserve identity, facial features, skin tone, hair, expression, head pose, background, camera angle, and lighting. Align the bridge and temples correctly and keep lens transparency, reflections, scale, and occlusion physically plausible.';

	private const WIG_TEMPLATE = 'Fit the selected wig naturally on the person\'s head. Preserve identity, facial features, skin tone, expression, head pose, body proportions, background, camera angle, and lighting. Keep the wig\'s cut, color, texture, hairline, volume, and occlusion accurate.';

	private const FURNITURE_TEMPLATE = 'Place the selected furniture naturally into the uploaded room or scene. Preserve the scene layout, architecture, existing objects, camera perspective, background, and lighting. Use realistic physical scale, floor or wall contact, shadows, reflections, and occlusion without removing unrelated scene content.';

	private const PRODUCT_PLACEMENT_TEMPLATE = 'Place the selected product naturally into the uploaded target scene. Preserve the scene layout, architecture, existing objects, camera perspective, background, and lighting. Use realistic scale, contact, shadows, reflections, and occlusion without removing unrelated scene content.';

	/**
	 * Compose a controlled provider prompt with merchant-owned direction.
	 *
	 * @param ExperienceType $experience_type Experience mode.
	 * @param string         $merchant_prompt Product-specific direction, maximum 2,000 characters.
	 * @return string
	 */
	public function compose( ExperienceType $experience_type, string $merchant_prompt ): string {
		$merchant_prompt = $this->normalize_merchant_prompt( $merchant_prompt );
		$template        = $this->template_for( $experience_type );
		$prompt          = self::COMMON_FIDELITY . "\n\n" . $template;

		if ( '' === $merchant_prompt ) {
			return $prompt;
		}

		return $prompt . "\n\nProduct-specific direction:\n" . $merchant_prompt;
	}

	/**
	 * Return controlled image-upload guidance for the selected mode.
	 *
	 * @param ExperienceType $experience_type Experience mode.
	 * @return string
	 */
	public function upload_instruction( ExperienceType $experience_type ): string {
		if ( $experience_type->is_person() ) {
			return 'Upload a clear photo of the person who will wear the selected product.';
		}

		if ( $experience_type->is_scene() ) {
			return 'Upload a clear photo of the room or target scene where the selected product should appear.';
		}

		return 'Upload a clear photo of the person, room, or target scene where the selected product should appear.';
	}

	/**
	 * Resolve a type-specific controlled template.
	 *
	 * @param ExperienceType $experience_type Experience mode.
	 * @return string
	 */
	private function template_for( ExperienceType $experience_type ): string {
		$templates = array(
			ExperienceType::AUTO               => self::AUTO_TEMPLATE,
			ExperienceType::CLOTHING           => self::CLOTHING_TEMPLATE,
			ExperienceType::HATS               => self::HATS_TEMPLATE,
			ExperienceType::SHOES              => self::SHOES_TEMPLATE,
			ExperienceType::HANDBAGS           => self::HANDBAGS_TEMPLATE,
			ExperienceType::EARRINGS           => self::EARRINGS_TEMPLATE,
			ExperienceType::RINGS              => self::RINGS_TEMPLATE,
			ExperienceType::NECKLACES          => self::NECKLACES_TEMPLATE,
			ExperienceType::BRACELETS          => self::BRACELETS_TEMPLATE,
			ExperienceType::NOSE_RINGS         => self::NOSE_RINGS_TEMPLATE,
			ExperienceType::BELLY_BUTTON_RINGS => self::BELLY_BUTTON_RINGS_TEMPLATE,
			ExperienceType::HAIR_ACCESSORIES   => self::HAIR_ACCESSORIES_TEMPLATE,
			ExperienceType::ANKLETS            => self::ANKLETS_TEMPLATE,
			ExperienceType::BROOCHES_PINS      => self::BROOCHES_PINS_TEMPLATE,
			ExperienceType::LIP_RINGS          => self::LIP_RINGS_TEMPLATE,
			ExperienceType::TONGUE_RINGS       => self::TONGUE_RINGS_TEMPLATE,
			ExperienceType::BODY_CHAINS        => self::BODY_CHAINS_TEMPLATE,
			ExperienceType::JEWELRY            => self::JEWELRY_TEMPLATE,
			ExperienceType::GLASSES            => self::GLASSES_TEMPLATE,
			ExperienceType::WIG                => self::WIG_TEMPLATE,
			ExperienceType::FURNITURE          => self::FURNITURE_TEMPLATE,
			ExperienceType::PRODUCT_PLACEMENT  => self::PRODUCT_PLACEMENT_TEMPLATE,
		);

		return $templates[ $experience_type->value() ];
	}

	/**
	 * Normalize and constrain trusted merchant direction.
	 *
	 * @param string $prompt Merchant prompt.
	 * @return string
	 * @throws InvalidArgumentException When the prompt is empty, invalid, or too long.
	 */
	private function normalize_merchant_prompt( string $prompt ): string {
		// WordPress is intentionally unavailable in this pure domain component.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		$prompt = strip_tags( $prompt );
		$prompt = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $prompt );

		if ( null === $prompt ) {
			throw new InvalidArgumentException( 'Product prompt is invalid.' );
		}

		$prompt = trim( preg_replace( '/[ \t]+/', ' ', $prompt ) ?? '' );

		$character_count = preg_match_all( '/./us', $prompt, $matches );

		if ( false === $character_count ) {
			throw new InvalidArgumentException( 'Product prompt must be valid UTF-8.' );
		}

		if ( $character_count > 2000 ) {
			throw new InvalidArgumentException( 'Product prompt must not exceed 2,000 characters.' );
		}

		return $prompt;
	}
}
