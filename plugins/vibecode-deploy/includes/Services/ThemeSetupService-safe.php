<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

final class ThemeSetupService {
    
    public static function ensure_theme_files( string $theme_slug ): array {
        $results = array(
            'created' => array(),
            'updated' => array(),
            'errors' => array(),
        );
        
        // Check if theme directory exists
        $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
        if ( ! is_dir( $theme_dir ) ) {
            $results['errors'][] = "Theme directory not found: {$theme_dir}";
            return $results;
        }
        
        // Only create files if they don't exist (safer approach)
        $files = array(
            'index.php' => self::get_index_php_content(),
            'page.php' => self::get_page_php_content(),
        );
        
        foreach ( $files as $file_path => $content ) {
            $full_path = $theme_dir . '/' . $file_path;
            
            // Only create if file doesn't exist
            if ( ! file_exists( $full_path ) ) {
                if ( file_put_contents( $full_path, $content ) !== false ) {
                    $results['created'][] = $file_path;
                } else {
                    $results['errors'][] = "Failed to create: {$file_path}";
                }
            }
        }
        
        // Safely update functions.php without breaking existing code
        self::safely_update_functions_php( $theme_dir, $results );
        
        return $results;
    }
    
    private static function get_index_php_content(): string {
        return '<?php
// This file is required for WordPress themes
get_header();
if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
}
get_footer();';
    }
    
    private static function get_page_php_content(): string {
        return '<?php
// Template for individual pages
get_header();
while (have_posts()) {
    the_post();
    the_content();
}
get_footer();';
    }
    
    private static function safely_update_functions_php( string $theme_dir, array &$results ): void {
        $functions_file = $theme_dir . '/functions.php';
        
        // Only update if file exists and is writable
        if ( ! file_exists( $functions_file ) || ! is_writable( $functions_file ) ) {
            return;
        }
        
        // Read existing content
        $content = file_get_contents( $functions_file );
        if ( $content === false ) {
            return;
        }
        
        // Check if asset enqueueing already exists
        if ( strpos( $content, 'vibecode-deploy-styles' ) === false ) {
            // Add at the end of file (safer)
            $new_code = "\n// Vibe Code Deploy Asset Enqueueing\nadd_action('wp_enqueue_scripts', function() {\n    \$version = defined('VIBECODE_DEPLOY_PLUGIN_VERSION') ? VIBECODE_DEPLOY_PLUGIN_VERSION : '0.1.1';\n    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/styles.css')) {\n        wp_enqueue_style('vibecode-deploy-styles', plugins_url('assets/css/styles.css', 'vibecode-deploy'), array(), \$version);\n    }\n    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/icons.css')) {\n        wp_enqueue_style('vibecode-deploy-icons', plugins_url('assets/css/icons.css', 'vibecode-deploy'), array(), \$version);\n    }\n}, 20);\n";
            
            // Append to file
            if ( file_put_contents( $functions_file, $content . $new_code ) !== false ) {
                $results['updated'][] = 'functions.php (asset enqueueing added)';
            }
        }
    }
}
