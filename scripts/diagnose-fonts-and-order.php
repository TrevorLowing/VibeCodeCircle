<?php
/**
 * Diagnostic script to check font loading and section order issues
 */

require_once __DIR__ . '/../plugins/vibecode-deploy/includes/Importer.php';

// Check home page
$home_page = get_page_by_path( 'home' );
if ( $home_page ) {
    echo "=== HOME PAGE ===\n";
    echo "Post ID: " . $home_page->ID . "\n";
    
    $project_slug = get_post_meta( $home_page->ID, \VibeCode\Deploy\Importer::META_PROJECT_SLUG, true );
    echo "Project Slug: " . ( $project_slug ?: 'NOT SET' ) . "\n";
    
    $fonts = get_post_meta( $home_page->ID, \VibeCode\Deploy\Importer::META_ASSET_FONTS, true );
    echo "Font URLs in post meta: " . ( is_array( $fonts ) && ! empty( $fonts ) ? print_r( $fonts, true ) : 'NONE' ) . "\n";
    
    // Check if fonts are being enqueued
    global $wp_styles;
    if ( isset( $wp_styles ) ) {
        echo "\nEnqueued styles:\n";
        foreach ( $wp_styles->queue as $handle ) {
            if ( strpos( $handle, 'font' ) !== false || strpos( $handle, 'vibecode-deploy-fonts' ) !== false ) {
                echo "  - $handle\n";
            }
        }
    }
}

// Check investigations page
$investigations_page = get_page_by_path( 'investigations' );
if ( $investigations_page ) {
    echo "\n=== INVESTIGATIONS PAGE ===\n";
    echo "Post ID: " . $investigations_page->ID . "\n";
    
    $project_slug = get_post_meta( $investigations_page->ID, \VibeCode\Deploy\Importer::META_PROJECT_SLUG, true );
    echo "Project Slug: " . ( $project_slug ?: 'NOT SET' ) . "\n";
    
    $fonts = get_post_meta( $investigations_page->ID, \VibeCode\Deploy\Importer::META_ASSET_FONTS, true );
    echo "Font URLs in post meta: " . ( is_array( $fonts ) && ! empty( $fonts ) ? print_r( $fonts, true ) : 'NONE' ) . "\n";
    
    // Check template file
    $template_file = get_page_template_slug( $investigations_page->ID );
    echo "Template file: " . ( $template_file ?: 'default' ) . "\n";
    
    // Get template content
    $template_post = get_block_template( get_stylesheet() . '//page-investigations', 'wp_template' );
    if ( $template_post && isset( $template_post->content ) ) {
        // Count occurrences of "Have Evidence" and "Active Investigations"
        $have_evidence_pos = strpos( $template_post->content, 'Have Evidence' );
        $active_investigations_pos = strpos( $template_post->content, 'Active Investigations' );
        
        echo "\nSection order in template:\n";
        if ( $have_evidence_pos !== false && $active_investigations_pos !== false ) {
            if ( $have_evidence_pos < $active_investigations_pos ) {
                echo "  ❌ WRONG: 'Have Evidence?' appears BEFORE 'Active Investigations'\n";
                echo "  Have Evidence position: $have_evidence_pos\n";
                echo "  Active Investigations position: $active_investigations_pos\n";
            } else {
                echo "  ✅ CORRECT: 'Active Investigations' appears BEFORE 'Have Evidence?'\n";
                echo "  Active Investigations position: $active_investigations_pos\n";
                echo "  Have Evidence position: $have_evidence_pos\n";
            }
        } else {
            echo "  ⚠️  Could not find both sections in template\n";
            if ( $have_evidence_pos === false ) {
                echo "    - 'Have Evidence?' not found\n";
            }
            if ( $active_investigations_pos === false ) {
                echo "    - 'Active Investigations' not found\n";
            }
        }
    }
}

echo "\n=== FONT ENQUEUE CHECK ===\n";
// Simulate the enqueue_fonts() logic
if ( $home_page ) {
    $post_id = $home_page->ID;
    $project_slug = get_post_meta( $post_id, \VibeCode\Deploy\Importer::META_PROJECT_SLUG, true );
    if ( $project_slug !== '' ) {
        $fonts = get_post_meta( $post_id, \VibeCode\Deploy\Importer::META_ASSET_FONTS, true );
        if ( is_array( $fonts ) && ! empty( $fonts ) ) {
            echo "Fonts would be enqueued:\n";
            foreach ( $fonts as $font_url ) {
                $handle = 'vibecode-deploy-fonts-' . md5( $font_url );
                echo "  - Handle: $handle\n";
                echo "    URL: $font_url\n";
            }
        } else {
            echo "❌ No fonts in post meta - pages need to be re-imported\n";
        }
    } else {
        echo "❌ No project slug - page not managed by plugin\n";
    }
}
