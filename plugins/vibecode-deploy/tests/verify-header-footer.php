<?php
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Importer;

$parts = get_posts( array(
	'post_type' => 'wp_template_part',
	'post_name__in' => array( 'header', 'footer' ),
	'posts_per_page' => -1,
	'post_status' => 'any',
) );

echo "Template parts with slugs 'header' or 'footer': " . count( $parts ) . PHP_EOL;
foreach ( $parts as $p ) {
	$project = get_post_meta( $p->ID, Importer::META_PROJECT_SLUG, true );
	echo "  - {$p->post_name} (ID: {$p->ID}, Status: {$p->post_status}, Project: {$project})" . PHP_EOL;
}

// Also check templates that reference header/footer
$templates = get_posts( array(
	'post_type' => 'wp_template',
	'posts_per_page' => -1,
	'post_status' => 'any',
) );

echo PHP_EOL . "Templates referencing header/footer:" . PHP_EOL;
foreach ( $templates as $t ) {
	$content = $t->post_content;
	if ( strpos( $content, 'template-part' ) !== false && ( strpos( $content, 'slug="header"' ) !== false || strpos( $content, 'slug="footer"' ) !== false ) ) {
		echo "  - {$t->post_name}" . PHP_EOL;
		preg_match_all( '/slug=["\']([^"\']+)["\']/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $slug ) {
				$exists = false;
				foreach ( $parts as $p ) {
					if ( $p->post_name === $slug ) {
						$exists = true;
						break;
					}
				}
				$status = $exists ? '✅' : '❌';
				echo "    {$status} {$slug}" . PHP_EOL;
			}
		}
	}
}
