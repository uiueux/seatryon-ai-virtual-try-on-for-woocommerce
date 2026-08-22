<?php
/**
 * Plugin skeleton smoke tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Dependencies;
use SeaTryOn\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the first-party PSR-4 classes can load without WordPress state.
 */
final class PluginSmokeTest extends TestCase {

	/**
	 * The plugin coordinator is a stable singleton.
	 */
	public function test_plugin_instance_is_stable(): void {
		self::assertSame( Plugin::instance(), Plugin::instance() );
	}

	/**
	 * The dependency service exposes the frozen WooCommerce baseline.
	 */
	public function test_minimum_woocommerce_version(): void {
		self::assertSame( '10.9', Dependencies::minimum_woocommerce_version() );
	}
}

