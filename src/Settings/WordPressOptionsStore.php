<?php
/**
 * WordPress option persistence adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes plugin options through WordPress public APIs.
 */
final class WordPressOptionsStore implements OptionsStoreInterface {

	/**
	 * Read an option from WordPress.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public function get( string $name, $fallback = null ) {
		return get_option( $name, $fallback );
	}

	/**
	 * Write an option through WordPress.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $value    Option value.
	 * @param bool   $autoload Whether WordPress should autoload the option.
	 */
	public function update( string $name, $value, bool $autoload = false ): bool {
		return update_option( $name, $value, $autoload );
	}
}
