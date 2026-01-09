<?php
/**
 * Remove duplicate helper functions from theme's functions.php
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

$theme_slug = get_stylesheet();
$theme_file = WP_CONTENT_DIR . '/themes/' . $theme_slug . '/functions.php';

if ( ! file_exists( $theme_file ) ) {
	echo "Error: Theme functions.php not found: {$theme_file}\n";
	exit( 1 );
}

$content = file_get_contents( $theme_file );
$original_content = $content;

// List of helper functions to deduplicate
$helper_functions = array(
	'cfa_shortcode_get_paged',
	'cfa_shortcode_render_pagination',
	'cfa_foia_normalize_meta_ids',
	'cfa_foia_is_public_request',
	'cfa_investigation_is_public',
);

$removed_count = 0;

foreach ( $helper_functions as $func_name ) {
	// Find all occurrences of this function
	$pattern = '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*\{.*?\}\s*/s';
	$matches = array();
	preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE );
	
	if ( count( $matches[0] ) > 1 ) {
		// Keep the first occurrence, remove the rest
		$first_match = $matches[0][0];
		$first_pos = $first_match[1];
		$first_length = strlen( $first_match[0] );
		
		// Remove all occurrences
		$content = preg_replace( $pattern, '', $content, -1, $count );
		
		// Re-insert the first occurrence at the original position
		// Find where to insert (before first CPT or shortcode)
		$insert_pos = strpos( $content, 'register_post_type' );
		if ( $insert_pos === false ) {
			$insert_pos = strpos( $content, 'add_shortcode' );
		}
		if ( $insert_pos === false ) {
			// Add at the end
			$content = rtrim( $content ) . "\n\n" . $first_match[0] . "\n";
		} else {
			// Add before first CPT/shortcode
			$content = substr( $content, 0, $insert_pos ) . $first_match[0] . "\n\n" . substr( $content, $insert_pos );
		}
		
		$removed_count += ( $count - 1 );
		echo "Removed " . ( $count - 1 ) . " duplicate(s) of {$func_name}\n";
	}
}

if ( $removed_count > 0 ) {
	if ( file_put_contents( $theme_file, $content ) === false ) {
		echo "Error: Failed to write functions.php\n";
		exit( 1 );
	}
	echo "✅ Removed {$removed_count} duplicate function(s)\n";
} else {
	echo "No duplicates found\n";
}
