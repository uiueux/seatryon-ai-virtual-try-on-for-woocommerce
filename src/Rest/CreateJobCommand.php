<?php
/**
 * Validated REST-to-job command.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use SeaTryOn\Auth\RequestIdentity;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag

final class CreateJobCommand {
	/** @var RequestIdentity */ private $identity;
	/** @var string */ private $idempotency_key;
	/** @var ProductContext */ private $product;
	/** @var string */ private $customer_image_reference;
	public function __construct( RequestIdentity $identity, string $idempotency_key, ProductContext $product, string $customer_image_reference ) {
		$this->identity                 = $identity;
		$this->idempotency_key          = $idempotency_key;
		$this->product                  = $product;
		$this->customer_image_reference = $customer_image_reference;
	}
	public function identity(): RequestIdentity {
		return $this->identity; }
	public function idempotency_key(): string {
		return $this->idempotency_key; }
	public function product(): ProductContext {
		return $this->product; }
	public function customer_image_reference(): string {
		return $this->customer_image_reference; }
}
