<?php
/**
 * Add missing helper functions to theme's functions.php
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

use VibeCode\Deploy\Settings;

$project_slug = 'cfa';
$settings = Settings::get_all();
if ( ! empty( $settings['project_slug'] ) ) {
	$project_slug = $settings['project_slug'];
}

$theme_slug = get_stylesheet(); // Use active theme (child theme if exists)
$theme_file = WP_CONTENT_DIR . '/themes/' . $theme_slug . '/functions.php';

if ( ! file_exists( $theme_file ) ) {
	echo "Error: Theme functions.php not found: {$theme_file}\n";
	exit( 1 );
}

$helper_functions = <<<'PHP'

function cfa_shortcode_get_paged($param) {
    if (!is_string($param) || $param === '') {
        return 1;
    }

    $raw = null;
    if (isset($_GET[$param])) {
        $raw = $_GET[$param];
    }

    $paged = (int) $raw;
    if ($paged < 1) {
        $paged = 1;
    }

    return $paged;
}

function cfa_shortcode_render_pagination($query, $param) {
    if (!($query instanceof WP_Query)) {
        return;
    }

    $total_pages = (int) $query->max_num_pages;
    if ($total_pages <= 1) {
        return;
    }

    $current = (int) max(1, (int) $query->get('paged'));

    $base = remove_query_arg($param);
    $base = add_query_arg($param, '%#%', $base);

    $links = paginate_links(array(
        'base' => $base,
        'format' => '',
        'current' => $current,
        'total' => $total_pages,
        'type' => 'array',
        'prev_text' => 'Previous',
        'next_text' => 'Next',
    ));

    if (!is_array($links) || empty($links)) {
        return;
    }

    echo '<nav class="cfa-pagination" aria-label="Pagination">';
    echo '<div class="cfa-pagination__links">' . implode(' ', $links) . '</div>';
    echo '</nav>';
}

function cfa_foia_normalize_meta_ids($value) {
    if (is_array($value)) {
        return array_values(array_filter(array_map('intval', $value)));
    }

    if (is_numeric($value)) {
        $int = (int) $value;
        return $int > 0 ? array($int) : array();
    }

    if (!is_string($value) || $value === '') {
        return array();
    }

    $maybe = @unserialize($value);
    if (is_array($maybe)) {
        return array_values(array_filter(array_map('intval', $maybe)));
    }

    return array();
}

function cfa_foia_is_public_request($post_id) {
    $visibility = (string) get_post_meta((int) $post_id, 'cfa_foia_visibility', true);
    $visibility = strtolower(trim($visibility));
    return $visibility === 'public';
}

function cfa_investigation_is_public($post_id) {
    $visibility = (string) get_post_meta((int) $post_id, 'cfa_investigation_visibility', true);
    $visibility = strtolower(trim($visibility));
    return $visibility === 'public';
}

PHP;

$content = file_get_contents( $theme_file );

// Check if ALL required functions exist
$required_functions = array(
	'cfa_shortcode_get_paged',
	'cfa_shortcode_render_pagination',
	'cfa_foia_normalize_meta_ids',
	'cfa_foia_is_public_request',
	'cfa_investigation_is_public',
);

$missing_functions = array();
foreach ( $required_functions as $func_name ) {
	if ( strpos( $content, 'function ' . $func_name ) === false ) {
		$missing_functions[] = $func_name;
	}
}

if ( empty( $missing_functions ) ) {
	echo "All helper functions already exist in functions.php\n";
	exit( 0 );
}

echo "Missing functions: " . implode( ', ', $missing_functions ) . "\n";

// Add helper functions before the first CPT registration or shortcode
$insert_pos = strpos( $content, 'register_post_type' );
if ( $insert_pos === false ) {
	$insert_pos = strpos( $content, 'add_shortcode' );
}
if ( $insert_pos === false ) {
	// Add at the end
	$content = rtrim( $content ) . "\n\n" . $helper_functions . "\n";
} else {
	// Add before first CPT/shortcode
	$content = substr( $content, 0, $insert_pos ) . $helper_functions . "\n\n" . substr( $content, $insert_pos );
}

if ( file_put_contents( $theme_file, $content ) === false ) {
	echo "Error: Failed to write functions.php\n";
	exit( 1 );
}

echo "✅ Helper functions added to functions.php\n";
