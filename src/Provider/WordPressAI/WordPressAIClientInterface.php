<?php
/**
 * WordPress AI Client boundary.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Provider\WordPressAI;

use SeaTryOn\Image\ValidatedImage;

defined( 'ABSPATH' ) || exit;

/** Keeps provider code testable without owning provider credentials. */
interface WordPressAIClientInterface {

	/** Determine whether a configured site connector can edit images. */
	public function supports_image_editing(): bool;

	/**
	 * Generate an image through the provider selected by WordPress.
	 *
	 * @param string           $prompt Prompt text.
	 * @param ValidatedImage[] $images Ordered input images.
	 */
	public function generate_image( string $prompt, array $images ): WordPressAIImage;
}
