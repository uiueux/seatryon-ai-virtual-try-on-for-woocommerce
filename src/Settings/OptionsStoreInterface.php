<?php
/**
 * Option persistence contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps settings consumers independent from WordPress globals in unit tests.
 */
interface OptionsStoreInterface {

	/**
	 * Read an option.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public function get( string $name, $fallback = null );

	/**
	 * Persist an option.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $value    Option value.
	 * @param bool   $autoload Whether WordPress should autoload the option.
	 */
	public function update( string $name, $value, bool $autoload = false ): bool;
}
