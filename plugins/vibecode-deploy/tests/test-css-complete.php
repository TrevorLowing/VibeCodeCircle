<?php
/**
 * Complete CSS loading test - simulates full page load
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

echo "=== Complete CSS Loading Test ===" . PHP_EOL;
echo PHP_EOL;

$tests_passed = 0;
$tests_failed = 0;

function test( $name, $condition, $message = '' ) {
	global $tests_passed, $tests_failed;
	if ( $condition ) {
		echo "✅ PASS: {$name}" . PHP_EOL;
		if ( $message ) {
			echo "   {$message}" . PHP_EOL;
		}
		$tests_passed++;
	} else {
		echo "❌ FAIL: {$name}" . PHP_EOL;
		if ( $message ) {
			echo "   {$message}" . PHP_EOL;
		}
		$tests_failed++;
	}
}

// 1. Check active fingerprint
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );
test( 'Active fingerprint is set', $active_fingerprint !== '', "Fingerprint: {$active_fingerprint}" );

// 2. Check CSS files exist
if ( $active_fingerprint !== '' ) {
	$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
	$css_files = array( 'css/icons.css', 'css/styles.css' );
	foreach ( $css_files as $css_path ) {
		$css_file = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
		$exists = file_exists( $css_file );
		test( "CSS file exists: {$css_path}", $exists, $exists ? "Size: " . number_format( filesize( $css_file ) / 1024, 2 ) . " KB" : "File not found: {$css_file}" );
	}
}

// 3. Check pages have correct fingerprint
$pages = get_posts( array(
	'post_type' => 'page',
	'posts_per_page' => 5,
	'post_status' => 'publish',
	'meta_query' => array(
		array(
			'key' => Importer::META_PROJECT_SLUG,
			'value' => $project_slug,
			'compare' => '=',
		),
	),
) );

test( 'Pages exist with project slug', ! empty( $pages ), "Found " . count( $pages ) . " pages" );

if ( ! empty( $pages ) ) {
	$matching_fingerprint = 0;
	foreach ( $pages as $page ) {
		$page_fingerprint = get_post_meta( $page->ID, Importer::META_FINGERPRINT, true );
		if ( $page_fingerprint === $active_fingerprint ) {
			$matching_fingerprint++;
		}
	}
	test( 'Pages have matching fingerprint', $matching_fingerprint > 0, "{$matching_fingerprint} pages match active fingerprint" );
}

// 4. Test CSS enqueueing
if ( ! empty( $pages ) ) {
	global $wp_query, $post, $wp_styles;
	$test_page = $pages[0];
	
	// Simulate page view
	$wp_query->is_singular = true;
	$wp_query->is_page = true;
	$wp_query->queried_object = $test_page;
	$wp_query->queried_object_id = $test_page->ID;
	$post = $test_page;
	
	if ( ! isset( $wp_styles ) ) {
		$wp_styles = new WP_Styles();
	}
	
	// Clear and enqueue
	Importer::enqueue_assets_for_current_page();
	
	// Check enqueued styles
	$enqueued = array();
	if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
		foreach ( $wp_styles->queue as $handle ) {
			if ( strpos( $handle, 'vibecode-deploy' ) !== false ) {
				$enqueued[] = $handle;
			}
		}
	}
	
	test( 'CSS files are enqueued', count( $enqueued ) >= 2, "Found " . count( $enqueued ) . " enqueued styles" );
	
	// Verify URLs are correct
	if ( ! empty( $enqueued ) ) {
		$correct_urls = 0;
		foreach ( $enqueued as $handle ) {
			$style = $wp_styles->registered[ $handle ] ?? null;
			if ( $style && isset( $style->src ) ) {
				$src = $style->src;
				if ( strpos( $src, $active_fingerprint ) !== false ) {
					$correct_urls++;
				}
			}
		}
		test( 'CSS URLs use correct fingerprint', $correct_urls === count( $enqueued ), "{$correct_urls}/" . count( $enqueued ) . " URLs correct" );
	}
}

// 5. Check hook registration
global $wp_filter;
$hook_registered = false;
if ( isset( $wp_filter['wp_enqueue_scripts'] ) ) {
	$callbacks = $wp_filter['wp_enqueue_scripts']->callbacks;
	foreach ( $callbacks as $priority => $hooks ) {
		foreach ( $hooks as $hook ) {
			if ( is_array( $hook['function'] ) && is_string( $hook['function'][0] ) && $hook['function'][0] === 'VibeCode\\Deploy\\Importer' ) {
				$hook_registered = true;
				break 2;
			}
		}
	}
}
test( 'wp_enqueue_scripts hook is registered', $hook_registered, $hook_registered ? 'Hook found' : 'Hook not found' );

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$tests_passed}" . PHP_EOL;
echo "Failed: {$tests_failed}" . PHP_EOL;
echo "Total: " . ( $tests_passed + $tests_failed ) . PHP_EOL;
echo PHP_EOL;

if ( $tests_failed === 0 ) {
	echo "✅ All tests passed! CSS loading is working correctly." . PHP_EOL;
	exit( 0 );
} else {
	echo "❌ Some tests failed. CSS loading may have issues." . PHP_EOL;
	exit( 1 );
}
