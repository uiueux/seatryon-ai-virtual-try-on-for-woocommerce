<?php
/**
 * Merge the generated POT into the Simplified Chinese catalog and compile MO.
 *
 * @package SeaTryOn\Build
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

$root       = dirname( __DIR__ );
$wordpress  = dirname( $root, 3 );
$pomo       = $wordpress . '/wp-includes/pomo';
$pot_file   = $root . '/languages/seatryon-ai-virtual-try-on-for-woocommerce.pot';
$po_file    = $root . '/languages/seatryon-ai-virtual-try-on-for-woocommerce-zh_CN.po';
$mo_file    = $root . '/languages/seatryon-ai-virtual-try-on-for-woocommerce-zh_CN.mo';

if ( ! is_readable( $pomo . '/po.php' ) || ! is_readable( $po_file ) || ! is_readable( $pot_file ) ) {
	throw new RuntimeException( 'WordPress POMO files and existing translation catalogs are required.' );
}

require_once $pomo . '/po.php';
require_once $pomo . '/mo.php';

$existing = new PO();
$template = new PO();
if ( ! $existing->import_from_file( $po_file ) || ! $template->import_from_file( $pot_file ) ) {
	throw new RuntimeException( 'Could not import translation catalogs.' );
}

$new_translations = array(
	'AI Virtual Try-On is enabled, but no configured WordPress connector supports image editing. Configure an AI provider under Settings > Connectors.' => 'AI 虚拟试穿已启用，但没有已配置的 WordPress 连接器支持图像编辑。请前往“设置 > 连接器”配置 AI 提供商。',
	'WordPress AI Client (site connector)' => 'WordPress AI 客户端（站点连接器）',
	'WordPress AI uses the provider and credentials configured under Settings > Connectors.' => 'WordPress AI 使用在“设置 > 连接器”中配置的提供商和凭据。',
);

$merged = new PO();
$merged->set_headers( $existing->headers );
$merged->set_header( 'Project-Id-Version', 'SeaTryon – AI Virtual Try-On for WooCommerce 1.1.0' );
$merged->set_header( 'Report-Msgid-Bugs-To', 'https://wordpress.org/support/plugin/seatryon-ai-virtual-try-on-for-woocommerce' );
$merged->set_header( 'X-Domain', 'seatryon-ai-virtual-try-on-for-woocommerce' );
$merged->set_header( 'PO-Revision-Date', '2026-08-19 00:00+0800' );

foreach ( $template->entries as $entry ) {
	$key = $entry->key();
	if ( isset( $existing->entries[ $key ] ) && ! empty( $existing->entries[ $key ]->translations ) ) {
		$entry->translations = $existing->entries[ $key ]->translations;
	} elseif ( isset( $new_translations[ $entry->singular ] ) ) {
		$entry->translations = array( $new_translations[ $entry->singular ] );
	}
	$merged->add_entry( $entry );
}

if ( ! $merged->export_to_file( $po_file ) ) {
	throw new RuntimeException( 'Could not write the merged PO file.' );
}

$compiled          = new MO();
$compiled->entries = $merged->entries;
$compiled->headers = $merged->headers;
if ( ! $compiled->export_to_file( $mo_file ) ) {
	throw new RuntimeException( 'Could not write the compiled MO file.' );
}

echo 'Updated ' . $po_file . ' and ' . $mo_file . "\n";
