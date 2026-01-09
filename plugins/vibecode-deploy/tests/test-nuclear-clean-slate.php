<?php
/**
 * Test that nuclear operation provides a clean slate (delete only, no rollback)
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/CleanupService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/RollbackService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Services\CleanupService;
use VibeCode\Deploy\Services\RollbackService;
use VibeCode\Deploy\Importer;

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

echo "=== Testing Nuclear Operation Clean Slate ===" . PHP_EOL;
echo PHP_EOL;

$project_slug = 'test_nuclear_' . time();

// Test 1: Create some test content
echo "Test 1: Creating test content..." . PHP_EOL;
$page_id = wp_insert_post( array(
	'post_title' => 'Test Nuclear Page',
	'post_content' => 'Test content',
	'post_status' => 'publish',
	'post_type' => 'page',
) );

if ( $page_id ) {
	update_post_meta( $page_id, Importer::META_PROJECT_SLUG, $project_slug );
	update_post_meta( $page_id, Importer::META_SOURCE_PATH, 'test.html' );
	update_post_meta( $page_id, Importer::META_FINGERPRINT, 'test-fingerprint' );
	
	test_result(
		'Test page created',
		true,
		"Page ID: {$page_id}"
	);
	
	// Verify page exists
	$page = get_post( $page_id );
	test_result(
		'Test page exists before nuclear',
		$page !== null && $page->post_title === 'Test Nuclear Page',
		$page ? "Found: {$page->post_title}" : 'Page not found'
	);
} else {
	test_result( 'Could not create test page', false, 'wp_insert_post failed' );
	exit( 1 );
}

// Test 2: Run nuclear operation
echo PHP_EOL . "Test 2: Running nuclear operation..." . PHP_EOL;
$results = CleanupService::nuclear_operation( $project_slug, 'everything', array(), array(), 'delete' );

test_result(
	'Nuclear operation returns results',
	isset( $results['deleted_pages'] ),
	'Results structure check'
);

test_result(
	'Nuclear operation deleted pages',
	$results['deleted_pages'] > 0,
	"Deleted: {$results['deleted_pages']} pages"
);

// Test 3: Verify page is deleted (clean slate)
echo PHP_EOL . "Test 3: Verifying clean slate..." . PHP_EOL;
$deleted_page = get_post( $page_id );
test_result(
	'Page is deleted (clean slate)',
	$deleted_page === null,
	$deleted_page ? "Page still exists: {$deleted_page->post_title}" : 'Page deleted successfully'
);

// Test 4: Verify nuclear doesn't attempt rollback
echo PHP_EOL . "Test 4: Verifying no rollback attempted..." . PHP_EOL;
test_result(
	'Nuclear operation does not restore pages',
	! isset( $results['restored_pages'] ) || $results['restored_pages'] === 0,
	"Restored pages: " . ( $results['restored_pages'] ?? 0 )
);

test_result(
	'Nuclear operation does not restore templates',
	! isset( $results['restored_templates'] ) || $results['restored_templates'] === 0,
	"Restored templates: " . ( $results['restored_templates'] ?? 0 )
);

test_result(
	'Nuclear operation does not restore template parts',
	! isset( $results['restored_template_parts'] ) || $results['restored_template_parts'] === 0,
	"Restored template parts: " . ( $results['restored_template_parts'] ?? 0 )
);

// Test 5: Verify action parameter is ignored (always deletes)
echo PHP_EOL . "Test 5: Verifying action parameter is ignored..." . PHP_EOL;
$results_rollback_action = CleanupService::nuclear_operation( $project_slug, 'everything', array(), array(), 'rollback' );
test_result(
	'Nuclear operation ignores action parameter',
	! isset( $results_rollback_action['restored_pages'] ) || $results_rollback_action['restored_pages'] === 0,
	"Even with 'rollback' action, restored pages: " . ( $results_rollback_action['restored_pages'] ?? 0 )
);

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
echo "Total: " . ( $passed + $failed ) . PHP_EOL;

if ( $failed > 0 ) {
	echo PHP_EOL . "❌ Some tests failed!" . PHP_EOL;
	exit( 1 );
} else {
	echo PHP_EOL . "✅ All tests passed! Nuclear operation provides clean slate." . PHP_EOL;
	exit( 0 );
}
