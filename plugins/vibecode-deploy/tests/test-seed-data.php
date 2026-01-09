<?php
/**
 * Test the test data seeder
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

use VibeCode\Deploy\Services\TestDataService;

echo "=== Testing Test Data Seeder ===" . PHP_EOL;
echo PHP_EOL;

// Seed only CPTs that don't have posts
$cpts_to_seed = array( 'advisory', 'evidence_record', 'survey' );

echo "Seeding CPTs: " . implode( ', ', $cpts_to_seed ) . PHP_EOL;
echo PHP_EOL;

$results = TestDataService::seed_test_data( $cpts_to_seed );

echo "=== Results ===" . PHP_EOL;
echo PHP_EOL;

if ( ! empty( $results['created'] ) ) {
	echo "✅ Created posts:" . PHP_EOL;
	foreach ( $results['created'] as $cpt => $post_ids ) {
		echo "  {$cpt}: " . count( $post_ids ) . " posts (IDs: " . implode( ', ', $post_ids ) . ")" . PHP_EOL;
	}
	echo PHP_EOL;
}

if ( ! empty( $results['skipped'] ) ) {
	echo "⚠️  Skipped:" . PHP_EOL;
	foreach ( $results['skipped'] as $skip ) {
		echo "  {$skip['cpt']}: {$skip['reason']}" . PHP_EOL;
	}
	echo PHP_EOL;
}

if ( ! empty( $results['errors'] ) ) {
	echo "❌ Errors:" . PHP_EOL;
	foreach ( $results['errors'] as $error ) {
		echo "  {$error['cpt']}: {$error['error']}" . PHP_EOL;
	}
	echo PHP_EOL;
}

echo "=== Verification ===" . PHP_EOL;
echo PHP_EOL;

$cpts = array( 'advisory', 'investigation', 'evidence_record', 'foia_request', 'foia_update', 'survey' );
foreach ( $cpts as $cpt ) {
	if ( post_type_exists( $cpt ) ) {
		$counts = wp_count_posts( $cpt );
		$published = (int) $counts->publish;
		echo "{$cpt}: {$published} published posts" . PHP_EOL;
	} else {
		echo "{$cpt}: CPT not registered" . PHP_EOL;
	}
}
