<?php
/**
 * SeaAI gateway URL policy tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Settings;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Settings\SeaAIBaseUrlValidator;

defined( 'ABSPATH' ) || exit;

final class SeaAIBaseUrlValidatorTest extends TestCase {

	public function test_https_gateway_is_normalized_in_every_environment(): void {
		$validator = $this->validator( 'production', 'https://store.example/' );

		self::assertSame(
			'https://gateway.example/store/wp-json/seaai/v1',
			$validator->normalize( 'https://gateway.example/store/wp-json/seaai/v1/' )
		);
	}

	public function test_loopback_site_can_save_loopback_http_without_environment_constant(): void {
		$validator = $this->validator( 'production', 'http://localhost/wp/' );

		self::assertSame(
			'http://localhost/wp/wp-json/seaai/v1',
			$validator->normalize( 'http://localhost/wp/wp-json/seaai/v1/' )
		);
		self::assertSame(
			'',
			$validator->normalize( 'http://private-gateway.example/wp-json/seaai/v1' )
		);
	}

	public function test_development_filter_cannot_relax_production_http(): void {
		$filter = static function (): bool {
			return true;
		};
		$production = $this->validator( 'production', 'https://store.example/', $filter );
		$development = $this->validator( 'development', 'https://store.example/', $filter );
		$url = 'http://private-gateway.example.test/wp-json/seaai/v1';

		self::assertSame( '', $production->normalize( $url ) );
		self::assertSame( $url, $development->normalize( $url ) );
	}

	public function test_invalid_or_unsafe_shapes_fail_closed(): void {
		$validator = $this->validator( 'local', 'http://localhost/' );
		$invalid = array(
			'http://localhost.evil.example/wp-json/seaai/v1',
			'http://127.999.999.999/wp-json/seaai/v1',
			'https://user:pass@gateway.example/wp-json/seaai/v1',
			'https://gateway.example/wp-json/seaai/v1?token=secret',
			'https://gateway.example/wp-json/seaai/v1#fragment',
			'https://gateway.example/wp-json/seaai/v1/forward/image/generate',
		);

		foreach ( $invalid as $url ) {
			self::assertSame( '', $validator->normalize( $url ) );
		}
	}

	private function validator( string $environment, string $site_url, ?callable $filter = null ): SeaAIBaseUrlValidator {
		return new SeaAIBaseUrlValidator(
			static function () use ( $environment ): string {
				return $environment;
			},
			static function () use ( $site_url ): string {
				return $site_url;
			},
			$filter
		);
	}
}
