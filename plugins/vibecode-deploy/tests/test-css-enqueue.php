<?php
/**
 * Test CSS enqueueing by simulating a page request
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

echo "=== Testing CSS Enqueueing ===" . PHP_EOL;
echo PHP_EOL;

// Get project settings
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';

// Get a deployed page
$pages = get_posts( array(
	'post_type' => 'page',
	'posts_per_page' => 1,
	'post_status' => 'publish',
	'meta_query' => array(
		array(
			'key' => Importer::META_PROJECT_SLUG,
			'value' => $project_slug,
			'compare' => '=',
		),
	),
) );

if ( empty( $pages ) ) {
	echo "❌ No deployed pages found" . PHP_EOL;
	exit( 1 );
}

$test_page = $pages[0];
echo "Testing with page: {$test_page->post_name} (ID: {$test_page->ID})" . PHP_EOL;

// Set up WordPress query to simulate viewing this page
global $wp_query, $post;
$wp_query->is_singular = true;
$wp_query->is_page = true;
$wp_query->queried_object = $test_page;
$wp_query->queried_object_id = $test_page->ID;
$post = $test_page;

// Clear any previously enqueued styles
global $wp_styles;
if ( ! isset( $wp_styles ) ) {
	$wp_styles = new WP_Styles();
}

// Call the enqueue function
echo PHP_EOL . "Calling enqueue_assets_for_current_page()..." . PHP_EOL;
Importer::enqueue_assets_for_current_page();

// Check what was enqueued
echo PHP_EOL . "=== Enqueued Styles ===" . PHP_EOL;
$enqueued = array();
if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
	foreach ( $wp_styles->queue as $handle ) {
		if ( strpos( $handle, 'vibecode-deploy' ) !== false ) {
			$enqueued[] = $handle;
			$style = $wp_styles->registered[ $handle ] ?? null;
			if ( $style ) {
				$src = $style->src ?? 'N/A';
				$ver = $style->ver ?? 'N/A';
				echo "  ✅ {$handle}" . PHP_EOL;
				echo "     Source: {$src}" . PHP_EOL;
				echo "     Version: {$ver}" . PHP_EOL;
			}
		}
	}
}

if ( empty( $enqueued ) ) {
	echo "  ❌ No VibeCode Deploy styles enqueued" . PHP_EOL;
	echo PHP_EOL . "Checking hook registration..." . PHP_EOL;
	global $wp_filter;
	if ( isset( $wp_filter['wp_enqueue_scripts'] ) ) {
		$callbacks = $wp_filter['wp_enqueue_scripts']->callbacks;
		$found = false;
		foreach ( $callbacks as $priority => $hooks ) {
			foreach ( $hooks as $hook ) {
				if ( is_array( $hook['function'] ) && is_string( $hook['function'][0] ) && $hook['function'][0] === 'VibeCode\\Deploy\\Importer' ) {
					$found = true;
					echo "  ✅ Hook found at priority {$priority}" . PHP_EOL;
				}
			}
		}
		if ( ! $found ) {
			echo "  ❌ Hook not found" . PHP_EOL;
		}
	} else {
		echo "  ❌ wp_enqueue_scripts filter not found" . PHP_EOL;
	}
} else {
	echo PHP_EOL . "✅ Found " . count( $enqueued ) . " enqueued styles" . PHP_EOL;
}

// Check active fingerprint
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );
echo PHP_EOL . "Active fingerprint: " . ( $active_fingerprint !== '' ? $active_fingerprint : '(none)' ) . PHP_EOL;

// Verify CSS files exist
if ( $active_fingerprint !== '' ) {
	$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
	$css_files = array( 'css/icons.css', 'css/styles.css' );
	echo PHP_EOL . "=== CSS File Verification ===" . PHP_EOL;
	foreach ( $css_files as $css_path ) {
		$css_file = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
		$exists = file_exists( $css_file );
		$status = $exists ? '✅' : '❌';
		echo "  {$status} {$css_path}" . PHP_EOL;
		if ( $exists ) {
			$size = filesize( $css_file );
			echo "     Size: " . number_format( $size / 1024, 2 ) . " KB" . PHP_EOL;
		}
	}
}

echo PHP_EOL . "=== Summary ===" . PHP_EOL;
if ( ! empty( $enqueued ) && $active_fingerprint !== '' ) {
	echo "✅ CSS enqueueing is working correctly" . PHP_EOL;
} else {
	echo "❌ Issues found:" . PHP_EOL;
	if ( empty( $enqueued ) ) {
		echo "   - No styles were enqueued" . PHP_EOL;
	}
	if ( $active_fingerprint === '' ) {
		echo "   - No active fingerprint set" . PHP_EOL;
	}
}
