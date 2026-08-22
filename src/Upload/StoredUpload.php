<?php
/**
 * Private customer upload reference.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Upload;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.ClassComment.Missing,Squiz.Commenting.FunctionComment.Missing

final class StoredUpload {
	/** @var string */ private $scope_id;
	/** @var string */ private $reference;
	/** @var string */ private $mime_type;

	public function __construct( string $scope_id, string $reference, string $mime_type ) {
		$this->scope_id  = $scope_id;
		$this->reference = $reference;
		$this->mime_type = $mime_type;
	}

	public function scope_id(): string {
		return $this->scope_id; }
	public function reference(): string {
		return $this->reference; }
	public function mime_type(): string {
		return $this->mime_type; }
}
