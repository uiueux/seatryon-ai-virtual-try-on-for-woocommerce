<?php
/**
 * Build the plugin POT file without requiring a global WP-CLI installation.
 *
 * @package SeaTryOn\Build
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

$root    = dirname( __DIR__ );
$sources = array( $root . '/src', $root . '/blocks', $root . '/assets/src' );
$messages = array();
$pattern = '/\b(?:__|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*(["\'])((?:\\.|(?!\1).)*)\1\s*,\s*(["\'])seatryon-ai-virtual-try-on-for-woocommerce\3\s*\)/s';

foreach ( $sources as $source ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js' ), true ) ) {
			continue;
		}

		$contents = file_get_contents( $file->getPathname() );
		if ( false === $contents ) {
			throw new RuntimeException( 'Could not read translation source.' );
		}

		if ( ! preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		foreach ( $matches[2] as $index => $match ) {
			$message = stripcslashes( $match[0] );
			$line    = 1 + substr_count( substr( $contents, 0, $matches[0][ $index ][1] ), "\n" );
			$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
			$messages[ $message ][ $relative . ':' . $line ] = true;
		}
	}
}

ksort( $messages, SORT_NATURAL | SORT_FLAG_CASE );
$escape = static function ( string $value ): string {
	return str_replace( array( '\\', '"', "\r", "\n" ), array( '\\\\', '\\"', '', '\\n' ), $value );
};

$output = <<<'POT'
msgid ""
msgstr ""
"Project-Id-Version: SeaTryon – AI Virtual Try-On for WooCommerce 1.1.0\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/seatryon-ai-virtual-try-on-for-woocommerce\n"
"POT-Creation-Date: 2026-08-19 00:00+0000\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: seatryon-ai-virtual-try-on-for-woocommerce\n"

POT;

foreach ( $messages as $message => $references ) {
	$output .= '#: ' . implode( ' ', array_keys( $references ) ) . "\n";
	$output .= 'msgid "' . $escape( $message ) . "\"\n";
	$output .= "msgstr \"\"\n\n";
}

$language_directory = $root . '/languages';
if ( ! is_dir( $language_directory ) && ! mkdir( $language_directory, 0777, true ) && ! is_dir( $language_directory ) ) {
	throw new RuntimeException( 'Could not create languages directory.' );
}

$target = $language_directory . '/seatryon-ai-virtual-try-on-for-woocommerce.pot';
if ( false === file_put_contents( $target, $output ) ) {
	throw new RuntimeException( 'Could not write POT file.' );
}

echo 'Created ' . $target . ' with ' . count( $messages ) . " messages.\n";
