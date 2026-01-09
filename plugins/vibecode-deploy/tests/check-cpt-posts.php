<?php
/**
 * Check CPT posts count
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== CPT Post Counts ===" . PHP_EOL;
echo PHP_EOL;

$cpts = array(
	'advisory',
	'investigation',
	'evidence_record',
	'foia_request',
	'foia_update',
	'survey',
);

foreach ( $cpts as $cpt ) {
	if ( ! post_type_exists( $cpt ) ) {
		echo "❌ {$cpt}: CPT not registered" . PHP_EOL;
		continue;
	}

	$counts = wp_count_posts( $cpt );
	$total = (int) $counts->publish + (int) $counts->draft + (int) $counts->pending + (int) $counts->trash;
	$published = (int) $counts->publish;

	echo "{$cpt}:" . PHP_EOL;
	echo "  Total posts: {$total}" . PHP_EOL;
	echo "  Published: {$published}" . PHP_EOL;
	echo "  Draft: " . (int) $counts->draft . PHP_EOL;
	echo PHP_EOL;
}

echo "=== Summary ===" . PHP_EOL;
$all_have_posts = true;
foreach ( $cpts as $cpt ) {
	if ( post_type_exists( $cpt ) ) {
		$counts = wp_count_posts( $cpt );
		$published = (int) $counts->publish;
		if ( $published === 0 ) {
			echo "⚠️  {$cpt} has no published posts" . PHP_EOL;
			$all_have_posts = false;
		}
	}
}

if ( $all_have_posts ) {
	echo "✅ All CPTs have published posts" . PHP_EOL;
} else {
	echo "❌ Some CPTs are missing published posts" . PHP_EOL;
}
