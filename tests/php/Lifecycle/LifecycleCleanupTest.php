<?php
/**
 * Lifecycle cleanup tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Tests\Lifecycle;

use PHPUnit\Framework\TestCase;
use SeaTryOn\Lifecycle\SiteDataCleaner;

/**
 * Verifies scheduled cleanup and deactivation/uninstall retention boundaries.
 */
final class LifecycleCleanupTest extends TestCase {

	/** Deactivation removes runtime personal data while preserving configuration. */
	public function test_deactivation_preserves_merchant_configuration(): void {
		$options = array(
			'sea_tryon_enabled',
			'sea_tryon_openai_api_key',
			'sea_tryon_success_count',
			'sea_tryon_data_version',
			'sea_tryon_job_abc',
			'sea_tryon_job_index_v1',
			'sea_tryon_quota_abc',
			'sea_tryon_replay_abc',
			'sea_tryon_success_job_abc',
		);
		$deleted = array();
		$hooks   = array();
		$purged  = 0;
		$cleaner = new SiteDataCleaner(
			static function ( string $prefix ) use ( &$options ): array {
				return array_values(
					array_filter(
						$options,
						static function ( string $name ) use ( $prefix ): bool {
							return 0 === strpos( $name, $prefix );
						}
					)
				);
			},
			static function ( string $name ) use ( &$deleted ): bool {
				$deleted[] = $name;
				return true;
			},
			static function (): bool { return true; },
			static function ( string $hook ) use ( &$hooks ): void { $hooks[] = $hook; },
			static function () use ( &$purged ): bool {
				++$purged;
				return true;
			},
			static function (): void {}
		);

		$cleaner->deactivate_site();

		self::assertContains( 'sea_tryon_job_abc', $deleted );
		self::assertContains( 'sea_tryon_job_index_v1', $deleted );
		self::assertContains( 'sea_tryon_quota_abc', $deleted );
		self::assertContains( 'sea_tryon_replay_abc', $deleted );
		self::assertContains( 'sea_tryon_success_job_abc', $deleted );
		self::assertNotContains( 'sea_tryon_enabled', $deleted );
		self::assertNotContains( 'sea_tryon_openai_api_key', $deleted );
		self::assertNotContains( 'sea_tryon_success_count', $deleted );
		self::assertNotContains( 'sea_tryon_data_version', $deleted );
		self::assertSame( '', $hooks[0] );
		self::assertSame( 4, count( array_unique( $hooks ) ) );
		self::assertContains( 'sea_tryon_process_job', $hooks );
		self::assertSame( 1, $purged );
	}

	/** Uninstall removes every plugin option and all product configuration meta. */
	public function test_uninstall_removes_configuration_secrets_and_product_meta(): void {
		$options = array(
			'sea_tryon_enabled',
			'sea_tryon_openai_api_key',
			'sea_tryon_seaai_api_key',
			'sea_tryon_data_version',
			'unrelated_option',
		);
		$deleted = array();
		$meta    = array();
		$cleaner = new SiteDataCleaner(
			static function ( string $prefix ) use ( &$options ): array {
				return array_values( array_filter( $options, static function ( string $name ) use ( $prefix ): bool { return 0 === strpos( $name, $prefix ); } ) );
			},
			static function ( string $name ) use ( &$deleted ): bool {
				$deleted[] = $name;
				return true;
			},
			static function ( string $key ) use ( &$meta ): bool {
				$meta[] = $key;
				return true;
			},
			static function (): void {},
			static function (): bool { return true; },
			static function (): void {}
		);

		$cleaner->uninstall_site();

		self::assertContains( 'sea_tryon_enabled', $deleted );
		self::assertContains( 'sea_tryon_openai_api_key', $deleted );
		self::assertContains( 'sea_tryon_seaai_api_key', $deleted );
		self::assertContains( 'sea_tryon_data_version', $deleted );
		self::assertNotContains( 'unrelated_option', $deleted );
		self::assertSame( array( '_sea_tryon_enabled', '_sea_tryon_prompt', '_sea_tryon_experience_type', '_sea_tryon_product_image_id' ), $meta );
	}

	/** A private storage failure is surfaced through a sanitized reporter. */
	public function test_deactivation_reports_private_storage_cleanup_failure(): void {
		$reported = 0;
		$cleaner  = new SiteDataCleaner(
			static function (): array { return array(); },
			static function (): bool { return true; },
			static function (): bool { return true; },
			static function (): void {},
			static function (): bool { return false; },
			static function (): void {},
			static function () use ( &$reported ): void { ++$reported; }
		);

		$cleaner->deactivate_site();

		self::assertSame( 1, $reported );
	}
}
