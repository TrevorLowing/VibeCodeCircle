<?php
/**
 * Deploy missing template parts from staging
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Staging.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/TemplateService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Staging;
use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Services\TemplateService;
use VibeCode\Deploy\Settings;

echo "=== Deploying Missing Template Parts ===" . PHP_EOL;
echo PHP_EOL;

// Get project slug
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
echo "Project slug: {$project_slug}" . PHP_EOL;

// Find latest staging directory
$upload_dir = wp_upload_dir();
$uploads_base = $upload_dir['basedir'];
$staging_dirs = glob( $uploads_base . '/vibecode-deploy/staging/' . $project_slug . '/*' );
if ( empty( $staging_dirs ) ) {
	echo "❌ No staging directories found for project '{$project_slug}'" . PHP_EOL;
	exit( 1 );
}

// Get latest staging directory (by fingerprint timestamp)
usort( $staging_dirs, function( $a, $b ) {
	return filemtime( $b ) - filemtime( $a );
});
$build_root = $staging_dirs[0];
$fingerprint = basename( $build_root );

echo "Using staging: {$fingerprint}" . PHP_EOL;
echo "Build root: {$build_root}" . PHP_EOL;
echo PHP_EOL;

// Check for template-parts directory
$template_parts_dir = $build_root . '/template-parts';
if ( ! is_dir( $template_parts_dir ) ) {
	echo "❌ No template-parts directory found in staging" . PHP_EOL;
	exit( 1 );
}

// Get list of template part files
$template_part_files = glob( $template_parts_dir . '/*.html' );
if ( empty( $template_part_files ) ) {
	echo "❌ No template part files found" . PHP_EOL;
	exit( 1 );
}

echo "Found " . count( $template_part_files ) . " template part files:" . PHP_EOL;
foreach ( $template_part_files as $file ) {
	$slug = basename( $file, '.html' );
	echo "  - {$slug}" . PHP_EOL;
}
echo PHP_EOL;

// Get resources base URL (needed for URL rewriting)
$uploads = wp_upload_dir();
$resources_base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $project_slug ) . '/' . rawurlencode( $fingerprint ) . '/resources';

// Get slug set for URL rewriting
$pages_dir = Importer::pages_dir( $build_root );
$slug_set = array();
if ( is_dir( $pages_dir ) ) {
	$html_files = glob( $pages_dir . '/*.html' );
	foreach ( $html_files as $file ) {
		$slug = basename( $file, '.html' );
		$slug_set[] = $slug;
	}
}

// Deploy template parts using the public method
echo "Deploying template parts..." . PHP_EOL;
$result = TemplateService::deploy_template_parts_and_404_template(
	$project_slug,
	$fingerprint,
	$build_root,
	$slug_set,
	$resources_base_url,
	true,  // deploy_template_parts
	false, // generate_404_template
	true,  // force_claim_templates (force claim to update existing ones)
	array(), // selected_templates
	array()  // selected_template_parts
);

$deployed = ( $result['created'] ?? 0 ) + ( $result['updated'] ?? 0 );
$errors = $result['errors'] ?? 0;

if ( isset( $result['created_parts'] ) && is_array( $result['created_parts'] ) ) {
	foreach ( $result['created_parts'] as $part ) {
		echo "  ✅ Created {$part['slug']} (ID: {$part['post_id']})" . PHP_EOL;
	}
}

if ( isset( $result['updated_parts'] ) && is_array( $result['updated_parts'] ) ) {
	foreach ( $result['updated_parts'] as $part ) {
		echo "  ✅ Updated {$part['slug']} (ID: {$part['post_id']})" . PHP_EOL;
	}
}

if ( ! empty( $result['error_messages'] ) ) {
	foreach ( $result['error_messages'] as $error ) {
		echo "  ❌ Error: {$error}" . PHP_EOL;
	}
}

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Deployed: {$deployed}" . PHP_EOL;
echo "Errors: {$errors}" . PHP_EOL;

if ( $errors > 0 ) {
	echo PHP_EOL . "❌ Some template parts failed to deploy" . PHP_EOL;
	exit( 1 );
} else {
	echo PHP_EOL . "✅ All template parts deployed successfully" . PHP_EOL;
	exit( 0 );
}
