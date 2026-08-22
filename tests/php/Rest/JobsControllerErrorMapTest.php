<?php
/**
 * REST provider error mapping tests.
 *
 * @package SeaTryOn\Tests
 */

namespace {
	if ( ! class_exists( 'WP_REST_Controller', false ) ) {
		class WP_REST_Controller {
			/** @var string */ protected $namespace = '';
			/** @var string */ protected $rest_base = '';
			/** @var array<string,mixed>|null */ protected $schema;
		}
	}
}

namespace SeaTryOn\Rest {
	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( string $text, string $domain ): string { unset( $domain ); return $text; }
	}
}

namespace SeaTryOn\Tests\Rest {
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;
	use SeaTryOn\Logging\Logger;
	use SeaTryOn\Rest\JobsController;
	use SeaTryOn\Upload\UploadException;

	final class JobsControllerErrorMapTest extends TestCase {
		/** @dataProvider mapping_provider */
		public function test_provider_and_worker_codes_map_to_stable_public_errors( string $input, string $output, int $status ): void {
			$reflection = new ReflectionClass( JobsController::class );
			$controller = $reflection->newInstanceWithoutConstructor();
			$map        = $reflection->getMethod( 'public_error_data' );
			$status_map = $reflection->getMethod( 'status_for_error' );
			$map->setAccessible( true );
			$status_map->setAccessible( true );
			$data = $map->invoke( $controller, $input );

			$this->assertSame( $output, $data['code'] );
			$this->assertSame( $status, $status_map->invoke( $controller, $data['code'] ) );
			$this->assertStringNotContainsString( $input, $data['message'] );
		}

		/** @return array<string,array{string,string,int}> */
		public function mapping_provider(): array {
			return array(
				'moderation'       => array( 'openai_moderation_blocked', 'provider_rejected', 422 ),
				'image user error' => array( 'openai_image_user_error', 'provider_rejected', 422 ),
				'openai invalid'   => array( 'openai_invalid_request', 'provider_rejected', 422 ),
				'seaai invalid'    => array( 'provider_invalid_request', 'provider_rejected', 422 ),
				'openai rate'      => array( 'openai_rate_limited', 'provider_rate_limited', 503 ),
				'seaai rate'       => array( 'provider_rate_limited', 'provider_rate_limited', 503 ),
				'quota exceeded'   => array( 'quota_exceeded', 'quota_exceeded', 429 ),
				'quota store'      => array( 'quota_unavailable', 'configuration_error', 503 ),
				'scheduler'        => array( 'scheduler_unavailable', 'configuration_error', 503 ),
				'authentication'   => array( 'openai_authentication_failed', 'configuration_error', 503 ),
				'balance'          => array( 'provider_insufficient_balance', 'provider_insufficient_balance', 503 ),
				'unknown'          => array( 'provider_contract_error', 'generation_failed', 502 ),
			);
		}

		public function test_server_failure_logs_private_diagnostic_without_changing_public_error(): void {
			$backend    = new RestRecordingLogger();
			$reflection = new ReflectionClass( JobsController::class );
			$controller = $reflection->newInstanceWithoutConstructor();
			$logger     = $reflection->getProperty( 'logger' );
			$logger->setAccessible( true );
			$logger->setValue( $controller, new Logger( $backend ) );
			$method = $reflection->getMethod( 'log_application_failure' );
			$method->setAccessible( true );

			$method->invoke(
				$controller,
				new UploadException( 'configuration_error', 'Public generic message.', 503, 'image_temporary_file_unavailable' ),
				'create_job',
				387
			);

			$this->assertCount( 1, $backend->records );
			$this->assertSame( 'error', $backend->records[0]['level'] );
			$this->assertSame( 'image_temporary_file_unavailable', $backend->records[0]['context']['diagnostic_code'] );
			$this->assertSame( 387, $backend->records[0]['context']['product_id'] );
			$this->assertSame( 'sea-tryon', $backend->records[0]['context']['source'] );
		}
	}

	final class RestRecordingLogger {
		/** @var array<int,array{level:string,message:string,context:array<mixed>}> */
		public $records = array();

		/** @param array<mixed> $context Context. */
		public function log( string $level, string $message, array $context ): void {
			$this->records[] = compact( 'level', 'message', 'context' );
		}
	}
}
