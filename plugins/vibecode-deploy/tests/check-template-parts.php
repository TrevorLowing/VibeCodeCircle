<?php
/**
 * Check template parts status
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Importer;

echo "=== Template Parts Diagnostic ===" . PHP_EOL;
echo PHP_EOL;

// Check existing template parts
$parts = get_posts( array(
	'post_type' => 'wp_template_part',
	'posts_per_page' => -1,
	'post_status' => 'any',
) );

echo "Existing Template Parts: " . count( $parts ) . PHP_EOL;
foreach ( $parts as $part ) {
	$project_slug = get_post_meta( $part->ID, Importer::META_PROJECT_SLUG, true );
	echo "  - {$part->post_name} (ID: {$part->ID}, Status: {$part->post_status}, Project: {$project_slug})" . PHP_EOL;
}
echo PHP_EOL;

// Check pages/templates referencing template parts
echo "Checking pages for template-part references..." . PHP_EOL;
$pages = get_posts( array(
	'post_type' => 'page',
	'posts_per_page' => -1,
	'post_status' => 'any',
) );

$referenced_parts = array();
foreach ( $pages as $page ) {
	$content = $page->post_content;
	if ( strpos( $content, 'template-part' ) !== false ) {
		preg_match_all( '/slug=["\']([^"\']+)["\']/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $slug ) {
				if ( ! isset( $referenced_parts[ $slug ] ) ) {
					$referenced_parts[ $slug ] = array();
				}
				$referenced_parts[ $slug ][] = $page->post_name;
			}
		}
	}
}

// Check templates
$templates = get_posts( array(
	'post_type' => 'wp_template',
	'posts_per_page' => -1,
	'post_status' => 'any',
) );

foreach ( $templates as $template ) {
	$content = $template->post_content;
	if ( strpos( $content, 'template-part' ) !== false ) {
		preg_match_all( '/slug=["\']([^"\']+)["\']/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $slug ) {
				if ( ! isset( $referenced_parts[ $slug ] ) ) {
					$referenced_parts[ $slug ] = array();
				}
				$referenced_parts[ $slug ][] = 'template:' . $template->post_name;
			}
		}
	}
}

echo "Referenced Template Parts:" . PHP_EOL;
foreach ( $referenced_parts as $slug => $references ) {
	$exists = false;
	foreach ( $parts as $part ) {
		if ( $part->post_name === $slug ) {
			$exists = true;
			break;
		}
	}
	$status = $exists ? '✅ EXISTS' : '❌ MISSING';
	echo "  {$status}: {$slug}" . PHP_EOL;
	echo "    Referenced in: " . implode( ', ', $references ) . PHP_EOL;
}
echo PHP_EOL;

// Check staging zip for template-parts
echo "Checking staging zip for template-parts..." . PHP_EOL;
$upload_dir = wp_upload_dir();
$uploads_base = $upload_dir['basedir'];
$staging_dirs = glob( $uploads_base . '/vibecode-deploy/staging/*/*' );
if ( ! empty( $staging_dirs ) ) {
	$latest_staging = array_pop( $staging_dirs );
	$template_parts_dir = $latest_staging . '/template-parts';
	if ( is_dir( $template_parts_dir ) ) {
		$files = glob( $template_parts_dir . '/*.html' );
		echo "  Found " . count( $files ) . " template part files in staging:" . PHP_EOL;
		foreach ( $files as $file ) {
			$slug = basename( $file, '.html' );
			echo "    - {$slug}" . PHP_EOL;
		}
	} else {
		echo "  ❌ No template-parts directory in staging" . PHP_EOL;
	}
} else {
	echo "  ⚠️  No staging directories found" . PHP_EOL;
}

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
$missing = array();
foreach ( $referenced_parts as $slug => $references ) {
	$exists = false;
	foreach ( $parts as $part ) {
		if ( $part->post_name === $slug ) {
			$exists = true;
			break;
		}
	}
	if ( ! $exists ) {
		$missing[] = $slug;
	}
}

if ( empty( $missing ) ) {
	echo "✅ All referenced template parts exist" . PHP_EOL;
} else {
	echo "❌ Missing template parts: " . implode( ', ', $missing ) . PHP_EOL;
	echo PHP_EOL;
	echo "To fix: Deploy with 'deploy_template_parts' enabled, or create these template parts manually." . PHP_EOL;
}
