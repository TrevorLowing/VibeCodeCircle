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
        
        $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
        
        // Ensure theme directory exists
        if ( ! is_dir( $theme_dir ) ) {
            $results['errors'][] = "Theme directory not found: {$theme_dir}";
            return $results;
        }
        
        // Create necessary files
        $files = array(
            'index.php' => self::get_index_php_content(),
            'page.php' => self::get_page_php_content(),
            'templates/index.html' => self::get_index_template_content(),
            'templates/page.html' => self::get_page_template_content(),
        );
        
        foreach ( $files as $file_path => $content ) {
            $full_path = $theme_dir . '/' . $file_path;
            $dir = dirname( $full_path );
            
            // Create directory if needed
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            
            // Write file
            if ( file_put_contents( $full_path, $content ) !== false ) {
                $results['created'][] = $file_path;
            } else {
                $results['errors'][] = "Failed to create: {$file_path}";
            }
        }
        
        // Update functions.php to enqueue assets
        self::update_functions_php( $theme_dir, $results );
        
        // Enable Etch mode if theme mod exists
        self::enable_etch_mode( $theme_slug );
        
        return $results;
    }
    
    private static function get_index_php_content(): string {
        return '<?php
// This file is required for WordPress themes
// It will use the block template system if available

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
    
    private static function get_index_template_content(): string {
        $settings = \VibeCode\Deploy\Settings::get_all();
        $class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
        $main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';
        
        return '<!-- wp:template-part {"slug":"header","theme":"' . get_option('stylesheet') . '","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->
<main class="wp-block-group ' . esc_attr( $main_class ) . '">
    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","theme":"' . get_option('stylesheet') . '","tagName":"footer"} /-->';
    }
    
    private static function get_page_template_content(): string {
        $settings = \VibeCode\Deploy\Settings::get_all();
        $class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
        $main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';
        
        return '<!-- wp:template-part {"slug":"header","theme":"' . get_option('stylesheet') . '","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->
<main class="wp-block-group ' . esc_attr( $main_class ) . '">
    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","theme":"' . get_option('stylesheet') . '","tagName":"footer"} /-->';
    }
    
    private static function update_functions_php( string $theme_dir, array &$results ): void {
        $functions_file = $theme_dir . '/functions.php';
        
        // Read existing functions.php
        $content = '';
        if ( file_exists( $functions_file ) ) {
            $content = file_get_contents( $functions_file );
        }
        
        // Check if asset enqueueing already exists
        if ( strpos( $content, 'vibecode-deploy-styles' ) === false ) {
            // Find the wp_enqueue_scripts hook or add it
            $pattern = '/add_action\(\'wp_enqueue_scripts\',\s*function\(\)/';
            
            if ( preg_match( $pattern, $content ) ) {
                // Update existing hook
                $content = preg_replace(
                    '/(add_action\(\'wp_enqueue_scripts\',\s*function\(\)\s*\{[^}]*)(\},\s*\d+\);)/s',
                    '$1' . "\n    \n    // Enqueue Vibe Code Deploy assets\n    if (file_exists(plugin_dir_path(\'vibecode-deploy\') . \'assets/css/styles.css\')) {\n        wp_enqueue_style(\'vibecode-deploy-styles\', plugins_url(\'assets/css/styles.css\', \'vibecode-deploy\'));\n    }\n    if (file_exists(plugin_dir_path(\'vibecode-deploy\') . \'assets/css/icons.css\')) {\n        wp_enqueue_style(\'vibecode-deploy-icons\', plugins_url(\'assets/css/icons.css\', \'vibecode-deploy\'));\n    }\n$2",
                    $content
                );
                $results['updated'][] = 'functions.php (asset enqueueing added)';
            } else {
                // Add new hook at the end
                $content .= "\n\nadd_action('wp_enqueue_scripts', function() {\n    // Enqueue Vibe Code Deploy assets\n    if (file_exists(plugin_dir_path('vibecode-deploy') . 'assets/css/styles.css')) {\n        wp_enqueue_style('vibecode-deploy-styles', plugins_url('assets/css/styles.css', 'vibecode-deploy'));\n    }\n    if (file_exists(plugin_dir_path('vibecode-deploy') . 'assets/css/icons.css')) {\n        wp_enqueue_style('vibecode-deploy-icons', plugins_url('assets/css/icons.css', 'vibecode-deploy'));\n    }\n}, 20);\n";
                $results['created'][] = 'functions.php (asset enqueueing added)';
            }
            
            file_put_contents( $functions_file, $content );
        }
    }
    
    private static function enable_etch_mode( string $theme_slug ): void {
        // Enable Etch mode for any child theme of etch-theme
        $theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( $theme_slug ) : null;
        $template = $theme && method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';
        
        if ( $template === 'etch-theme' ) {
            // Enable Etch mode via theme mod (using theme slug as identifier)
            $mod_key = sanitize_key( $theme_slug ) . '_etch_mode_enabled';
            set_theme_mod( $mod_key, true );
            
            // Also ensure Etch settings allow block migration
            $etch_settings = get_option( 'etch_settings', array() );
            if ( ! is_array( $etch_settings ) ) {
                $etch_settings = array();
            }
            $etch_settings['custom_block_migration_completed'] = true;
            update_option( 'etch_settings', $etch_settings );
        }
    }
}
