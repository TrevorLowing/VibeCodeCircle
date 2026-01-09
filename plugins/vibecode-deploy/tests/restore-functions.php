<?php
/**
 * Restore functions.php from staging
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\BuildService;

$settings = Settings::get_all();
$project_slug = $settings['project_slug'] ?? '';
if ( $project_slug === '' ) {
	echo "Error: No project_slug configured in settings. Please configure it in Vibe Code Deploy → Settings.\n";
	exit( 1 );
}
$fingerprint = BuildService::get_active_fingerprint( $project_slug );

if ( ! $fingerprint ) {
	echo "Error: No active fingerprint found\n";
	exit( 1 );
}

$build_root = WP_CONTENT_DIR . '/uploads/vibecode-deploy/staging/' . $project_slug . '/' . $fingerprint;
$staging_file = $build_root . '/theme/functions.php';
$theme_slug = get_stylesheet();
$theme_file = WP_CONTENT_DIR . '/themes/' . $theme_slug . '/functions.php';

if ( ! file_exists( $staging_file ) ) {
	echo "Error: Staging functions.php not found: {$staging_file}\n";
	exit( 1 );
}

// Backup current
if ( file_exists( $theme_file ) ) {
	copy( $theme_file, $theme_file . '.backup.' . time() );
}

// Copy staging as base
copy( $staging_file, $theme_file );

echo "✅ Restored functions.php from staging\n";
echo "File: {$theme_file}\n";

// Verify syntax
$output = array();
$return_var = 0;
exec( "php -l " . escapeshellarg( $theme_file ) . " 2>&1", $output, $return_var );
if ( $return_var === 0 ) {
	echo "✅ Syntax check passed\n";
} else {
	echo "❌ Syntax errors:\n";
	echo implode( "\n", $output ) . "\n";
}
