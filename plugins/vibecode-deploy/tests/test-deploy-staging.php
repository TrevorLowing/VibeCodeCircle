<?php
/**
 * Test staging zip deployment workflow
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Staging.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ManifestService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';

use VibeCode\Deploy\Staging;
use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Services\ManifestService;
use VibeCode\Deploy\Services\BuildService;

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

echo "=== Testing Staging Zip Deployment ===" . PHP_EOL;
echo PHP_EOL;

$project_slug = 'test_deploy_' . time();
$temp_dir = sys_get_temp_dir() . '/vibecode-test-' . time();
$staging_dir = $temp_dir . '/vibecode-deploy-staging';

// Test 1: Create test staging structure
echo "Test 1: Creating test staging structure..." . PHP_EOL;
mkdir( $temp_dir, 0755, true );
mkdir( $staging_dir, 0755, true );
mkdir( $staging_dir . '/pages', 0755, true );
mkdir( $staging_dir . '/css', 0755, true );
mkdir( $staging_dir . '/js', 0755, true );

// Create test HTML page with proper structure for deployment
$test_page_content = '<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="cfa-page-content">
        <main class="cfa-main">
            <h1>Test Page</h1>
            <p>This is a test page.</p>
        </main>
    </div>
    <script src="js/main.js"></script>
</body>
</html>';

file_put_contents( $staging_dir . '/pages/test-page.html', $test_page_content );

// Create test CSS
file_put_contents( $staging_dir . '/css/styles.css', 'body { margin: 0; }' );

// Create test JS
file_put_contents( $staging_dir . '/js/main.js', 'console.log("test");' );

test_result(
	'Test staging structure created',
	is_dir( $staging_dir ) && is_file( $staging_dir . '/pages/test-page.html' ),
	"Staging dir: {$staging_dir}"
);

// Test 2: Create staging zip
echo PHP_EOL . "Test 2: Creating staging zip..." . PHP_EOL;
$zip_path = $temp_dir . '/test-staging.zip';
$zip = new ZipArchive();
if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $staging_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	
	foreach ( $iterator as $file ) {
		$file_path = $file->getRealPath();
		$relative_path = substr( $file_path, strlen( $staging_dir ) + 1 );
		
		if ( $file->isDir() ) {
			$zip->addEmptyDir( 'vibecode-deploy-staging/' . $relative_path );
		} else {
			$zip->addFile( $file_path, 'vibecode-deploy-staging/' . $relative_path );
		}
	}
	
	$zip->close();
	
	test_result(
		'Staging zip created',
		is_file( $zip_path ),
		"Zip size: " . filesize( $zip_path ) . " bytes"
	);
} else {
	test_result( 'Failed to create zip', false, 'ZipArchive::open failed' );
	exit( 1 );
}

// Test 3: Extract staging zip
echo PHP_EOL . "Test 3: Extracting staging zip..." . PHP_EOL;
$upload_dir = wp_upload_dir();
$staging_base = $upload_dir['basedir'] . '/vibecode-deploy/staging/' . $project_slug;

// Copy zip to uploads directory for extraction
$upload_zip = $upload_dir['basedir'] . '/test-staging-' . time() . '.zip';
copy( $zip_path, $upload_zip );

$extract_result = Staging::extract_zip_to_staging( $upload_zip, $project_slug );

test_result(
	'Staging zip extracted',
	is_array( $extract_result ) && isset( $extract_result['fingerprint'] ),
	isset( $extract_result['fingerprint'] ) ? "Fingerprint: {$extract_result['fingerprint']}" : 'No fingerprint'
);

$fingerprint = $extract_result['fingerprint'] ?? '';

// Test 4: Run preflight
echo PHP_EOL . "Test 4: Running preflight..." . PHP_EOL;
if ( $fingerprint ) {
	$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
	
	// Debug: Check if pages directory exists
	if ( is_dir( $build_root ) ) {
		$pages_dir = $build_root . '/pages';
		test_result(
			'Pages directory exists after extraction',
			is_dir( $pages_dir ),
			"Pages dir: {$pages_dir}"
		);
		
		if ( is_dir( $pages_dir ) ) {
			$page_files = glob( $pages_dir . '/*.html' );
			test_result(
				'HTML files found in pages directory',
				count( $page_files ) > 0,
				"Found " . count( $page_files ) . " HTML files"
			);
		}
	}
	
	$preflight = Importer::preflight( $project_slug, $build_root );
	
	test_result(
		'Preflight returns results',
		is_array( $preflight ) && isset( $preflight['pages_total'] ),
		"Pages found: " . ( $preflight['pages_total'] ?? 0 )
	);
	
	test_result(
		'Preflight finds test page',
		isset( $preflight['pages_total'] ) && $preflight['pages_total'] > 0,
		"Total pages: {$preflight['pages_total']}"
	);
	
	if ( isset( $preflight['items'] ) && is_array( $preflight['items'] ) ) {
		$found_test_page = false;
		foreach ( $preflight['items'] as $item ) {
			if ( isset( $item['slug'] ) && $item['slug'] === 'test-page' ) {
				$found_test_page = true;
				break;
			}
		}
		test_result(
			'Preflight finds test-page slug',
			$found_test_page,
			$found_test_page ? 'Found test-page' : 'test-page not found in preflight items'
		);
	}
} else {
	test_result( 'Cannot run preflight without fingerprint', false, 'No fingerprint from extraction' );
}

// Test 5: Deploy pages
echo PHP_EOL . "Test 5: Deploying pages..." . PHP_EOL;
if ( $fingerprint ) {
	$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
	
	test_result(
		'Build root path exists',
		is_dir( $build_root ),
		"Build root: {$build_root}"
	);
	
	$deploy_result = Importer::run_import(
		$project_slug,
		$fingerprint,
		$build_root,
		false, // force_claim_unowned
		false, // deploy_template_parts
		false, // generate_404_template
		false, // force_claim_templates
		false, // validate_cpt_shortcodes
		array(), // selected_pages (empty = all)
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
	
	// Debug: Check deploy result details
	if ( isset( $deploy_result['errors'] ) && $deploy_result['errors'] > 0 ) {
		echo "   ⚠️  Deployment had {$deploy_result['errors']} errors" . PHP_EOL;
	}
	
	test_result(
		'Pages were created',
		isset( $deploy_result['created'] ) && $deploy_result['created'] > 0,
		"Created {$deploy_result['created']} pages (Updated: {$deploy_result['updated']}, Skipped: {$deploy_result['skipped']}, Errors: {$deploy_result['errors']})"
	);
	
	// Verify page exists in WordPress
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
	
	test_result(
		'Deployed page exists in WordPress',
		count( $pages ) > 0,
		"Found " . count( $pages ) . " pages with project slug"
	);
	
	// Check if test-page exists
	$test_page_found = false;
	foreach ( $pages as $page ) {
		if ( $page->post_name === 'test-page' ) {
			$test_page_found = true;
			test_result(
				'Test page deployed correctly',
				$page->post_title === 'Test Page' || strpos( $page->post_content, 'Test Page' ) !== false,
				"Page title: {$page->post_title}"
			);
			break;
		}
	}
	
	if ( ! $test_page_found ) {
		test_result( 'Test page not found after deployment', false, 'test-page slug not found' );
	}
} else {
	test_result( 'Cannot deploy without fingerprint', false, 'No fingerprint from extraction' );
}

// Cleanup
echo PHP_EOL . "Cleaning up test files..." . PHP_EOL;
@unlink( $zip_path );
@unlink( $upload_zip );
@unlink( $staging_dir . '/pages/test-page.html' );
@unlink( $staging_dir . '/css/styles.css' );
@unlink( $staging_dir . '/js/main.js' );
@rmdir( $staging_dir . '/pages' );
@rmdir( $staging_dir . '/css' );
@rmdir( $staging_dir . '/js' );
@rmdir( $staging_dir );
@rmdir( $temp_dir );

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
echo "Total: " . ( $passed + $failed ) . PHP_EOL;

if ( $failed > 0 ) {
	echo PHP_EOL . "❌ Some tests failed!" . PHP_EOL;
	exit( 1 );
} else {
	echo PHP_EOL . "✅ All tests passed! Staging deployment works correctly." . PHP_EOL;
	exit( 0 );
}
