<?php
/**
 * Check investigation post content
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== Checking Investigation Posts ===" . PHP_EOL;
echo PHP_EOL;

$posts = get_posts(
	array(
		'post_type' => 'investigation',
		'posts_per_page' => 5,
		'post_status' => 'publish',
	)
);

if ( empty( $posts ) ) {
	echo "❌ No investigation posts found" . PHP_EOL;
	exit( 1 );
}

foreach ( $posts as $post ) {
	echo "Post ID: {$post->ID}" . PHP_EOL;
	echo "Title: {$post->post_title}" . PHP_EOL;
	echo "Content length: " . strlen( $post->post_content ) . " chars" . PHP_EOL;
	echo "Content preview: " . substr( strip_tags( $post->post_content ), 0, 100 ) . "..." . PHP_EOL;
	
	$docket = get_post_meta( $post->ID, 'cfa_investigation_docket_id', true );
	$status = get_post_meta( $post->ID, 'cfa_investigation_status', true );
	$hypothesis = get_post_meta( $post->ID, 'cfa_investigation_hypothesis', true );
	$latest_update = get_post_meta( $post->ID, 'cfa_investigation_latest_update', true );
	$visibility = get_post_meta( $post->ID, 'cfa_investigation_visibility', true );
	
	echo "  Docket ID: " . ( $docket ?: 'N/A' ) . PHP_EOL;
	echo "  Status: " . ( $status ?: 'N/A' ) . PHP_EOL;
	echo "  Visibility: " . ( $visibility ?: 'N/A' ) . PHP_EOL;
	echo "  Hypothesis: " . ( $hypothesis ? substr( $hypothesis, 0, 80 ) . '...' : 'N/A' ) . PHP_EOL;
	echo "  Latest Update: " . ( $latest_update ? substr( $latest_update, 0, 80 ) . '...' : 'N/A' ) . PHP_EOL;
	echo PHP_EOL;
}
