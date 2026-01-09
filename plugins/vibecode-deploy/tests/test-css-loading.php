<?php
/**
 * Test CSS loading and enqueueing
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

echo "=== CSS Loading Diagnostic ===" . PHP_EOL;
echo PHP_EOL;

// Get project settings
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
echo "Project slug: {$project_slug}" . PHP_EOL;

// Find latest staging directory
$upload_dir = wp_upload_dir();
$uploads_base = $upload_dir['basedir'];
$staging_dirs = glob( $uploads_base . '/vibecode-deploy/staging/' . $project_slug . '/*' );
if ( empty( $staging_dirs ) ) {
	echo "❌ No staging directories found" . PHP_EOL;
	exit( 1 );
}

usort( $staging_dirs, function( $a, $b ) {
	return filemtime( $b ) - filemtime( $a );
});
$build_root = $staging_dirs[0];
$fingerprint = basename( $build_root );

echo "Latest fingerprint: {$fingerprint}" . PHP_EOL;
echo "Build root: {$build_root}" . PHP_EOL;
echo PHP_EOL;

// Check CSS files in staging
echo "=== CSS Files in Staging ===" . PHP_EOL;
$css_files = glob( $build_root . '/**/*.css', GLOB_BRACE );
if ( empty( $css_files ) ) {
	// Try alternative paths
	$css_files = array_merge(
		glob( $build_root . '/css/*.css' ),
		glob( $build_root . '/assets/css/*.css' ),
		glob( $build_root . '/*/css/*.css' )
	);
}

if ( empty( $css_files ) ) {
	echo "❌ No CSS files found in staging" . PHP_EOL;
	echo "   Searched:" . PHP_EOL;
	echo "   - {$build_root}/css/*.css" . PHP_EOL;
	echo "   - {$build_root}/assets/css/*.css" . PHP_EOL;
} else {
	echo "Found " . count( $css_files ) . " CSS files:" . PHP_EOL;
	foreach ( $css_files as $file ) {
		$relative = str_replace( $build_root . '/', '', $file );
		$size = filesize( $file );
		$exists = file_exists( $file ) ? '✅' : '❌';
		echo "  {$exists} {$relative} (" . number_format( $size / 1024, 2 ) . " KB)" . PHP_EOL;
	}
}
echo PHP_EOL;

// Check expected CSS files
echo "=== Expected CSS Files ===" . PHP_EOL;
$expected_css = array(
	'css/icons.css',
	'css/styles.css',
);

foreach ( $expected_css as $css_path ) {
	$full_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
	$exists = file_exists( $full_path );
	$status = $exists ? '✅' : '❌';
	echo "  {$status} {$css_path}" . PHP_EOL;
	if ( $exists ) {
		$mtime = filemtime( $full_path );
		$size = filesize( $full_path );
		echo "      Size: " . number_format( $size / 1024, 2 ) . " KB" . PHP_EOL;
		echo "      Modified: " . date( 'Y-m-d H:i:s', $mtime ) . PHP_EOL;
	}
}
echo PHP_EOL;

// Check a deployed page's CSS meta
echo "=== Page CSS Meta ===" . PHP_EOL;
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

if ( empty( $pages ) ) {
	echo "❌ No pages found with project slug" . PHP_EOL;
} else {
	foreach ( $pages as $page ) {
		$css_meta = get_post_meta( $page->ID, Importer::META_ASSET_CSS, true );
		$fingerprint_meta = get_post_meta( $page->ID, Importer::META_FINGERPRINT, true );
		echo "Page: {$page->post_name} (ID: {$page->ID})" . PHP_EOL;
		echo "  Fingerprint: {$fingerprint_meta}" . PHP_EOL;
		if ( is_array( $css_meta ) && ! empty( $css_meta ) ) {
			echo "  CSS files:" . PHP_EOL;
			foreach ( $css_meta as $css ) {
				echo "    - {$css}" . PHP_EOL;
			}
		} else {
			echo "  ⚠️  No CSS meta found" . PHP_EOL;
		}
	}
}
echo PHP_EOL;

// Check active fingerprint
echo "=== Active Fingerprint ===" . PHP_EOL;
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );
if ( $active_fingerprint !== '' ) {
	echo "✅ Active fingerprint: {$active_fingerprint}" . PHP_EOL;
} else {
	echo "❌ No active fingerprint found" . PHP_EOL;
}
echo PHP_EOL;

// Test CSS URL generation
echo "=== CSS URL Generation Test ===" . PHP_EOL;
if ( $active_fingerprint !== '' ) {
	$uploads = wp_upload_dir();
	$base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $project_slug ) . '/' . rawurlencode( $active_fingerprint ) . '/';
	
	foreach ( $expected_css as $css_path ) {
		$css_url = $base_url . ltrim( $css_path, '/' );
		$css_file = rtrim( BuildService::build_root_path( $project_slug, $active_fingerprint ), '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
		$exists = file_exists( $css_file );
		$status = $exists ? '✅' : '❌';
		echo "  {$status} {$css_path}" . PHP_EOL;
		echo "      URL: {$css_url}" . PHP_EOL;
		echo "      File: {$css_file}" . PHP_EOL;
		if ( $exists ) {
			$mtime = filemtime( $css_file );
			$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $active_fingerprint . '-' . $mtime : $active_fingerprint . '-' . $mtime;
			echo "      Version: {$version}" . PHP_EOL;
		}
	}
} else {
	echo "⚠️  Cannot test URL generation - no active fingerprint" . PHP_EOL;
}
echo PHP_EOL;

// Check if wp_enqueue_scripts hook is registered
echo "=== Hook Registration ===" . PHP_EOL;
global $wp_filter;
if ( isset( $wp_filter['wp_enqueue_scripts'] ) ) {
	$callbacks = $wp_filter['wp_enqueue_scripts']->callbacks;
	$found = false;
	foreach ( $callbacks as $priority => $hooks ) {
		foreach ( $hooks as $hook ) {
			if ( is_array( $hook['function'] ) && is_object( $hook['function'][0] ) ) {
				$class = get_class( $hook['function'][0] );
				if ( strpos( $class, 'VibeCode\\Deploy' ) !== false ) {
					$found = true;
					echo "✅ Found VibeCode Deploy hook at priority {$priority}" . PHP_EOL;
					echo "   Class: {$class}" . PHP_EOL;
				}
			} elseif ( is_string( $hook['function'] ) && strpos( $hook['function'], 'vibecode' ) !== false ) {
				$found = true;
				echo "✅ Found VibeCode hook: {$hook['function']}" . PHP_EOL;
			}
		}
	}
	if ( ! $found ) {
		echo "❌ No VibeCode Deploy hooks found on wp_enqueue_scripts" . PHP_EOL;
	}
} else {
	echo "❌ wp_enqueue_scripts filter not found" . PHP_EOL;
}
echo PHP_EOL;

echo "=== Summary ===" . PHP_EOL;
$css_count = count( $css_files );
$expected_count = count( $expected_css );
$all_exist = true;
foreach ( $expected_css as $css_path ) {
	$full_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
	if ( ! file_exists( $full_path ) ) {
		$all_exist = false;
		break;
	}
}

if ( $all_exist && $active_fingerprint !== '' ) {
	echo "✅ CSS files exist and active fingerprint is set" . PHP_EOL;
} else {
	echo "❌ Issues found:" . PHP_EOL;
	if ( ! $all_exist ) {
		echo "   - Some expected CSS files are missing" . PHP_EOL;
	}
	if ( $active_fingerprint === '' ) {
		echo "   - No active fingerprint set" . PHP_EOL;
	}
}
