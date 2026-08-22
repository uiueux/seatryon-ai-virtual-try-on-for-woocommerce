<?php
/**
 * Create-time quota preflight tests.
 *
 * @package SeaTryOn\Tests
 */

namespace SeaTryOn\Rest {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $text, string $domain ): string { unset( $domain ); return $text; }
	}
}

namespace SeaTryOn\Tests\Rest {
	use DateTimeImmutable;
	use DateTimeZone;
	use PHPUnit\Framework\TestCase;
	use SeaTryOn\Auth\RequestIdentity;
	use SeaTryOn\Contracts\ClockInterface;
	use SeaTryOn\Quota\QuotaStoreInterface;
	use SeaTryOn\Rest\RestException;
	use SeaTryOn\Rest\WordPressQuotaPreflight;
	use SeaTryOn\Settings\OptionsStoreInterface;
	use SeaTryOn\Settings\SettingsRepository;

	final class QuotaPreflightTest extends TestCase {
		public function test_exhausted_guest_quota_returns_reset_time(): void {
			$state    = array( 'bucket' => '2026-08-09', 'count' => 3, 'dispatches' => array(), 'resets_at' => 0 );
			$service  = new WordPressQuotaPreflight( new PreflightStore( $state ), new SettingsRepository( new PreflightOptions() ), new PreflightClock(), new DateTimeZone( 'Asia/Shanghai' ) );
			$identity = new RequestIdentity( null, str_repeat( 'A', 43 ), str_repeat( 'a', 64 ) );
			try {
				$service->assert_available( $identity );
				$this->fail( 'Expected quota exhaustion.' );
			} catch ( RestException $exception ) {
				$this->assertSame( 429, $exception->http_status() );
				$this->assertSame( '2026-08-10T00:00:00+08:00', $exception->error_data()['reset_at'] );
			}
		}

		public function test_previous_day_bucket_does_not_block_creation(): void {
			$service = new WordPressQuotaPreflight( new PreflightStore( array( 'bucket' => '2026-08-08', 'count' => 100 ) ), new SettingsRepository( new PreflightOptions() ), new PreflightClock(), new DateTimeZone( 'Asia/Shanghai' ) );
			$service->assert_available( new RequestIdentity( 4, null, str_repeat( 'b', 64 ) ) );
			$this->addToAssertionCount( 1 );
		}

		public function test_quota_exempt_manager_skips_preflight_store(): void {
			$store    = new PreflightStore( array( 'bucket' => '2026-08-09', 'count' => 100 ) );
			$service  = new WordPressQuotaPreflight( $store, new SettingsRepository( new PreflightOptions() ), new PreflightClock(), new DateTimeZone( 'Asia/Shanghai' ) );
			$identity = new RequestIdentity( 4, null, str_repeat( 'c', 64 ), true );

			$service->assert_available( $identity );

			$this->assertSame( 0, $store->loads );
		}
	}

	final class PreflightClock implements ClockInterface {
		public function now(): DateTimeImmutable { return new DateTimeImmutable( '2026-08-09T12:00:00+08:00' ); }
	}

	final class PreflightStore implements QuotaStoreInterface {
		/** @var array<string,mixed>|null */ private $state;
		/** @var int */ public $loads = 0;
		/** @param array<string,mixed>|null $state State. */ public function __construct( ?array $state ) { $this->state = $state; }
		public function load( string $identity_key ): ?array { unset( $identity_key ); ++$this->loads; return $this->state; }
		public function save( string $identity_key, array $state ): bool { return true; }
		public function delete( string $identity_key ): bool { return true; }
	}

	final class PreflightOptions implements OptionsStoreInterface {
		/** @param mixed $default Default. @return mixed */
		public function get( string $name, $default = false ) {
			$values = array( SettingsRepository::OPTION_GUEST_DAILY_LIMIT => 3, SettingsRepository::OPTION_LOGGED_IN_DAILY_LIMIT => 4 );
			return isset( $values[ $name ] ) ? $values[ $name ] : $default;
		}
		/** @param mixed $value Value. */ public function update( string $name, $value, bool $autoload = false ): bool { return true; }
		public function add( string $name, string $value, bool $autoload = false ): bool { return true; }
		public function delete( string $name ): bool { return true; }
	}
}
