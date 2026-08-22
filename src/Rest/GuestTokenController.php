<?php
/**
 * Guest action-token refresh endpoint.
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
use SeaTryOn\Auth\ActionTokenService;
use SeaTryOn\Auth\AuthException;
use SeaTryOn\Auth\GuestSessionManager;
use SeaTryOn\Auth\SameOriginPolicy;
use SeaTryOn\Logging\Logger;
use SeaTryOn\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionCommentThrowTag.Missing,Squiz.Commenting.FunctionComment.ParamCommentFullStop,WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Reissues short-lived tokens without exposing the HttpOnly guest session. */
final class GuestTokenController extends WP_REST_Controller {
	/** @var SettingsRepository */ private $settings;
	/** @var GuestSessionManager */ private $sessions;
	/** @var SameOriginPolicy */ private $origins;
	/** @var ActionTokenService */ private $tokens;
	/** @var Logger */ private $logger;

	public function __construct( SettingsRepository $settings, GuestSessionManager $sessions, SameOriginPolicy $origins, ActionTokenService $tokens, ?Logger $logger = null ) {
		$this->namespace = 'sea-tryon/v1';
		$this->rest_base = 'guest-token';
		$this->settings  = $settings;
		$this->sessions  = $sessions;
		$this->origins   = $origins;
		$this->tokens    = $tokens;
		$this->logger    = $logger ?? new Logger();
	}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'schema' => array( $this, 'get_public_item_schema' ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'product_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/** @param WP_REST_Request $request Request. @return bool|WP_Error */
	public function create_item_permissions_check( $request ) {
		try {
			if ( is_user_logged_in() || ! $this->settings->allow_guests() ) {
				throw new AuthException( 'authentication_required', __( 'A valid guest session is required.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
			}
			$this->origins->assert_request( $request );
			if ( null === $this->sessions->current() ) {
				throw new AuthException( 'authentication_required', __( 'A valid guest session is required.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), 401 );
			}
			return true;
		} catch ( AuthException $exception ) {
			return new WP_Error( $exception->error_code(), $exception->getMessage(), array( 'status' => $exception->http_status() ) );
		}
	}

	/** Return only action tokens; never return the session cookie value. */
	public function create_item( $request ) {
		$session = $this->sessions->current();
		$product = (int) $request->get_param( 'product_id' );
		if ( null === $session ) {
			return new WP_Error( 'authentication_required', __( 'A valid guest session is required.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), array( 'status' => 401 ) );
		}
		try {
			$response = new WP_REST_Response(
				array(
					'create' => $this->tokens->issue( $session, $product, 'create', 900 ),
					'status' => $this->tokens->issue( $session, $product, 'status', 900 ),
					'result' => $this->tokens->issue( $session, $product, 'result', 900 ),
					'delete' => $this->tokens->issue( $session, $product, 'delete', 900 ),
				),
				200
			);
			$response->header( 'Cache-Control', 'no-store, private' );
			return $response;
		} catch ( Throwable $exception ) {
			$this->logger->error(
				'Guest Virtual Try-On token issuance failed.',
				array(
					'operation'  => 'issue_guest_tokens',
					'product_id' => $product,
					'exception'  => $exception,
				)
			);
			return new WP_Error( 'configuration_error', __( 'Virtual Try-On is temporarily unavailable. Please contact the store.', 'seatryon-ai-virtual-try-on-for-woocommerce' ), array( 'status' => 503 ) );
		}
	}

	/** @return array<string,mixed> */
	public function get_item_schema(): array {
		if ( null === $this->schema ) {
			$this->schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'sea-tryon-guest-token',
				'type'       => 'object',
				'properties' => array(
					'create' => array(
						'type'     => 'string',
						'readonly' => true,
					),
					'status' => array(
						'type'     => 'string',
						'readonly' => true,
					),
					'result' => array(
						'type'     => 'string',
						'readonly' => true,
					),
					'delete' => array(
						'type'     => 'string',
						'readonly' => true,
					),
				),
			);
		}
		return $this->schema;
	}
}
