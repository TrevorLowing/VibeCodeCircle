<?php
/**
 * Generate functions.php for deployment package
 *
 * Extracts CPT registrations, shortcodes, and asset enqueueing from existing functions.php
 *
 * Usage: php generate-functions-php.php <source-functions.php>
 */

if ( $argc < 2 ) {
    fwrite( STDERR, "Usage: php generate-functions-php.php <source-functions.php>\n" );
    exit( 1 );
}

$source_file = $argv[1];
if ( ! is_file( $source_file ) || ! is_readable( $source_file ) ) {
    fwrite( STDERR, "Error: Source functions.php not found or not readable: {$source_file}\n" );
    exit( 1 );
}

$content = file_get_contents( $source_file );
if ( $content === false ) {
    fwrite( STDERR, "Error: Unable to read source file\n" );
    exit( 1 );
}

// Extract CPT registrations (between 'add_action('init', function() {' and closing brace)
$cpt_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*function\s*\([^)]*\)\s*\{[^}]*register_post_type[^}]*\}[^}]*\}\s*,\s*\d+\s*\);/s';
preg_match_all( $cpt_pattern, $content, $cpt_matches );

// Extract shortcode registrations
$shortcode_pattern = '/add_shortcode\s*\([^)]+\)\s*\{[^}]*\}/s';
preg_match_all( $shortcode_pattern, $content, $shortcode_matches );

// Extract taxonomy registrations
$taxonomy_pattern = '/register_taxonomy\s*\([^)]+\)\s*\{[^}]*\}/s';
preg_match_all( $taxonomy_pattern, $content, $taxonomy_matches );

// Build output
$output = "<?php\n";
$output .= "/**\n";
$output .= " * Theme Functions\n";
$output .= " * Generated for Vibe Code Deploy\n";
$output .= " * Build Date: " . gmdate( 'Y-m-d H:i:s' ) . "\n";
$output .= " */\n\n";

// Add CPT registrations
if ( ! empty( $cpt_matches[0] ) ) {
    $output .= "// Custom Post Types\n";
    $output .= "add_action('init', function() {\n";
    foreach ( $cpt_matches[0] as $match ) {
        // Extract just the register_post_type and register_taxonomy calls
        if ( preg_match( '/register_(post_type|taxonomy)\s*\([^)]+\)/s', $match, $reg_match ) ) {
            $output .= "    " . trim( $reg_match[0] ) . ";\n";
        }
    }
    $output .= "}, 5);\n\n";
}

// Add shortcodes
if ( ! empty( $shortcode_matches[0] ) ) {
    $output .= "// Shortcodes\n";
    foreach ( $shortcode_matches[0] as $match ) {
        $output .= trim( $match ) . "\n\n";
    }
}

// Add asset enqueueing placeholder
$output .= "// Asset Enqueueing\n";
$output .= "// Vibe Code Deploy will automatically enqueue assets from the deployment package\n";
$output .= "// Add custom asset enqueueing below if needed\n";

echo $output;
