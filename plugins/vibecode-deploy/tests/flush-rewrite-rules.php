<?php
/**
 * Flush rewrite rules to fix 404 errors on CPT single pages
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__, 5 ) . '/wp-load.php';
}

echo "=== Flushing Rewrite Rules ===" . PHP_EOL;
echo PHP_EOL;

// Flush rewrite rules
flush_rewrite_rules( false ); // false = soft flush (faster)

echo "✅ Rewrite rules flushed" . PHP_EOL;
echo PHP_EOL;

// Verify CPTs are registered
echo "=== Verifying CPT Registration ===" . PHP_EOL;
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
	if ( post_type_exists( $cpt ) ) {
		$obj = get_post_type_object( $cpt );
		echo "✅ {$cpt}:" . PHP_EOL;
		echo "   Rewrite: " . ( $obj->rewrite ? 'yes' : 'no' ) . PHP_EOL;
		if ( $obj->rewrite ) {
			echo "   Slug: " . ( $obj->rewrite['slug'] ?? 'N/A' ) . PHP_EOL;
		}
		echo "   Has Archive: " . ( $obj->has_archive ? 'yes' : 'no' ) . PHP_EOL;
		echo PHP_EOL;
	} else {
		echo "❌ {$cpt}: Not registered" . PHP_EOL;
		echo PHP_EOL;
	}
}

// Check permalink structure
echo "=== Permalink Structure ===" . PHP_EOL;
$permalink_structure = get_option( 'permalink_structure' );
if ( empty( $permalink_structure ) ) {
	echo "⚠️  Permalink structure is not set (using default)" . PHP_EOL;
	echo "   Go to Settings → Permalinks and save to set permalink structure" . PHP_EOL;
} else {
	echo "✅ Permalink structure: {$permalink_structure}" . PHP_EOL;
}
echo PHP_EOL;

echo "=== Next Steps ===" . PHP_EOL;
echo "1. If permalink structure was not set, go to Settings → Permalinks and save" . PHP_EOL;
echo "2. Try accessing a CPT single page again" . PHP_EOL;
echo "3. If still 404, check that the CPT has 'public' => true and 'rewrite' => true" . PHP_EOL;
