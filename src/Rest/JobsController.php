<?php
/**
 * Virtual Try-On REST job controller.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Rest;

use Throwable;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use SeaTryOn\Auth\AuthException;
use SeaTryOn\Auth\RequestAuthenticator;
use SeaTryOn\Auth\RequestIdentity;
use SeaTryOn\Domain\Job;
use SeaTryOn\Domain\JobStatus;
use SeaTryOn\Job\JobSchedulingException;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Storage\TemporaryStorageInterface;
use SeaTryOn\Upload\UploadException;
use SeaTryOn\Upload\UploadService;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Defines the discoverable, ownership-checked asynchronous job API. */
final class JobsController extends WP_REST_Controller {

	/** @var RequestAuthenticator */ private $auth;
	/** @var UploadService */ private $uploads;
	/** @var ProductContextResolverInterface */ private $products;
	/** @var JobApplicationInterface */ private $jobs;
	/** @var QuotaPreflightInterface */ private $quota;
	/** @var TemporaryStorageInterface */ private $storage;
	/** @var Logger */ private $logger;
	/** @var array<string,RequestIdentity> */ private $identities = array();
	/** @var array<string,string> */ private $stream_paths        = array();

	public function __construct( RequestAuthenticator $auth, UploadService $uploads, ProductContextResolverInterface $products, JobApplicationInterface $jobs, TemporaryStorageInterface $storage, QuotaPreflightInterface $quota, ?Logger $logger = null ) {
		$this->namespace = 'sea-tryon/v1';
		$this->rest_base = 'jobs';
		$this->auth      = $auth;
		$this->uploads   = $uploads;
		$this->products  = $products;
		$this->jobs      = $jobs;
		$this->storage   = $storage;
		$this->quota     = $quota;
		$this->logger    = $logger ?? new Logger();
	}

