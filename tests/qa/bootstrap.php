<?php
/**
 * Shared bootstrap for WordPress integration smoke tests.
 *
 * Set SEA_TRYON_WP_ROOT or pass --wp-root <path>. The plugin file defaults to
 * the source checkout and can be overridden with SEA_TRYON_PLUGIN_FILE or
 * --plugin-file <path>.
 *
 * @package SeaTryOn\Tests\QA
 */

/**
 * Load WordPress for a QA script without embedding a machine-specific path.
 *
 * @param array<int, string> $argv CLI arguments.
 * @return array{wp_root: string, plugin_file: string}
 */
function sea_tryon_qa_bootstrap( array $argv ): array {
	$wp_root     = getenv( 'SEA_TRYON_WP_ROOT' );
	$plugin_file = getenv( 'SEA_TRYON_PLUGIN_FILE' );

	for ( $index = 1, $count = count( $argv ); $index < $count; $index++ ) {
		$argument = (string) $argv[ $index ];
		if ( 0 === strpos( $argument, '--wp-root=' ) ) {
			$wp_root = substr( $argument, 10 );
		} elseif ( '--wp-root' === $argument && isset( $argv[ $index + 1 ] ) ) {
			$wp_root = (string) $argv[ ++$index ];
		} elseif ( 0 === strpos( $argument, '--plugin-file=' ) ) {
			$plugin_file = substr( $argument, 15 );
		} elseif ( '--plugin-file' === $argument && isset( $argv[ $index + 1 ] ) ) {
			$plugin_file = (string) $argv[ ++$index ];
		}
	}

	$wp_root = is_string( $wp_root ) ? trim( $wp_root ) : '';
	if ( '' === $wp_root ) {
		throw new RuntimeException( 'Set SEA_TRYON_WP_ROOT or pass --wp-root <path>.' );
	}

	$resolved_root = realpath( $wp_root );
	if ( false === $resolved_root || ! is_readable( $resolved_root . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
		throw new RuntimeException( 'The configured WordPress root does not contain a readable wp-load.php.' );
	}

	$plugin_file = is_string( $plugin_file ) ? trim( $plugin_file ) : '';
	if ( '' === $plugin_file ) {
		$plugin_file = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'sea-tryon.php';
	}
	if ( ! is_readable( $plugin_file ) ) {
		throw new RuntimeException( 'The configured Sea Try-On plugin file is not readable.' );
	}

	require_once $resolved_root . DIRECTORY_SEPARATOR . 'wp-load.php';

	return array(
		'wp_root'     => $resolved_root,
		'plugin_file' => $plugin_file,
	);
}