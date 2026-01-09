<?php
/**
 * Test class prefix auto-detection from staging files
 */

// Load WordPress if not already loaded
if ( ! defined( 'ABSPATH' ) ) {
	// Try to find wp-load.php
	$wp_load_paths = array(
		dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php', // From plugin tests directory
		dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php', // Alternative path
	);
	
	$wp_loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$wp_loaded = true;
			break;
		}
	}
	
	if ( ! $wp_loaded ) {
		die( "Error: Could not find wp-load.php. Please run this test from WordPress root or adjust the path.\n" );
	}
}

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ClassPrefixDetector.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Services\ClassPrefixDetector;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

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

echo "=== Testing Class Prefix Auto-Detection ===" . PHP_EOL;
echo PHP_EOL;

// Test 1: Create test staging directory with CFA-style classes
echo "Test 1: Creating test staging with 'cfa-' prefix..." . PHP_EOL;
$test_dir = sys_get_temp_dir() . '/vibecode-prefix-test-' . time();
mkdir( $test_dir, 0755, true );
mkdir( $test_dir . '/pages', 0755, true );
mkdir( $test_dir . '/css', 0755, true );

// Create test HTML file with cfa- classes
$test_html = '<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
</head>
<body>
    <main class="cfa-main">
        <section class="cfa-hero cfa-hero--compact">
            <div class="cfa-hero__container">
                <h1 class="cfa-hero__title">Test</h1>
            </div>
        </section>
        <section class="cfa-page-section">
            <div class="cfa-container">
                <div class="cfa-page-card">
                    <p>Content</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>';

file_put_contents( $test_dir . '/pages/test.html', $test_html );

// Create test CSS file with cfa- classes
$test_css = '.cfa-main {
    margin: 0;
}

.cfa-hero {
    padding: 2rem 0;
}

.cfa-hero--compact {
    padding: 1rem 0;
}

.cfa-page-section {
    padding: 2rem 0;
}

.cfa-container {
    max-width: 1200px;
    margin: 0 auto;
}

.cfa-page-card {
    background: #fff;
    padding: 1.5rem;
}

.cfa-button {
    padding: 0.5rem 1rem;
}';

file_put_contents( $test_dir . '/css/styles.css', $test_css );

$detected = ClassPrefixDetector::detect_from_staging( $test_dir );
test(
	'Detect cfa- prefix from test files',
	$detected === 'cfa-',
	"Detected: '{$detected}' (expected: 'cfa-')"
);

// Cleanup
array_map( 'unlink', glob( $test_dir . '/**/*' ) );
array_map( 'rmdir', array_reverse( glob( $test_dir . '/**' ) ) );
rmdir( $test_dir );

// Test 2: Test with different prefix
echo PHP_EOL . "Test 2: Testing with 'my-site-' prefix..." . PHP_EOL;
$test_dir2 = sys_get_temp_dir() . '/vibecode-prefix-test2-' . time();
mkdir( $test_dir2, 0755, true );
mkdir( $test_dir2 . '/pages', 0755, true );
mkdir( $test_dir2 . '/css', 0755, true );

$test_html2 = '<main class="my-site-main"><section class="my-site-hero"></section></main>';
file_put_contents( $test_dir2 . '/pages/test.html', $test_html2 );

$test_css2 = '.my-site-main { margin: 0; } .my-site-hero { padding: 2rem; }';
file_put_contents( $test_dir2 . '/css/styles.css', $test_css2 );

$detected2 = ClassPrefixDetector::detect_from_staging( $test_dir2 );
test(
	'Detect my-site- prefix from test files',
	$detected2 === 'my-site-',
	"Detected: '{$detected2}' (expected: 'my-site-')"
);

// Cleanup
array_map( 'unlink', glob( $test_dir2 . '/**/*' ) );
array_map( 'rmdir', array_reverse( glob( $test_dir2 . '/**' ) ) );
rmdir( $test_dir2 );

// Test 3: Test with no prefix (should return empty)
echo PHP_EOL . "Test 3: Testing with no prefix (unprefixed classes)..." . PHP_EOL;
$test_dir3 = sys_get_temp_dir() . '/vibecode-prefix-test3-' . time();
mkdir( $test_dir3, 0755, true );
mkdir( $test_dir3 . '/pages', 0755, true );

$test_html3 = '<main class="main"><section class="hero"></section></main>';
file_put_contents( $test_dir3 . '/pages/test.html', $test_html3 );

$detected3 = ClassPrefixDetector::detect_from_staging( $test_dir3 );
test(
	'No prefix detected for unprefixed classes',
	$detected3 === '',
	"Detected: '{$detected3}' (expected: empty string)"
);

// Cleanup
array_map( 'unlink', glob( $test_dir3 . '/**/*' ) );
array_map( 'rmdir', array_reverse( glob( $test_dir3 . '/**' ) ) );
rmdir( $test_dir3 );

// Test 4: Test with actual staging files (if available)
echo PHP_EOL . "Test 4: Testing with actual staging files..." . PHP_EOL;
$settings = Settings::get_all();
$project_slug = $settings['project_slug'] ?? '';
if ( $project_slug === '' ) {
	echo "Skipping Test 4: No project_slug configured in settings." . PHP_EOL;
	exit;
}
$fingerprints = BuildService::list_build_fingerprints( $project_slug );

if ( ! empty( $fingerprints ) ) {
	$latest_fingerprint = $fingerprints[0];
	$build_root = BuildService::build_root_path( $project_slug, $latest_fingerprint );
	
	if ( is_dir( $build_root ) ) {
		$detected4 = ClassPrefixDetector::detect_from_staging( $build_root );
		test(
			'Detect prefix from actual CFA staging files',
			$detected4 === 'cfa-',
			"Detected: '{$detected4}' from fingerprint: {$latest_fingerprint}"
		);
	} else {
		test(
			'CFA staging directory exists',
			false,
			"Build root not found: {$build_root}"
		);
	}
} else {
	test(
		'CFA staging files available',
		false,
		'No staging fingerprints found for project: ' . $project_slug
	);
}

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$tests_passed}" . PHP_EOL;
echo "Failed: {$tests_failed}" . PHP_EOL;
echo "Total: " . ( $tests_passed + $tests_failed ) . PHP_EOL;

if ( $tests_failed === 0 ) {
	echo PHP_EOL . "✅ All tests passed!" . PHP_EOL;
	exit( 0 );
} else {
	echo PHP_EOL . "❌ Some tests failed." . PHP_EOL;
	exit( 1 );
}
