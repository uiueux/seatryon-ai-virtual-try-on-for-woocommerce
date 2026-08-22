<?php
/**
 * Cryptographically secure identifier generator.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Domain;

use SeaTryOn\Contracts\IdGeneratorInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Generates 128-bit opaque hexadecimal job identifiers.
 */
final class CSPRNGIdGenerator implements IdGeneratorInterface {

	/**
	 * Generate a 128-bit CSPRNG identifier.
	 *
	 * @return string
	 */
	public function generate(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
