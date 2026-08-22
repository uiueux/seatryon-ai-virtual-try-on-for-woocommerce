<?php
/**
 * Identifier generator contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Generates opaque identifiers for domain entities.
 */
interface IdGeneratorInterface {

	/**
	 * Generate a new opaque identifier.
	 *
	 * @return string
	 */
	public function generate(): string;
}
