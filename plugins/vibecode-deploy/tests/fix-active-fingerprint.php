<?php
/**
 * Fix active fingerprint to match latest deployment
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Importer;

echo "=== Fixing Active Fingerprint ===" . PHP_EOL;
echo PHP_EOL;

// Get project settings
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
echo "Project slug: {$project_slug}" . PHP_EOL;

// Get current active fingerprint
$current_active = BuildService::get_active_fingerprint( $project_slug );
echo "Current active fingerprint: " . ( $current_active !== '' ? $current_active : '(none)' ) . PHP_EOL;

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

$latest_fingerprint = basename( $staging_dirs[0] );
echo "Latest fingerprint: {$latest_fingerprint}" . PHP_EOL;
echo PHP_EOL;

// Check if pages exist with latest fingerprint
$pages_with_latest = get_posts( array(
	'post_type' => 'page',
	'posts_per_page' => 1,
	'post_status' => 'any',
	'meta_query' => array(
		array(
			'key' => Importer::META_PROJECT_SLUG,
			'value' => $project_slug,
			'compare' => '=',
		),
		array(
			'key' => Importer::META_FINGERPRINT,
			'value' => $latest_fingerprint,
			'compare' => '=',
		),
	),
) );

if ( empty( $pages_with_latest ) ) {
	echo "⚠️  No pages found with latest fingerprint" . PHP_EOL;
	echo "   This might mean the latest deployment didn't create pages" . PHP_EOL;
} else {
	echo "✅ Found pages with latest fingerprint" . PHP_EOL;
}

// Set active fingerprint
echo PHP_EOL . "Setting active fingerprint to: {$latest_fingerprint}" . PHP_EOL;
BuildService::set_active_fingerprint( $project_slug, $latest_fingerprint );

// Verify it was set
$new_active = BuildService::get_active_fingerprint( $project_slug );
if ( $new_active === $latest_fingerprint ) {
	echo "✅ Active fingerprint updated successfully" . PHP_EOL;
} else {
	echo "❌ Failed to update active fingerprint" . PHP_EOL;
	echo "   Expected: {$latest_fingerprint}" . PHP_EOL;
	echo "   Got: {$new_active}" . PHP_EOL;
	exit( 1 );
}

echo PHP_EOL . "=== Verification ===" . PHP_EOL;
$css_files = array( 'css/icons.css', 'css/styles.css' );
$build_root = BuildService::build_root_path( $project_slug, $latest_fingerprint );
$uploads = wp_upload_dir();
$base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $project_slug ) . '/' . rawurlencode( $latest_fingerprint ) . '/';

foreach ( $css_files as $css_path ) {
	$css_file = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $css_path );
	$css_url = $base_url . ltrim( $css_path, '/' );
	$exists = file_exists( $css_file );
	$status = $exists ? '✅' : '❌';
	echo "{$status} {$css_path}" . PHP_EOL;
	if ( $exists ) {
		echo "   URL: {$css_url}" . PHP_EOL;
		$mtime = filemtime( $css_file );
		$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $latest_fingerprint . '-' . $mtime : $latest_fingerprint . '-' . $mtime;
		echo "   Version: {$version}" . PHP_EOL;
	}
}

echo PHP_EOL . "✅ Active fingerprint fixed!" . PHP_EOL;
echo "   CSS files should now load correctly." . PHP_EOL;
