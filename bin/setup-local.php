<?php
/**
 * Local DB setup helper for SystemPAK Black Friday plugin.
 * Invoked from PowerShell with mysql HEX dumps — no mysqli extension required.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

function spbf_read_hex_file( $path ) {
	$hex = preg_replace( '/\s+/', '', (string) file_get_contents( $path ) );
	$bin = @hex2bin( $hex );
	if ( false === $bin ) {
		fwrite( STDERR, "Invalid hex in $path\n" );
		exit( 1 );
	}
	return $bin;
}

function spbf_write_hex_file( $path, $bin ) {
	file_put_contents( $path, strtoupper( bin2hex( $bin ) ) );
}

$cmd = $argv[1] ?? '';

if ( 'activate-plugin' === $cmd ) {
	$in_hex  = $argv[2];
	$out_hex = $argv[3];
	$plugins = @unserialize( spbf_read_hex_file( $in_hex ) );
	if ( ! is_array( $plugins ) ) {
		fwrite( STDERR, "Failed to unserialize active_plugins\n" );
		exit( 1 );
	}
	$target = 'systempak-black-friday-1p/systempak-black-friday-1p.php';
	if ( ! in_array( $target, $plugins, true ) ) {
		$plugins[] = $target;
		sort( $plugins );
	}
	spbf_write_hex_file( $out_hex, serialize( $plugins ) );
	echo 'PLUGIN_SERIALIZED count=' . count( $plugins ) . PHP_EOL;
	exit( 0 );
}

if ( 'check-plugin' === $cmd ) {
	$plugins = @unserialize( spbf_read_hex_file( $argv[2] ) );
	$target  = 'systempak-black-friday-1p/systempak-black-friday-1p.php';
	echo ( is_array( $plugins ) && in_array( $target, $plugins, true ) ) ? "PLUGIN_ACTIVE\n" : "PLUGIN_MISSING\n";
	exit( 0 );
}

if ( 'settings' === $cmd ) {
	$page_id = isset( $argv[2] ) ? (int) $argv[2] : 0;
	$settings = array(
		'visibility'          => 'admin_only',
		'start_datetime'      => '2026-11-27 00:00:00',
		'end_datetime'        => '2026-11-30 17:00:00',
		'timezone'            => 'Europe/London',
		'promo_price'         => '0.01',
		'delivery_per_box'    => '10.95',
		'max_qty_per_product' => 4,
		'fee_taxable'         => 'yes',
		'fee_tax_class'       => '',
		'fee_label'           => 'Black Friday delivery (per box)',
		'collection_page_id'  => $page_id,
		'show_product_notice' => 'yes',
		'show_banner'         => 'yes',
		'allowed_countries'   => array( 'GB' ),
	);
	spbf_write_hex_file( $argv[3], serialize( $settings ) );
	echo "SETTINGS_SERIALIZED page_id={$page_id}\n";
	exit( 0 );
}

if ( 'page-content' === $cmd ) {
	$content = "<!-- wp:shortcode -->\n[spbf_collection]\n<!-- /wp:shortcode -->";
	spbf_write_hex_file( $argv[2], $content );
	echo "PAGE_CONTENT_OK\n";
	exit( 0 );
}

fwrite( STDERR, "Unknown command: $cmd\n" );
exit( 1 );