	/** Register route discovery and authenticated binary serving. */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_result' ), 10, 4 );
	}

	/** {@inheritDoc} */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'schema' => array( $this, 'get_public_item_schema' ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => $this->create_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-f0-9]{32,128})',
			array(
				'schema' => array( $this, 'get_public_item_schema' ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->id_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => $this->id_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-f0-9]{32,128})/result',
			array(
				'schema' => array( $this, 'get_public_item_schema' ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_result' ),
					'permission_callback' => array( $this, 'get_result_permissions_check' ),
					'args'                => $this->id_args(),
				),
			)
		);
	}

	/**
	 * Authenticate the creation request before touching its upload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function create_permissions_check( $request ) {
		return $this->authorize( $request, (int) $request->get_param( 'product_id' ), 'create', true );
	}

	/**
	 * Authenticate and enforce object ownership.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->authorize_existing( $request, 'status', false );
	}

	/**
	 * Authenticate and enforce result ownership.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function get_result_permissions_check( $request ) {
		return $this->authorize_existing( $request, 'result', false );
	}

	/**
	 * Authenticate and atomically consume the delete action token.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->authorize_existing( $request, 'delete', true );
	}

	/** Create and schedule a job after authoritative consent, upload and product checks. */
	public function create_item( $request ) {
		try {
			if ( true !== $request->get_param( 'consent' ) ) {
				throw new RestException( 'consent_required', __( 'Please agree to the photo processing notice before continuing.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
			}
			$identity = $this->identity_for( $request, 'create' );
			$this->quota->assert_available( $identity );
			$files = $request->get_file_params();
			if ( ! isset( $files['image'] ) || ! is_array( $files['image'] ) ) {
				throw new UploadException( 'invalid_upload', __( 'Please upload a valid JPEG, PNG, or WebP image.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 400 );
			}

			$upload = $this->uploads->store_customer( $files['image'] );
			try {
				$variation = $request->get_param( 'variation_id' );
				$product   = $this->products->resolve( (int) $request->get_param( 'product_id' ), null === $variation ? null : (int) $variation, $upload->scope_id() );
				$job       = $this->jobs->create( new CreateJobCommand( $identity, (string) $request->get_param( 'idempotency_key' ), $product, $upload->reference() ) );
				if ( $job->customer_image_reference() !== $upload->reference() ) {
					// An owner/idempotency race returned the original job; its scope stays intact.
					$this->storage->delete_scope( $upload->scope_id() );
				}
			} catch ( JobSchedulingException $exception ) {
				$this->cleanup_after_scheduling_failure( $exception, $upload );
				throw $exception;
			} catch ( Throwable $exception ) {
				$this->storage->delete_scope( $upload->scope_id() );
				throw $exception;
			}

			$response = new WP_REST_Response( $this->prepare_job_data( $job ), 202 );
			$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $job->id() ) );
			$response->header( 'Cache-Control', 'no-store, private' );

			return $response;
		} catch ( AuthException $exception ) {
			return $this->error_from_exception( $exception );
		} catch ( UploadException $exception ) {
			$this->log_application_failure( $exception, 'create_job', (int) $request->get_param( 'product_id' ) );
			return $this->error_from_exception( $exception );
		} catch ( RestException $exception ) {
			$this->log_application_failure( $exception, 'create_job', (int) $request->get_param( 'product_id' ) );
			return $this->error( $exception->error_code(), $exception->getMessage(), $exception->http_status(), $exception->error_data() );
		} catch ( Throwable $exception ) {
			$this->log_unhandled_failure( $exception, 'create_job', array( 'product_id' => (int) $request->get_param( 'product_id' ) ) );
			return $this->error( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503 );
		}
	}

	/** Return an ownership-checked status resource. */
	public function get_item( $request ) {
		$job = $this->jobs->find( (string) $request['id'] );
		if ( null === $job ) {
			return $this->not_found();
		}
		$response = new WP_REST_Response( $this->prepare_job_data( $job ), 200 );
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/**
	 * Prepare an authenticated PHP-streamed result response.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_result( WP_REST_Request $request ) {
		$job = $this->jobs->find( (string) $request['id'] );
		if ( null === $job || JobStatus::EXPIRED === $job->status()->value() ) {
			return $this->not_found();
		}
		if ( JobStatus::FAILED === $job->status()->value() ) {
			return $this->job_failure( $job );
		}
		if ( JobStatus::SUCCEEDED !== $job->status()->value() || null === $job->result_reference() || null === $job->result_mime_type() ) {
			return $this->error( 'result_not_ready', __( 'This try-on preview is not ready yet.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 409 );
		}

		try {
			$path = $this->storage->absolute_path( $job->result_reference() );
		} catch ( Throwable $exception ) {
			$this->log_unhandled_failure( $exception, 'read_result', array( 'job_id' => $job->id() ) );
			return $this->error( 'generation_failed', __( 'We could not generate the preview. Please try again.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 502 );
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return $this->not_found();
		}

		$response = new WP_REST_Response( null, 200 );
		$response->header( 'Content-Type', $job->result_mime_type() );
		$response->header( 'Content-Disposition', 'attachment; filename="virtual-try-on-' . $job->id() . '.' . ( 'image/jpeg' === $job->result_mime_type() ? 'jpg' : 'png' ) . '"' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$this->stream_paths[ spl_object_hash( $response ) ] = $path;

		return $response;
	}

	/** Remove job metadata, scheduled actions, input and result files. */
	public function delete_item( $request ) {
		if ( ! $this->jobs->delete( (string) $request['id'] ) ) {
			$this->logger->error(
				'Virtual Try-On REST operation failed.',
				array(
					'operation'       => 'delete_job',
					'diagnostic_code' => 'job_delete_failed',
					'job_id'          => (string) $request['id'],
				)
			);
			return $this->error( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 503 );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Stream a controller-owned result without exposing its private path.
	 *
	 * @param bool            $served Whether served.
	 * @param mixed           $result REST result.
	 * @param WP_REST_Request $request Request.
	 * @param mixed           $server Server.
	 */
	public function serve_result( $served, $result, $request, $server ): bool {
		if ( ! $result instanceof WP_REST_Response ) {
			return (bool) $served;
		}
		$key = spl_object_hash( $result );
		if ( ! isset( $this->stream_paths[ $key ] ) ) {
			return (bool) $served;
		}

		$handle = fopen( $this->stream_paths[ $key ], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Authenticated chunked streaming of a validated private path.
		if ( false === $handle ) {
			return true;
		}
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded stream chunk.
			if ( false === $chunk ) {
				break;
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated binary image response.
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Matching private stream close.

		return true;
	}

	/** {@inheritDoc} */
	/** @return array<string,mixed> */
	public function get_item_schema(): array {
		if ( null !== $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'sea-tryon-job',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'type'     => 'string',
					'pattern'  => '^[a-f0-9]{32,128}$',
					'readonly' => true,
				),
				'status'       => array(
					'type'     => 'string',
					'enum'     => JobStatus::values(),
					'readonly' => true,
				),
				'product_id'   => array(
					'type'     => 'integer',
					'minimum'  => 1,
					'readonly' => true,
				),
				'variation_id' => array(
					'type'     => array( 'integer', 'null' ),
					'minimum'  => 1,
					'readonly' => true,
				),
				'created_at'   => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
				'expires_at'   => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
				'result_url'   => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'uri',
					'readonly' => true,
				),
				'error'        => array(
					'type'       => array( 'object', 'null' ),
					'readonly'   => true,
					'properties' => array(
						'code'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
			),
		);

		return $this->schema;
	}

	/** @return array<string,array<string,mixed>> */
	private function create_args(): array {
		return array(
			'product_id'      => array(
				'type'              => 'integer',
				'required'          => true,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'variation_id'    => array(
				'type'              => 'integer',
				'required'          => false,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'consent'         => array(
				'type'     => 'boolean',
				'required' => true,
			),
			'idempotency_key' => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 16,
				'maxLength' => 128,
				'pattern'   => '^[A-Za-z0-9_-]+$',
			),
		);
	}

	/** @return array<string,array<string,mixed>> */
	private function id_args(): array {
		return array(
			'id' => array(
				'type'     => 'string',
				'required' => true,
				'pattern'  => '^[a-f0-9]{32,128}$',
			),
		);
	}

	/** Authenticate a request once and cache its identity for the callback. */
	/** @return bool|WP_Error */
	private function authorize( WP_REST_Request $request, int $product_id, string $action, bool $consume ) {
		try {
			$this->identities[ $this->identity_key( $request, $action ) ] = $this->auth->authenticate( $request, $product_id, $action, $consume );

			return true;
		} catch ( AuthException $exception ) {
			return $this->error_from_exception( $exception );
		} catch ( Throwable $exception ) {
			return $this->error( 'authentication_required', __( 'Please log in to use Virtual Try-On.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
		}
	}

	/** Resolve a job before verifying its product-bound token and owner hash. */
	/** @return bool|WP_Error */
	private function authorize_existing( WP_REST_Request $request, string $action, bool $consume ) {
		$job = $this->jobs->find( (string) $request['id'] );
		if ( null === $job ) {
			return $this->not_found();
		}
		$allowed = $this->authorize( $request, $job->product_id(), $action, $consume );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$identity = $this->identity_for( $request, $action );

		return hash_equals( $job->owner_hash(), $identity->owner_hash() ) ? true : $this->not_found();
	}

	/** Return an identity cached by the permission callback. */
	private function identity_for( WP_REST_Request $request, string $action ): RequestIdentity {
		$key = $this->identity_key( $request, $action );
		if ( ! isset( $this->identities[ $key ] ) ) {
			throw new AuthException( 'authentication_required', __( 'Please log in to use Virtual Try-On.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
		}

		return $this->identities[ $key ];
	}

	private function identity_key( WP_REST_Request $request, string $action ): string {
		return spl_object_hash( $request ) . '|' . $action;
	}

	/** Preserve only the retained queued job's original private scope. */
	private function cleanup_after_scheduling_failure( JobSchedulingException $exception, \SeaTryOn\Upload\StoredUpload $upload ): void {
		if ( ! $exception->owns_customer_image_reference( $upload->reference() ) ) {
			$this->storage->delete_scope( $upload->scope_id() );
		}
	}

	/** @return array<string,mixed> */
	private function prepare_job_data( Job $job ): array {
		$error = $job->error();
		return array(
			'id'           => $job->id(),
			'status'       => $job->status()->value(),
			'product_id'   => $job->product_id(),
			'variation_id' => $job->variation_id(),
			'created_at'   => $job->created_at()->format( DATE_ATOM ),
			'expires_at'   => $job->expires_at()->format( DATE_ATOM ),
			'result_url'   => JobStatus::SUCCEEDED === $job->status()->value() ? rest_url( $this->namespace . '/' . $this->rest_base . '/' . $job->id() . '/result' ) : null,
			'error'        => null === $error ? null : $this->public_error_data( $error->code() ),
		);
	}

	/** Map a terminal provider error to a stable public REST response. */
	private function job_failure( Job $job ): WP_Error {
		$error = $job->error();
		$data  = $this->public_error_data( null === $error ? 'generation_failed' : $error->code() );

		return $this->error( $data['code'], $data['message'], $this->status_for_error( $data['code'] ) );
	}

	/** @return array{code:string,message:string} */
	private function public_error_data( string $code ): array {
		$aliases = array(
			'openai_moderation_blocked'             => 'provider_rejected',
			'openai_image_user_error'               => 'provider_rejected',
			'openai_invalid_request'                => 'provider_rejected',
			'provider_invalid_request'              => 'provider_rejected',
			'openai_rate_limited'                   => 'provider_rate_limited',
			'openai_service_unavailable'            => 'provider_rate_limited',
			'provider_unavailable'                  => 'provider_rate_limited',
			'openai_authentication_failed'          => 'configuration_error',
			'openai_access_denied'                  => 'configuration_error',
			'openai_quota_exhausted'                => 'configuration_error',
			'provider_auth_missing'                 => 'configuration_error',
			'provider_auth_rejected'                => 'configuration_error',
			'quota_unavailable'                     => 'configuration_error',
			'scheduler_unavailable'                 => 'configuration_error',
			'openai_image_validation_unavailable'   => 'configuration_error',
			'openai_storage_error'                  => 'configuration_error',
			'seaai_storage_error'                   => 'configuration_error',
			'provider_storage_error'                => 'configuration_error',
			'provider_image_validation_unavailable' => 'configuration_error',
		);
		if ( isset( $aliases[ $code ] ) ) {
			$code = $aliases[ $code ];
		}
		$messages = array(
			'provider_rejected'             => __( 'This image could not be processed. Please choose another photo.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'provider_rate_limited'         => __( 'The image service is busy. Please try again shortly.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'provider_insufficient_balance' => __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'configuration_error'           => __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'quota_exceeded'                => __( 'You have reached today’s try-on limit. Please try again tomorrow.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
			'generation_failed'             => __( 'We could not generate the preview. Please try again.', 'seatryon-ai-virtual-try-on-for-woocommerce' ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			$code = 'generation_failed';
		}

		return array(
			'code'    => $code,
			'message' => $messages[ $code ],
		);
	}

	private function status_for_error( string $code ): int {
		if ( 'provider_rejected' === $code ) {
			return 422; }
		if ( 'quota_exceeded' === $code ) {
			return 429; }
		if ( in_array( $code, array( 'provider_rate_limited', 'provider_insufficient_balance', 'configuration_error' ), true ) ) {
			return 503; }
		return 502;
	}

	/**
	 * @param AuthException|UploadException|RestException $exception Safe application exception.
	 */
	private function error_from_exception( $exception ): WP_Error {
		return $this->error( $exception->error_code(), $exception->getMessage(), $exception->http_status() );
	}

	/**
	 * Log a server-side application failure without exposing diagnostics in REST.
	 *
	 * @param UploadException|RestException $exception Application exception.
	 */
	private function log_application_failure( $exception, string $operation, int $product_id ): void {
		if ( $exception->http_status() < 500 ) {
			return;
		}

		$diagnostic_code = method_exists( $exception, 'diagnostic_code' ) ? $exception->diagnostic_code() : '';
		$this->logger->error(
			'Virtual Try-On REST operation failed.',
			array(
				'operation'       => $operation,
				'error_code'      => $exception->error_code(),
				'diagnostic_code' => '' === $diagnostic_code ? 'application_failure' : $diagnostic_code,
				'http_status'     => $exception->http_status(),
				'exception_class' => get_class( $exception ),
				'product_id'      => $product_id,
			)
		);
	}

	/**
	 * Log an unexpected failure through the redacting WooCommerce logger.
	 *
	 * @param Throwable           $exception Unexpected exception.
	 * @param string              $operation Operation identifier.
	 * @param array<string,mixed> $context   Safe operation context.
	 */
	private function log_unhandled_failure( Throwable $exception, string $operation, array $context = array() ): void {
		$this->logger->error(
			'Unhandled Virtual Try-On REST failure.',
			array_merge(
				$context,
				array(
					'operation' => $operation,
					'exception' => $exception,
				)
			)
		);
	}

	private function not_found(): WP_Error {
		return $this->error( 'job_not_found', __( 'This try-on request could not be found or has expired.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 404 );
	}

	/**
	 * Build a stable public REST error.
	 *
	 * @param string              $code    Public error code.
	 * @param string              $message Translated public message.
	 * @param int                 $status  HTTP status.
	 * @param array<string,mixed> $data    Additional public data.
	 */
	private function error( string $code, string $message, int $status, array $data = array() ): WP_Error {
		return new WP_Error( $code, $message, array_merge( $data, array( 'status' => $status ) ) );
	}
}
