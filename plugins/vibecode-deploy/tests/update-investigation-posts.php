<?php
/**
 * Update existing investigation posts with proper content
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== Updating Investigation Posts ===" . PHP_EOL;
echo PHP_EOL;

$posts = get_posts(
	array(
		'post_type' => 'investigation',
		'posts_per_page' => -1,
		'post_status' => 'any',
	)
);

if ( empty( $posts ) ) {
	echo "❌ No investigation posts found" . PHP_EOL;
	exit( 1 );
}

$updates = array(
	'25-001' => array(
		'hypothesis' => 'Federal agencies are not adequately overseeing contractor performance and compliance.',
		'methodology' => '<p>This investigation analyzes procurement data from multiple federal agencies to identify patterns in contractor oversight. We are reviewing contract award documentation, performance evaluations, and compliance reports to assess the effectiveness of oversight mechanisms.</p><p>Our methodology includes FOIA requests for contract files, analysis of publicly available procurement databases, and interviews with agency procurement officials where possible.</p>',
		'latest_update' => 'Initial data collection phase completed. Reviewing contract files from Department of Commerce and Department of Defense.',
	),
	'25-002' => array(
		'hypothesis' => 'Agencies are not consistently following data retention policies, leading to premature data deletion.',
		'methodology' => '<p>This investigation examines agency compliance with federal records management requirements. We are reviewing data retention schedules, records disposition schedules, and actual data deletion practices across multiple agencies.</p><p>Our approach includes document analysis, interviews with records management officers, and review of FOIA request responses to identify instances where requested records were deleted prematurely.</p>',
		'latest_update' => 'Analysis phase in progress. Reviewing records management policies from 15 federal agencies.',
	),
	'25-003' => array(
		'hypothesis' => 'FOIA request processing delays are preventing timely public access to government information.',
		'methodology' => '<p>This investigation reviews FOIA request processing times across multiple federal agencies. We are analyzing request logs, response times, and appeal rates to identify systemic delays.</p><p>Our methodology includes statistical analysis of FOIA request data, interviews with FOIA officers, and review of agency FOIA reports to Congress.</p>',
		'latest_update' => 'FOIA requests submitted to 10 agencies. Awaiting responses to assess processing times.',
	),
);

$updated = 0;
foreach ( $posts as $post ) {
	$docket = get_post_meta( $post->ID, 'cfa_investigation_docket_id', true );
	
	if ( ! $docket || ! isset( $updates[ $docket ] ) ) {
		echo "⚠️  Skipping post {$post->ID}: Docket ID '{$docket}' not found in updates" . PHP_EOL;
		continue;
	}
	
	$update_data = $updates[ $docket ];
	
	// Update post content if it's the placeholder text
	if ( strpos( $post->post_content, 'Seeded investigation content' ) !== false || strlen( $post->post_content ) < 100 ) {
		wp_update_post(
			array(
				'ID' => $post->ID,
				'post_content' => $update_data['methodology'],
			)
		);
		echo "✅ Updated post {$post->ID} ({$post->post_title}) content" . PHP_EOL;
	}
	
	// Update hypothesis if missing
	$current_hypothesis = get_post_meta( $post->ID, 'cfa_investigation_hypothesis', true );
	if ( empty( $current_hypothesis ) ) {
		update_post_meta( $post->ID, 'cfa_investigation_hypothesis', $update_data['hypothesis'] );
		echo "✅ Updated post {$post->ID} hypothesis" . PHP_EOL;
	}
	
	// Update latest_update if missing
	$current_update = get_post_meta( $post->ID, 'cfa_investigation_latest_update', true );
	if ( empty( $current_update ) ) {
		update_post_meta( $post->ID, 'cfa_investigation_latest_update', $update_data['latest_update'] );
		echo "✅ Updated post {$post->ID} latest_update" . PHP_EOL;
	}
	
	$updated++;
}

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Updated {$updated} investigation posts" . PHP_EOL;
echo PHP_EOL;
echo "Posts should now display properly on the investigations page." . PHP_EOL;
