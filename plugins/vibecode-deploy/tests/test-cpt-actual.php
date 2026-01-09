<?php
/**
 * Test actual CPT registration and shortcode functionality
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Importer;

echo "=== CPT and Shortcode Diagnostic ===" . PHP_EOL;
echo PHP_EOL;

// Check all registered CPTs
echo "=== Registered Custom Post Types ===" . PHP_EOL;
$all_cpts = get_post_types( array( 'public' => true ), 'names' );
$custom_cpts = array_filter( $all_cpts, function( $cpt ) {
	return ! in_array( $cpt, array( 'post', 'page', 'attachment' ), true );
} );

if ( empty( $custom_cpts ) ) {
	echo "❌ No custom post types registered" . PHP_EOL;
} else {
	echo "Found " . count( $custom_cpts ) . " custom post types:" . PHP_EOL;
	foreach ( $custom_cpts as $cpt ) {
		$obj = get_post_type_object( $cpt );
		$labels = $obj ? $obj->labels->name : 'N/A';
		echo "  ✅ {$cpt} ({$labels})" . PHP_EOL;
	}
}
echo PHP_EOL;

// Check all registered shortcodes
echo "=== Registered Shortcodes ===" . PHP_EOL;
global $shortcode_tags;
$cfa_shortcodes = array_filter( array_keys( $shortcode_tags ), function( $tag ) {
	return strpos( $tag, 'cfa_' ) === 0;
} );

if ( empty( $cfa_shortcodes ) ) {
	echo "❌ No CFA shortcodes registered" . PHP_EOL;
} else {
	echo "Found " . count( $cfa_shortcodes ) . " CFA shortcodes:" . PHP_EOL;
	foreach ( $cfa_shortcodes as $tag ) {
		echo "  ✅ {$tag}" . PHP_EOL;
	}
}
echo PHP_EOL;

// Check functions.php execution
echo "=== Functions.php Execution ===" . PHP_EOL;
$theme = wp_get_theme();
$theme_slug = $theme->get_stylesheet();
$theme_dir = get_theme_root() . '/' . $theme_slug;
$functions_file = $theme_dir . '/functions.php';

if ( file_exists( $functions_file ) ) {
	$content = file_get_contents( $functions_file );
	$has_cpt = strpos( $content, 'register_post_type' ) !== false;
	$has_shortcode = strpos( $content, 'add_shortcode' ) !== false;
	
	echo "functions.php exists: ✅" . PHP_EOL;
	echo "Contains register_post_type: " . ( $has_cpt ? '✅' : '❌' ) . PHP_EOL;
	echo "Contains add_shortcode: " . ( $has_shortcode ? '✅' : '❌' ) . PHP_EOL;
	
	// Count CPT registrations
	preg_match_all( "/register_post_type\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $cpt_matches );
	if ( ! empty( $cpt_matches[1] ) ) {
		echo "CPT slugs found in functions.php:" . PHP_EOL;
		foreach ( array_unique( $cpt_matches[1] ) as $slug ) {
			$registered = post_type_exists( $slug );
			echo "  " . ( $registered ? '✅' : '❌' ) . " {$slug}" . PHP_EOL;
		}
	}
	
	// Count shortcode registrations
	preg_match_all( "/add_shortcode\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $shortcode_matches );
	if ( ! empty( $shortcode_matches[1] ) ) {
		echo "Shortcode tags found in functions.php:" . PHP_EOL;
		foreach ( array_unique( $shortcode_matches[1] ) as $tag ) {
			$registered = shortcode_exists( $tag );
			echo "  " . ( $registered ? '✅' : '❌' ) . " {$tag}" . PHP_EOL;
		}
	}
} else {
	echo "❌ functions.php not found" . PHP_EOL;
}
echo PHP_EOL;

// Check page content for placeholders vs shortcodes
echo "=== Page Content Analysis ===" . PHP_EOL;
$pages_to_check = array(
	'advisories' => 'cfa_advisories',
	'investigations' => 'cfa_investigations',
	'foia-reading-room' => 'cfa_foia_index',
	'home' => 'cfa_surveys',
);

foreach ( $pages_to_check as $page_slug => $expected_shortcode ) {
	$page = get_page_by_path( $page_slug );
	if ( $page ) {
		$content = $page->post_content;
		$has_shortcode = strpos( $content, '[' . $expected_shortcode ) !== false;
		$has_placeholder = strpos( $content, 'VIBECODE_SHORTCODE' ) !== false || strpos( $content, 'CFA_SHORTCODE' ) !== false;
		$has_block = strpos( $content, '<!-- wp:shortcode -->' ) !== false;
		
		echo "Page: {$page_slug}" . PHP_EOL;
		echo "  Has shortcode [{$expected_shortcode}]: " . ( $has_shortcode ? '✅' : '❌' ) . PHP_EOL;
		echo "  Has placeholder comment: " . ( $has_placeholder ? '⚠️  YES (not converted)' : '✅' ) . PHP_EOL;
		echo "  Has shortcode block: " . ( $has_block ? '✅' : '❌' ) . PHP_EOL;
		
		if ( $has_placeholder ) {
			// Extract placeholder
			if ( preg_match( '/<!--\s*(?:VIBECODE_SHORTCODE|CFA_SHORTCODE)\s+([^\s]+)/', $content, $matches ) ) {
				echo "  Found placeholder: " . $matches[1] . PHP_EOL;
			}
		}
	} else {
		echo "Page: {$page_slug} ❌ Not found" . PHP_EOL;
	}
}
echo PHP_EOL;

// Check staging vs deployed content
echo "=== Staging vs Deployed Content ===" . PHP_EOL;
$settings = \VibeCode\Deploy\Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
$active_fingerprint = \VibeCode\Deploy\Services\BuildService::get_active_fingerprint( $project_slug );

if ( $active_fingerprint !== '' ) {
	$build_root = \VibeCode\Deploy\Services\BuildService::build_root_path( $project_slug, $active_fingerprint );
	$staging_investigations = $build_root . '/pages/investigations.html';
	$staging_foia = $build_root . '/pages/foia-reading-room.html';
	
	if ( file_exists( $staging_investigations ) ) {
		$staging_content = file_get_contents( $staging_investigations );
		$has_placeholder = strpos( $staging_content, 'CFA_SHORTCODE' ) !== false || strpos( $staging_content, 'VIBECODE_SHORTCODE' ) !== false;
		echo "Staging investigations.html has placeholder: " . ( $has_placeholder ? '✅' : '❌' ) . PHP_EOL;
	}
	
	if ( file_exists( $staging_foia ) ) {
		$staging_content = file_get_contents( $staging_foia );
		$has_placeholder = strpos( $staging_content, 'CFA_SHORTCODE' ) !== false || strpos( $staging_content, 'VIBECODE_SHORTCODE' ) !== false;
		echo "Staging foia-reading-room.html has placeholder: " . ( $has_placeholder ? '✅' : '❌' ) . PHP_EOL;
	}
}

echo PHP_EOL . "=== Summary ===" . PHP_EOL;
$cpt_count = count( $custom_cpts );
$shortcode_count = count( $cfa_shortcodes );

if ( $cpt_count > 0 && $shortcode_count > 0 ) {
	echo "✅ CPTs and shortcodes are registered" . PHP_EOL;
} else {
	echo "❌ Issues found:" . PHP_EOL;
	if ( $cpt_count === 0 ) {
		echo "   - No CPTs registered" . PHP_EOL;
	}
	if ( $shortcode_count === 0 ) {
		echo "   - No shortcodes registered" . PHP_EOL;
	}
}
