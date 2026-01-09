<?php
/**
 * Test CPT functionality and page content
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

echo "=== CPT Functionality and Page Content Test ===" . PHP_EOL;
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

// 1. Check CPTs are registered
echo "=== Custom Post Types ===" . PHP_EOL;
$expected_cpts = array( 'cfa_advisory', 'cfa_investigation', 'cfa_foia_request', 'cfa_survey', 'cfa_evidence_record' );
foreach ( $expected_cpts as $cpt ) {
	$exists = post_type_exists( $cpt );
	test( "CPT registered: {$cpt}", $exists, $exists ? 'Registered' : 'Not registered' );
}
echo PHP_EOL;

// 2. Check functions.php was deployed
echo "=== Theme Functions ===" . PHP_EOL;
$theme = wp_get_theme();
$theme_slug = $theme->get_stylesheet();
$theme_dir = get_theme_root() . '/' . $theme_slug;
$functions_file = $theme_dir . '/functions.php';

test( 'functions.php exists', file_exists( $functions_file ), "Path: {$functions_file}" );

if ( file_exists( $functions_file ) ) {
	$functions_content = file_get_contents( $functions_file );
	$has_cpt_registration = strpos( $functions_content, 'register_post_type' ) !== false;
	test( 'functions.php contains CPT registration', $has_cpt_registration, $has_cpt_registration ? 'Found register_post_type' : 'No register_post_type found' );
	
	// Check for specific CPTs
	foreach ( $expected_cpts as $cpt ) {
		$has_cpt = strpos( $functions_content, "'{$cpt}'" ) !== false || strpos( $functions_content, "\"{$cpt}\"" ) !== false;
		test( "functions.php contains {$cpt}", $has_cpt, $has_cpt ? 'Found' : 'Not found' );
	}
}
echo PHP_EOL;

// 3. Check ACF JSON files
echo "=== ACF JSON Files ===" . PHP_EOL;
$acf_json_dir = $theme_dir . '/acf-json';
test( 'ACF JSON directory exists', is_dir( $acf_json_dir ), "Path: {$acf_json_dir}" );

if ( is_dir( $acf_json_dir ) ) {
	$acf_files = glob( $acf_json_dir . '/*.json' );
	test( 'ACF JSON files exist', ! empty( $acf_files ), "Found " . count( $acf_files ) . " files" );
	
	$expected_acf = array( 'group_cfa_advisory', 'group_cfa_investigation', 'group_cfa_foia_request', 'group_cfa_survey' );
	foreach ( $expected_acf as $acf_group ) {
		$found = false;
		foreach ( $acf_files as $file ) {
			if ( strpos( basename( $file ), $acf_group ) !== false ) {
				$found = true;
				break;
			}
		}
		test( "ACF JSON exists: {$acf_group}", $found, $found ? 'Found' : 'Not found' );
	}
}
echo PHP_EOL;

// 4. Check page content for shortcodes
echo "=== Page Content and Shortcodes ===" . PHP_EOL;
$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';

$pages_to_check = array(
	'advisories' => array( 'shortcode' => 'cfa_advisories', 'description' => 'Advisories page' ),
	'investigations' => array( 'shortcode' => 'cfa_investigations', 'description' => 'Investigations page' ),
	'foia-reading-room' => array( 'shortcode' => 'cfa_foia_index', 'description' => 'FOIA Reading Room page' ),
	'home' => array( 'shortcode' => 'cfa_surveys', 'description' => 'Home page' ),
);

foreach ( $pages_to_check as $page_slug => $config ) {
	$page = get_page_by_path( $page_slug );
	if ( $page ) {
		$content = $page->post_content;
		$has_shortcode = strpos( $content, '[' . $config['shortcode'] ) !== false;
		$has_placeholder = strpos( $content, 'VIBECODE_SHORTCODE' ) !== false || strpos( $content, 'CFA_SHORTCODE' ) !== false;
		$page_fingerprint = get_post_meta( $page->ID, Importer::META_FINGERPRINT, true );
		$page_project = get_post_meta( $page->ID, Importer::META_PROJECT_SLUG, true );
		
		test( "Page exists: {$page_slug}", true, "ID: {$page->ID}" );
		test( "Page has project slug", $page_project === $project_slug, "Project: {$page_project}" );
		test( "Page has fingerprint", $page_fingerprint !== '', "Fingerprint: {$page_fingerprint}" );
		test( "Page has shortcode or placeholder: {$config['description']}", $has_shortcode || $has_placeholder, 
			$has_shortcode ? "Has shortcode [{$config['shortcode']}]" : ( $has_placeholder ? 'Has placeholder' : 'No shortcode/placeholder found' ) );
		
		// Check if content looks recent
		$content_length = strlen( $content );
		test( "Page has content", $content_length > 100, "Content length: {$content_length} chars" );
	} else {
		test( "Page exists: {$page_slug}", false, "Page not found" );
	}
}
echo PHP_EOL;

// 5. Check latest staging content
echo "=== Staging Content Check ===" . PHP_EOL;
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );
if ( $active_fingerprint !== '' ) {
	$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
	$pages_dir = Importer::pages_dir( $build_root );
	
	test( 'Staging pages directory exists', is_dir( $pages_dir ), "Path: {$pages_dir}" );
	
	if ( is_dir( $pages_dir ) ) {
		$staging_pages = glob( $pages_dir . '/*.html' );
		test( 'Staging has HTML pages', ! empty( $staging_pages ), "Found " . count( $staging_pages ) . " pages" );
		
		// Check a specific page in staging
		$advisories_staging = $pages_dir . '/advisories.html';
		if ( file_exists( $advisories_staging ) ) {
			$staging_content = file_get_contents( $advisories_staging );
			$has_placeholder = strpos( $staging_content, 'VIBECODE_SHORTCODE' ) !== false || strpos( $staging_content, 'CFA_SHORTCODE' ) !== false || strpos( $staging_content, 'cfa_advisories' ) !== false;
			test( 'Staging advisories.html has shortcode/placeholder', $has_placeholder, $has_placeholder ? 'Found' : 'Not found' );
			
			$staging_mtime = filemtime( $advisories_staging );
			$staging_date = date( 'Y-m-d H:i:s', $staging_mtime );
			echo "   Staging file modified: {$staging_date}" . PHP_EOL;
		}
	}
}
echo PHP_EOL;

// 6. Check shortcode functions exist
echo "=== Shortcode Functions ===" . PHP_EOL;
$expected_shortcodes = array( 'cfa_advisories', 'cfa_investigations', 'cfa_foia_index', 'cfa_surveys' );
foreach ( $expected_shortcodes as $shortcode ) {
	$exists = shortcode_exists( $shortcode );
	test( "Shortcode registered: {$shortcode}", $exists, $exists ? 'Registered' : 'Not registered' );
}
echo PHP_EOL;

// Summary
echo "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$tests_passed}" . PHP_EOL;
echo "Failed: {$tests_failed}" . PHP_EOL;
echo "Total: " . ( $tests_passed + $tests_failed ) . PHP_EOL;
echo PHP_EOL;

if ( $tests_failed === 0 ) {
	echo "✅ All tests passed!" . PHP_EOL;
	exit( 0 );
} else {
	echo "❌ Some tests failed. Review the output above." . PHP_EOL;
	exit( 1 );
}
