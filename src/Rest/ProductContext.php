<?php
/**
 * Trusted server-side product context.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use SeaTryOn\Domain\ExperienceType;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

final class ProductContext {
	/** @var int */ private $product_id;
	/** @var int|null */ private $variation_id;
	/** @var string */ private $provider;
	/** @var ExperienceType */ private $experience_type;
	/** @var string */ private $prompt;
	/** @var string */ private $product_image_reference;
	public function __construct( int $product_id, ?int $variation_id, string $provider, ExperienceType $experience_type, string $prompt, string $product_image_reference ) {
		$this->product_id              = $product_id;
		$this->variation_id            = $variation_id;
		$this->provider                = $provider;
		$this->experience_type         = $experience_type;
		$this->prompt                  = $prompt;
		$this->product_image_reference = $product_image_reference;
	}
	public function product_id(): int {
		return $this->product_id; }
	public function variation_id(): ?int {
		return $this->variation_id; }
	public function provider(): string {
		return $this->provider; }
	public function experience_type(): ExperienceType {
		return $this->experience_type; }
	public function prompt(): string {
		return $this->prompt; }
	public function product_image_reference(): string {
		return $this->product_image_reference; }
}
