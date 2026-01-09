<?php
/**
 * Test placeholder conversion
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ShortcodePlaceholderService.php';

use VibeCode\Deploy\Services\ShortcodePlaceholderService;

echo "=== Testing Placeholder Conversion ===" . PHP_EOL;
echo PHP_EOL;

$test_placeholders = array(
	'<!-- CFA_SHORTCODE cfa_investigations per_page="10" paginate="1" -->',
	'<!-- CFA_SHORTCODE cfa_foia_index paginate="1" per_page="20" -->',
	'<!-- VIBECODE_SHORTCODE cfa_advisories featured="1" per_page="6" paginate="1" -->',
);

echo "Placeholder prefix: " . ShortcodePlaceholderService::get_placeholder_prefix() . PHP_EOL;
echo PHP_EOL;

foreach ( $test_placeholders as $placeholder ) {
	echo "Testing: {$placeholder}" . PHP_EOL;
	$is_placeholder = ShortcodePlaceholderService::is_placeholder_comment( trim( $placeholder ) );
	echo "  Is placeholder: " . ( $is_placeholder ? '✅ YES' : '❌ NO' ) . PHP_EOL;
	
	$parsed = ShortcodePlaceholderService::parse_placeholder_comment( trim( $placeholder ) );
	echo "  Parsed OK: " . ( ! empty( $parsed['ok'] ) ? '✅ YES' : '❌ NO' ) . PHP_EOL;
	if ( ! empty( $parsed['ok'] ) ) {
		echo "  Name: " . ( $parsed['name'] ?? 'N/A' ) . PHP_EOL;
		echo "  Attrs: " . count( $parsed['attrs'] ?? array() ) . PHP_EOL;
	} else {
		echo "  Error: " . ( $parsed['error'] ?? 'Unknown' ) . PHP_EOL;
	}
	
	$block = ShortcodePlaceholderService::comment_to_shortcode_block( trim( $placeholder ), 'test' );
	echo "  Converted to block: " . ( $block !== null ? '✅ YES' : '❌ NO' ) . PHP_EOL;
	if ( $block !== null ) {
		echo "  Block: {$block}" . PHP_EOL;
	}
	echo PHP_EOL;
}
