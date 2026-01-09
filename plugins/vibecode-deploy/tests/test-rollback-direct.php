<?php
/**
 * Direct test script for RollbackService
 * Can be run via: wp eval-file or php -r "require 'wp-load.php'; require 'this-file.php';"
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/RollbackService.php';

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

echo "=== Testing RollbackService ===" . PHP_EOL;
echo PHP_EOL;

// Test 1: restore_post_snapshot with missing post (should be skippable)
echo "Test 1: restore_post_snapshot with missing post..." . PHP_EOL;
$reflection = new ReflectionClass( RollbackService::class );
$method = $reflection->getMethod( 'restore_post_snapshot' );
$method->setAccessible( true );

$result = $method->invokeArgs( null, array( 99999, array( 'post_content' => 'Test', 'post_title' => 'Test', 'post_status' => 'publish' ), array() ) );

test_result(
	'restore_post_snapshot with missing post returns skippable error',
	$result['success'] === false && isset( $result['skippable'] ) && $result['skippable'] === true,
	isset( $result['error'] ) ? $result['error'] : 'No error message'
);

// Test 2: restore_post_snapshot with existing post (should succeed)
echo PHP_EOL . "Test 2: restore_post_snapshot with existing post..." . PHP_EOL;
$post_id = wp_insert_post( array(
	'post_title' => 'Test Post',
	'post_content' => 'Original content',
	'post_status' => 'publish',
) );

if ( $post_id ) {
	update_post_meta( $post_id, Importer::META_PROJECT_SLUG, 'test_project' );
	
	$result = $method->invokeArgs( null, array( $post_id, array( 'post_content' => 'Restored content', 'post_title' => 'Restored Title', 'post_status' => 'publish' ), array() ) );
	
	test_result(
		'restore_post_snapshot with existing post succeeds',
		$result['success'] === true,
		isset( $result['error'] ) ? $result['error'] : ''
	);
	
	// Verify post was restored
	$restored_post = get_post( $post_id );
	test_result(
		'Post content was restored correctly',
		$restored_post && $restored_post->post_content === 'Restored content',
		'Expected: Restored content, Got: ' . ( $restored_post ? $restored_post->post_content : 'null' )
	);
	
	// Clean up
	wp_delete_post( $post_id, true );
} else {
	test_result( 'Could not create test post', false, 'wp_insert_post failed' );
}

// Test 3: rollback_deploy separates warnings from errors
echo PHP_EOL . "Test 3: rollback_deploy separates warnings from errors..." . PHP_EOL;
$fingerprint = 'test-' . time();
$manifest = array(
	'created_pages' => array(),
	'created_template_parts' => array(),
	'created_templates' => array(),
	'updated_pages' => array(
		array(
			'post_id' => 99999, // Non-existent post (should be warning)
			'before' => array(
				'post_content' => 'Test',
				'post_title' => 'Test',
				'post_status' => 'publish',
			),
			'before_meta' => array(),
		),
	),
	'updated_template_parts' => array(),
	'updated_templates' => array(),
	'front_before' => array(),
	'active_before' => '',
);

// Save manifest
$upload_dir = wp_upload_dir();
$manifest_dir = $upload_dir['basedir'] . '/vibecode-deploy/manifests/test_project';
if ( ! is_dir( $manifest_dir ) ) {
	wp_mkdir_p( $manifest_dir );
}
file_put_contents( $manifest_dir . '/' . $fingerprint . '.json', wp_json_encode( $manifest ) );

$result = RollbackService::rollback_deploy( 'test_project', $fingerprint );

test_result(
	'rollback_deploy returns 0 errors for missing posts',
	$result['errors'] === 0,
	"Expected: 0 errors, Got: {$result['errors']}"
);

test_result(
	'rollback_deploy has warnings array',
	isset( $result['warnings'] ) && is_array( $result['warnings'] ),
	'warnings array not found'
);

test_result(
	'rollback_deploy has actual_errors array',
	isset( $result['actual_errors'] ) && is_array( $result['actual_errors'] ),
	'actual_errors array not found'
);

test_result(
	'rollback_deploy separates warnings from errors',
	! empty( $result['warnings'] ) && empty( $result['actual_errors'] ),
	'Warnings: ' . count( $result['warnings'] ) . ', Actual errors: ' . count( $result['actual_errors'] )
);

// Clean up manifest
@unlink( $manifest_dir . '/' . $fingerprint . '.json' );

// Summary
echo PHP_EOL . "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
echo "Total: " . ( $passed + $failed ) . PHP_EOL;

if ( $failed > 0 ) {
	echo PHP_EOL . "❌ Some tests failed!" . PHP_EOL;
	exit( 1 );
} else {
	echo PHP_EOL . "✅ All tests passed!" . PHP_EOL;
	exit( 0 );
}
