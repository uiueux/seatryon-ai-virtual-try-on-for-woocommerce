<?php
/**
 * Safe WooCommerce logger wrapper.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Logging;

use SeaTryOn\Security\Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * Sends already-redacted records to WC_Logger and safely no-ops without WooCommerce.
 */
final class Logger {

	private const SOURCE = 'sea-tryon';

	/**
	 * WC_Logger-compatible backend.
	 *
	 * @var object|null
	 */
	private $logger;

	/**
	 * Record redaction policy.
	 *
	 * @var Redactor
	 */
	private $redactor;

	/**
	 * Whether debug records may be emitted.
	 *
	 * @var bool
	 */
	private $debug_enabled;

	/**
	 * Set up safe logging.
	 *
	 * @param object|null   $logger        Injected WC_Logger-compatible object.
	 * @param Redactor|null $redactor      Redaction policy.
	 * @param bool          $debug_enabled Whether debug-level records are enabled.
	 */
	public function __construct( $logger = null, ?Redactor $redactor = null, bool $debug_enabled = false ) {
		$this->logger        = is_object( $logger ) ? $logger : null;
		$this->redactor      = $redactor ?? new Redactor();
		$this->debug_enabled = $debug_enabled;
	}

	/**
	 * Emit a debug record when explicitly enabled.
	 *
	 * @param string       $message Log message.
	 * @param array<mixed> $context Log context.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( $this->debug_enabled ) {
			$this->log( 'debug', $message, $context );
		}
	}

	/**
	 * Emit an informational record.
	 *
	 * @param string       $message Log message.
	 * @param array<mixed> $context Log context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Emit a warning record.
	 *
	 * @param string       $message Log message.
	 * @param array<mixed> $context Log context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Emit an error record.
	 *
	 * @param string       $message Log message.
	 * @param array<mixed> $context Log context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Emit a normalized record to WooCommerce.
	 *
	 * @param string       $level   Log level.
	 * @param string       $message Log message.
	 * @param array<mixed> $context Log context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$level = strtolower( $level );
		if ( 'debug' === $level && ! $this->debug_enabled ) {
			return;
		}

		$logger = $this->get_logger();
		if ( null === $logger || ! method_exists( $logger, 'log' ) ) {
			return;
		}

		$allowed_levels = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' );
		if ( ! in_array( $level, $allowed_levels, true ) ) {
			$level = 'info';
		}

		$context              = $this->redactor->redact_context( $context );
		$context['source']    = self::SOURCE;
		$context['_redacted'] = true;

		$logger->log( $level, $this->redactor->redact( $message ), $context );
	}

	/**
	 * Resolve the injected or global WooCommerce logger.
	 *
	 * @return object|null
	 */
	private function get_logger() {
		if ( null !== $this->logger ) {
			return $this->logger;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			if ( is_object( $logger ) ) {
				$this->logger = $logger;
				return $this->logger;
			}
		}

		return null;
	}
}
