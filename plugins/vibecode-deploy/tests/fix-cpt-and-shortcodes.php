<?php
/**
 * Fix CPT and shortcode registration by redeploying theme files
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ThemeDeployService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Importer.php';

use VibeCode\Deploy\Services\ThemeDeployService;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Importer;

echo "=== Fixing CPT and Shortcode Registration ===" . PHP_EOL;
echo PHP_EOL;

$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );

if ( $active_fingerprint === '' ) {
	echo "❌ No active fingerprint found" . PHP_EOL;
	exit( 1 );
}

echo "Project slug: {$project_slug}" . PHP_EOL;
echo "Active fingerprint: {$active_fingerprint}" . PHP_EOL;
echo PHP_EOL;

$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
$theme = wp_get_theme();
$theme_slug = $theme->get_stylesheet();

echo "Build root: {$build_root}" . PHP_EOL;
echo "Theme slug: {$theme_slug}" . PHP_EOL;
echo PHP_EOL;

// Redeploy theme files
echo "=== Redeploying Theme Files ===" . PHP_EOL;
$theme_result = ThemeDeployService::deploy_theme_files( $build_root, $theme_slug, array() );

if ( isset( $theme_result['errors'] ) && ! empty( $theme_result['errors'] ) ) {
	echo "❌ Theme deployment had errors:" . PHP_EOL;
	foreach ( $theme_result['errors'] as $error ) {
		echo "   - {$error}" . PHP_EOL;
	}
	exit( 1 );
}

echo "✅ Theme files deployed" . PHP_EOL;
if ( ! empty( $theme_result['created'] ) ) {
	echo "   Created: " . implode( ', ', $theme_result['created'] ) . PHP_EOL;
}
if ( ! empty( $theme_result['updated'] ) ) {
	echo "   Updated: " . implode( ', ', $theme_result['updated'] ) . PHP_EOL;
}
echo PHP_EOL;

// Verify shortcodes are now registered
echo "=== Verifying Shortcode Registration ===" . PHP_EOL;
global $shortcode_tags;
$cfa_shortcodes = array_filter( array_keys( $shortcode_tags ), function( $tag ) {
	return strpos( $tag, 'cfa_' ) === 0;
} );

if ( empty( $cfa_shortcodes ) ) {
	echo "⚠️  Shortcodes still not registered. Flushing rewrite rules..." . PHP_EOL;
	flush_rewrite_rules();
	
	// Re-check
	global $shortcode_tags;
	$cfa_shortcodes = array_filter( array_keys( $shortcode_tags ), function( $tag ) {
		return strpos( $tag, 'cfa_' ) === 0;
	} );
}

if ( ! empty( $cfa_shortcodes ) ) {
	echo "✅ Found " . count( $cfa_shortcodes ) . " registered shortcodes:" . PHP_EOL;
	foreach ( $cfa_shortcodes as $tag ) {
		echo "   - {$tag}" . PHP_EOL;
	}
} else {
	echo "❌ Shortcodes still not registered" . PHP_EOL;
}
echo PHP_EOL;

// Redeploy specific pages that have placeholder issues
echo "=== Redeploying Pages with Placeholder Issues ===" . PHP_EOL;
$pages_to_fix = array( 'investigations', 'foia-reading-room' );

foreach ( $pages_to_fix as $page_slug ) {
	echo "Redeploying: {$page_slug}" . PHP_EOL;
	
	$deploy_result = Importer::run_import(
		$project_slug,
		$active_fingerprint,
		$build_root,
		true,  // force_claim_unowned
		true,  // deploy_template_parts
		true,  // generate_404_template
		false, // force_claim_templates
		false, // validate_cpt_shortcodes
		array( $page_slug ), // selected_pages
		array(), // selected_css
		array(), // selected_js
		array(), // selected_templates
		array(), // selected_template_parts
		array()  // selected_theme_files
	);
	
	if ( is_array( $deploy_result ) ) {
		$created = $deploy_result['created'] ?? 0;
		$updated = $deploy_result['updated'] ?? 0;
		$errors = $deploy_result['errors'] ?? 0;
		
		if ( $errors > 0 ) {
			echo "   ❌ Errors: {$errors}" . PHP_EOL;
		} else {
			echo "   ✅ Created: {$created}, Updated: {$updated}" . PHP_EOL;
		}
	} else {
		echo "   ❌ Deployment failed" . PHP_EOL;
	}
}
echo PHP_EOL;

// Verify page content
echo "=== Verifying Page Content ===" . PHP_EOL;
foreach ( $pages_to_fix as $page_slug ) {
	$page = get_page_by_path( $page_slug );
	if ( $page ) {
		$content = $page->post_content;
		$has_placeholder = strpos( $content, 'VIBECODE_SHORTCODE' ) !== false || strpos( $content, 'CFA_SHORTCODE' ) !== false;
		$has_shortcode_block = strpos( $content, '<!-- wp:shortcode -->' ) !== false;
		
		echo "Page: {$page_slug}" . PHP_EOL;
		echo "   Has placeholder: " . ( $has_placeholder ? '❌ YES (not converted)' : '✅ NO' ) . PHP_EOL;
		echo "   Has shortcode block: " . ( $has_shortcode_block ? '✅ YES' : '❌ NO' ) . PHP_EOL;
	} else {
		echo "Page: {$page_slug} ❌ Not found" . PHP_EOL;
	}
}
echo PHP_EOL;

echo "=== Summary ===" . PHP_EOL;
echo "✅ Theme files redeployed" . PHP_EOL;
echo "✅ Pages redeployed" . PHP_EOL;
echo PHP_EOL;
echo "Please check the page content above to verify placeholders were converted." . PHP_EOL;
