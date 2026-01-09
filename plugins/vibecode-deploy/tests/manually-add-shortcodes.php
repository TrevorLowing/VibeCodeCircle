<?php
/**
 * Manually add shortcodes to functions.php
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );

if ( $active_fingerprint === '' ) {
	echo "❌ No active fingerprint" . PHP_EOL;
	exit( 1 );
}

$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
$staging_file = $build_root . '/theme/functions.php';
$theme = wp_get_theme();
$theme_slug = $theme->get_stylesheet();
$theme_dir = get_theme_root() . '/' . $theme_slug;
$theme_file = $theme_dir . '/functions.php';

echo "=== Manually Adding Shortcodes ===" . PHP_EOL;
echo "Staging file: {$staging_file}" . PHP_EOL;
echo "Theme file: {$theme_file}" . PHP_EOL;
echo PHP_EOL;

if ( ! file_exists( $staging_file ) ) {
	echo "❌ Staging file not found" . PHP_EOL;
	exit( 1 );
}

// Read staging content
$staging_content = file_get_contents( $staging_file );

// Extract shortcodes manually using regex
$shortcodes = array();
preg_match_all( '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(function\s*\([^)]*\)\s*\{.*?\})\s*\)\s*;/s', $staging_content, $matches, PREG_SET_ORDER );

foreach ( $matches as $match ) {
	$tag = $match[1];
	// Find the full block by searching for the complete add_shortcode call
	$start = strpos( $staging_content, $match[0] );
	if ( $start !== false ) {
		// Use balanced brace matching to find the complete block
		$add_pos = strpos( $staging_content, 'add_shortcode', $start );
		$open_paren = strpos( $staging_content, '(', $add_pos );
		$func_pos = strpos( $staging_content, 'function', $open_paren );
		$brace_start = strpos( $staging_content, '{', $func_pos );
		
		$brace_count = 1;
		$search_pos = $brace_start + 1;
		$brace_end = false;
		
		while ( $search_pos < strlen( $staging_content ) && $brace_count > 0 ) {
			$char = $staging_content[ $search_pos ];
			if ( $char === '{' ) {
				$brace_count++;
			} elseif ( $char === '}' ) {
				$brace_count--;
				if ( $brace_count === 0 ) {
					$brace_end = $search_pos;
					break;
				}
			}
			$search_pos++;
		}
		
		if ( $brace_end !== false ) {
			$close_paren = strpos( $staging_content, ')', $brace_end );
			if ( $close_paren !== false ) {
				$semicolon = strpos( $staging_content, ';', $close_paren );
				if ( $semicolon !== false ) {
					$full_block = substr( $staging_content, $add_pos, $semicolon - $add_pos + 1 );
					$shortcodes[ $tag ] = $full_block;
				}
			}
		}
	}
}

echo "Found " . count( $shortcodes ) . " shortcodes to add:" . PHP_EOL;
foreach ( $shortcodes as $tag => $code ) {
	echo "  - {$tag} (" . strlen( $code ) . " bytes)" . PHP_EOL;
}
echo PHP_EOL;

if ( empty( $shortcodes ) ) {
	echo "❌ No shortcodes found to add" . PHP_EOL;
	exit( 1 );
}

// Read theme functions.php
$theme_content = file_exists( $theme_file ) ? file_get_contents( $theme_file ) : "<?php\n\n";

// Remove existing shortcodes
foreach ( array_keys( $shortcodes ) as $tag ) {
	$pattern = '/add_shortcode\s*\(\s*[\'"]' . preg_quote( $tag, '/' ) . '[\'"]\s*,\s*function\s*\([^)]*\)\s*\{.*?\}\s*\)\s*;/s';
	$theme_content = preg_replace( $pattern, '', $theme_content );
}

// Add shortcodes at the end
$theme_content = rtrim( $theme_content ) . "\n\n// Shortcodes\n";
foreach ( $shortcodes as $tag => $code ) {
	$theme_content .= $code . "\n\n";
}

// Write back
if ( file_put_contents( $theme_file, $theme_content ) === false ) {
	echo "❌ Failed to write theme functions.php" . PHP_EOL;
	exit( 1 );
}

echo "✅ Shortcodes added to functions.php" . PHP_EOL;
echo PHP_EOL;

// Verify shortcodes are registered
echo "=== Verifying Shortcode Registration ===" . PHP_EOL;
// Force WordPress to reload functions.php
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

global $shortcode_tags;
$cfa_shortcodes = array_filter( array_keys( $shortcode_tags ), function( $tag ) {
	return strpos( $tag, 'cfa_' ) === 0;
} );

if ( ! empty( $cfa_shortcodes ) ) {
	echo "✅ Found " . count( $cfa_shortcodes ) . " registered shortcodes:" . PHP_EOL;
	foreach ( $cfa_shortcodes as $tag ) {
		echo "   - {$tag}" . PHP_EOL;
	}
} else {
	echo "⚠️  Shortcodes not yet registered (may need page reload)" . PHP_EOL;
}
