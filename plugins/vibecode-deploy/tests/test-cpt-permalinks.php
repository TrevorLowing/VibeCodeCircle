<?php
/**
 * Test CPT permalinks to diagnose 404 errors
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== Testing CPT Permalinks ===" . PHP_EOL;
echo PHP_EOL;

$cpts = array(
	'advisory',
	'investigation',
	'evidence_record',
	'foia_request',
);

foreach ( $cpts as $cpt ) {
	if ( ! post_type_exists( $cpt ) ) {
		echo "❌ {$cpt}: CPT not registered" . PHP_EOL;
		continue;
	}

	$obj = get_post_type_object( $cpt );
	echo "{$cpt}:" . PHP_EOL;
	echo "  Public: " . ( $obj->public ? 'yes' : 'no' ) . PHP_EOL;
	echo "  Publicly Queryable: " . ( $obj->publicly_queryable ? 'yes' : 'no' ) . PHP_EOL;
	echo "  Rewrite: " . ( $obj->rewrite ? 'yes' : 'no' ) . PHP_EOL;
	if ( $obj->rewrite ) {
		echo "  Slug: " . ( $obj->rewrite['slug'] ?? 'N/A' ) . PHP_EOL;
		echo "  With Front: " . ( $obj->rewrite['with_front'] ?? 'N/A' ) . PHP_EOL;
	}
	echo "  Has Archive: " . ( $obj->has_archive ? 'yes' : 'no' ) . PHP_EOL;
	echo PHP_EOL;

	// Get a sample post
	$posts = get_posts(
		array(
			'post_type' => $cpt,
			'posts_per_page' => 1,
			'post_status' => 'publish',
		)
	);

	if ( ! empty( $posts ) ) {
		$post = $posts[0];
		$permalink = get_permalink( $post->ID );
		echo "  Sample Post:" . PHP_EOL;
		echo "    ID: {$post->ID}" . PHP_EOL;
		echo "    Slug: {$post->post_name}" . PHP_EOL;
		echo "    Permalink: {$permalink}" . PHP_EOL;
		echo PHP_EOL;
	} else {
		echo "  ⚠️  No published posts found" . PHP_EOL;
		echo PHP_EOL;
	}
}

// Check rewrite rules
echo "=== Checking Rewrite Rules ===" . PHP_EOL;
global $wp_rewrite;
$rules = get_option( 'rewrite_rules' );

$found_rules = array();
foreach ( $cpts as $cpt ) {
	if ( ! post_type_exists( $cpt ) ) {
		continue;
	}
	$obj = get_post_type_object( $cpt );
	if ( $obj->rewrite ) {
		$slug = $obj->rewrite['slug'] ?? $cpt;
		// Look for rewrite rules matching this CPT
		$pattern = '/' . preg_quote( $slug, '/' ) . '/';
		$matching_rules = array_filter(
			array_keys( $rules ?? array() ),
			function( $rule ) use ( $pattern ) {
				return preg_match( $pattern, $rule );
			}
		);
		if ( ! empty( $matching_rules ) ) {
			$found_rules[ $cpt ] = count( $matching_rules );
		} else {
			$found_rules[ $cpt ] = 0;
		}
	}
}

foreach ( $found_rules as $cpt => $count ) {
	if ( $count > 0 ) {
		echo "✅ {$cpt}: {$count} rewrite rules found" . PHP_EOL;
	} else {
		echo "❌ {$cpt}: No rewrite rules found (needs flush)" . PHP_EOL;
	}
}

echo PHP_EOL;
echo "=== Recommendation ===" . PHP_EOL;
if ( array_sum( $found_rules ) === 0 ) {
	echo "⚠️  No rewrite rules found. Run flush-rewrite-rules.php to fix." . PHP_EOL;
} else {
	echo "✅ Rewrite rules appear to be set correctly." . PHP_EOL;
	echo "   If you're still getting 404s, try:" . PHP_EOL;
	echo "   1. Go to Settings → Permalinks and click 'Save Changes'" . PHP_EOL;
	echo "   2. Clear any caching plugins" . PHP_EOL;
	echo "   3. Check .htaccess file permissions" . PHP_EOL;
}
