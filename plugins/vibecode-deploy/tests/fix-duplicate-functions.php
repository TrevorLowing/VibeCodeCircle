<?php
/**
 * Fix duplicate helper functions in theme's functions.php
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
$kept_functions = array();

foreach ( $helper_functions as $func_name ) {
	// Find all occurrences of this function using balanced brace matching
	$pattern = '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*\{/';
	$matches = array();
	preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE );
	
	if ( count( $matches[0] ) > 1 ) {
		// Extract the first complete function
		$first_pos = $matches[0][0][1];
		$brace_start = strpos( $content, '{', $first_pos );
		
		if ( $brace_start !== false ) {
			// Balance braces to find closing brace
			$brace_count = 1;
			$search_pos = $brace_start + 1;
			$brace_end = false;
			
			while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
				$char = $content[ $search_pos ];
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
				$first_function = substr( $content, $first_pos, $brace_end - $first_pos + 1 );
				$kept_functions[ $func_name ] = $first_function;
				
				// Remove all occurrences
				$content = preg_replace( '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*\{.*?\}\s*/s', '', $content );
				
				$removed_count += ( count( $matches[0] ) - 1 );
				echo "Removed " . ( count( $matches[0] ) - 1 ) . " duplicate(s) of {$func_name}\n";
			}
		}
	}
}

// Re-insert kept functions at the beginning (after <?php)
if ( ! empty( $kept_functions ) ) {
	$php_pos = strpos( $content, '<?php' );
	if ( $php_pos !== false ) {
		$end_pos = strpos( $content, "\n", $php_pos );
		if ( $end_pos === false ) {
			$end_pos = strlen( $content );
		}
		
		$functions_block = "\n" . implode( "\n\n", $kept_functions ) . "\n";
		$content = substr( $content, 0, $end_pos + 1 ) . $functions_block . substr( $content, $end_pos + 1 );
	}
}

if ( $removed_count > 0 || ! empty( $kept_functions ) ) {
	if ( file_put_contents( $theme_file, $content ) === false ) {
		echo "Error: Failed to write functions.php\n";
		exit( 1 );
	}
	echo "✅ Fixed duplicate functions\n";
} else {
	echo "No duplicates found\n";
}
