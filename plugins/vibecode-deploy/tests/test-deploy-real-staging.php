<?php
/**
 * Test staging zip deployment with actual zip file
 * Usage: php -r "require 'wp-load.php'; require 'this-file.php';"
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Staging.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Staging;
use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

$test_results = array();
$passed = 0;
$failed = 0;

function test_result( $name, $passed_test, $message = '' ) {
	global $test_results, $passed, $failed;
	$test_results[] = array(
		'name' => $name,
		'passed' => $passed_test,
		'message' => $message,
	);
	if ( $passed_test ) {
		$passed++;
		echo "✅ PASS: {$name}" . PHP_EOL;
	} else {
		$failed++;
		echo "❌ FAIL: {$name}" . PHP_EOL;
		if ( $message ) {
			echo "   {$message}" . PHP_EOL;
		}
	}
}

echo "=== Testing Real Staging Zip Deployment ===" . PHP_EOL;
echo PHP_EOL;

// Get project slug from settings
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';

echo "Project slug: {$project_slug}" . PHP_EOL;
echo PHP_EOL;

// Find staging zip file
$staging_zip = '';
$possible_paths = array(
	dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/CFA/vibecode-deploy-staging.zip',
	dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/CFA/dist/vibecode-deploy-staging.zip',
	'/var/www/html/vibecode-deploy-staging.zip',
);

foreach ( $possible_paths as $path ) {
	if ( file_exists( $path ) ) {
		$staging_zip = $path;
		break;
	}
}

if ( $staging_zip === '' ) {
	// Try uploads directory
	$upload_dir = wp_upload_dir();
	$uploads_base = $upload_dir['basedir'];
	$zip_files = glob( $uploads_base . '/*staging*.zip' );
	if ( ! empty( $zip_files ) ) {
		$staging_zip = $zip_files[0];
	}
}

if ( $staging_zip === '' || ! file_exists( $staging_zip ) ) {
	echo "❌ No staging zip file found." . PHP_EOL;
	echo "   Searched:" . PHP_EOL;
	foreach ( $possible_paths as $path ) {
		echo "   - {$path}" . PHP_EOL;
	}
	exit( 1 );
}

echo "📦 Found staging zip: {$staging_zip}" . PHP_EOL;
echo "   Size: " . number_format( filesize( $staging_zip ) / 1024, 2 ) . " KB" . PHP_EOL;
echo PHP_EOL;

// Copy zip to uploads if needed
$upload_dir = wp_upload_dir();
$uploads_base = $upload_dir['basedir'];
$upload_zip = $uploads_base . '/test-staging-' . time() . '.zip';

if ( ! copy( $staging_zip, $upload_zip ) ) {
	test_result( 'Could not copy zip to uploads', false, 'copy() failed' );
	exit( 1 );
}

test_result( 'Zip copied to uploads directory', true, "Upload path: {$upload_zip}" );

// Extract zip
echo PHP_EOL . "Test 1: Extracting staging zip..." . PHP_EOL;
$extract_result = Staging::extract_zip_to_staging( $upload_zip, $project_slug );

test_result(
	'Zip extraction succeeded',
	is_array( $extract_result ) && isset( $extract_result['ok'] ) && $extract_result['ok'] === true,
	isset( $extract_result['error'] ) ? $extract_result['error'] : ''
);

if ( ! isset( $extract_result['fingerprint'] ) ) {
	echo "❌ No fingerprint returned from extraction" . PHP_EOL;
	exit( 1 );
}

$fingerprint = $extract_result['fingerprint'];
echo "   Fingerprint: {$fingerprint}" . PHP_EOL;
echo "   Files extracted: " . ( $extract_result['files'] ?? 0 ) . PHP_EOL;
echo PHP_EOL;

// Run preflight
echo "Test 2: Running preflight..." . PHP_EOL;
$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
$preflight = Importer::preflight( $project_slug, $build_root );

test_result(
	'Preflight returns results',
	is_array( $preflight ) && isset( $preflight['pages_total'] ),
	"Pages found: " . ( $preflight['pages_total'] ?? 0 )
);

$pages_total = $preflight['pages_total'] ?? 0;
echo "   Pages found: {$pages_total}" . PHP_EOL;

if ( isset( $preflight['items'] ) && is_array( $preflight['items'] ) && count( $preflight['items'] ) > 0 ) {
	echo "   First 5 pages:" . PHP_EOL;
	foreach ( array_slice( $preflight['items'], 0, 5 ) as $item ) {
		$slug = $item['slug'] ?? 'unknown';
		$action = $item['action'] ?? 'unknown';
		echo "     - {$slug} ({$action})" . PHP_EOL;
	}
}

echo PHP_EOL;

// Deploy
echo "Test 3: Deploying pages..." . PHP_EOL;
$deploy_result = Importer::run_import(
	$project_slug,
	$fingerprint,
	$build_root,
	false, // set_front_page
	false, // force_claim_unowned
	true,  // deploy_template_parts (ENABLED for full deployment)
	true,  // generate_404_template (ENABLED for full deployment)
	false, // force_claim_templates
	false, // validate_cpt_shortcodes
	array(), // selected_pages
	array(), // selected_css
	array(), // selected_js
	array(), // selected_templates
	array(), // selected_template_parts
	array()  // selected_theme_files
);

test_result(
	'Deploy returns results',
	is_array( $deploy_result ) && isset( $deploy_result['created'] ),
	"Created: {$deploy_result['created']}, Updated: {$deploy_result['updated']}, Errors: {$deploy_result['errors']}"
);

$created = $deploy_result['created'] ?? 0;
$updated = $deploy_result['updated'] ?? 0;
$errors = $deploy_result['errors'] ?? 0;

echo "   Created: {$created} pages" . PHP_EOL;
echo "   Updated: {$updated} pages" . PHP_EOL;
echo "   Errors: {$errors}" . PHP_EOL;
echo PHP_EOL;

test_result(
	'Pages were created or updated',
	$created > 0 || $updated > 0,
	"Created: {$created}, Updated: {$updated}"
);

test_result(
	'No deployment errors',
	$errors === 0,
	"Errors: {$errors}"
);

// Verify pages exist
echo PHP_EOL . "Test 4: Verifying deployed pages..." . PHP_EOL;
$pages = get_posts( array(
	'post_type' => 'page',
	'post_status' => 'any',
	'posts_per_page' => -1,
	'meta_query' => array(
		array(
			'key' => Importer::META_PROJECT_SLUG,
			'value' => $project_slug,
			'compare' => '=',
		),
	),
) );

$pages_count = count( $pages );
test_result(
	'Deployed pages exist in WordPress',
	$pages_count > 0,
	"Found {$pages_count} pages with project slug"
);

if ( $pages_count > 0 ) {
	echo "   Sample pages:" . PHP_EOL;
	foreach ( array_slice( $pages, 0, 5 ) as $page ) {
		echo "     - {$page->post_name} ({$page->post_title})" . PHP_EOL;
	}
}

// Cleanup
@unlink( $upload_zip );

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
echo "Total: " . ( $passed + $failed ) . PHP_EOL;
echo PHP_EOL;
echo "Deployment Details:" . PHP_EOL;
echo "  Project: {$project_slug}" . PHP_EOL;
echo "  Fingerprint: {$fingerprint}" . PHP_EOL;
echo "  Pages found: {$pages_total}" . PHP_EOL;
echo "  Pages created: {$created}" . PHP_EOL;
echo "  Pages updated: {$updated}" . PHP_EOL;
echo "  Pages in WordPress: {$pages_count}" . PHP_EOL;
echo "  Errors: {$errors}" . PHP_EOL;

if ( $failed > 0 ) {
	echo PHP_EOL . "❌ Some tests failed!" . PHP_EOL;
	exit( 1 );
} else {
	echo PHP_EOL . "✅ All tests passed! Staging deployment successful." . PHP_EOL;
	exit( 0 );
}
