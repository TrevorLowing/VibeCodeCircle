<?php
/**
 * Verify helper functions exist
 */

// Clear opcache if available
if ( function_exists( 'opcache_reset' ) ) {
	opcache_reset();
}

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

$funcs = array(
	'cfa_shortcode_get_paged',
	'cfa_shortcode_render_pagination',
	'cfa_foia_normalize_meta_ids',
	'cfa_foia_is_public_request',
	'cfa_investigation_is_public',
);

echo "=== Verifying Helper Functions ===" . PHP_EOL;
echo PHP_EOL;

foreach ( $funcs as $f ) {
	$exists = function_exists( $f );
	echo $f . ': ' . ( $exists ? '✅ EXISTS' : '❌ MISSING' ) . PHP_EOL;
}

echo PHP_EOL;
$all_exist = array_reduce( $funcs, function( $carry, $func ) {
	return $carry && function_exists( $func );
}, true );

if ( $all_exist ) {
	echo "✅ All helper functions are available!" . PHP_EOL;
} else {
	echo "❌ Some helper functions are missing!" . PHP_EOL;
}
