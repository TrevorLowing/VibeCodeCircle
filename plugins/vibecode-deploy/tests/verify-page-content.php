<?php
/**
 * Verify page content after deployment
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== Verifying Page Content After Deployment ===" . PHP_EOL;
echo PHP_EOL;

$pages_to_check = array( 'investigations', 'foia-reading-room', 'advisories', 'home' );

foreach ( $pages_to_check as $slug ) {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		echo "Page '{$slug}': ❌ NOT FOUND" . PHP_EOL;
		continue;
	}

	$content = $page->post_content;
	$has_cfa_placeholder = strpos( $content, 'CFA_SHORTCODE' ) !== false;
	$has_vibecode_placeholder = strpos( $content, 'VIBECODE_SHORTCODE' ) !== false;
	$has_shortcode_block = strpos( $content, 'wp:shortcode' ) !== false;
	$has_placeholder = $has_cfa_placeholder || $has_vibecode_placeholder;

	echo "Page '{$slug}':" . PHP_EOL;
	echo "  Has placeholder comment: " . ( $has_placeholder ? '❌ YES (BAD)' : '✅ NO (GOOD)' ) . PHP_EOL;
	echo "  Has shortcode block: " . ( $has_shortcode_block ? '✅ YES (GOOD)' : '❌ NO (BAD)' ) . PHP_EOL;

	if ( $has_shortcode_block ) {
		// Extract shortcode block
		if ( preg_match( '/<!-- wp:shortcode -->\[([^\]]+)\]<!-- \/wp:shortcode -->/', $content, $matches ) ) {
			echo "  Shortcode: [{$matches[1]}]" . PHP_EOL;
		}
	}

	if ( $has_placeholder && ! $has_shortcode_block ) {
		echo "  ⚠️  WARNING: Page has placeholder but no shortcode block!" . PHP_EOL;
	} elseif ( ! $has_placeholder && $has_shortcode_block ) {
		echo "  ✅ GOOD: Placeholder converted to shortcode block" . PHP_EOL;
	}

	echo PHP_EOL;
}

echo "=== Summary ===" . PHP_EOL;
echo "All pages should have shortcode blocks and NO placeholder comments." . PHP_EOL;
