<?php
/**
 * Test shortcode extraction from staging functions.php
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ThemeDeployService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/BuildService.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/Settings.php';

use VibeCode\Deploy\Services\ThemeDeployService;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

$settings = Settings::get_all();
$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? $settings['project_slug'] : 'cfa';
$active_fingerprint = BuildService::get_active_fingerprint( $project_slug );

if ( $active_fingerprint === '' ) {
	echo "❌ No active fingerprint" . PHP_EOL;
	exit( 1 );
}

$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
$staging_file = $build_root . '/theme/functions.php';

if ( ! file_exists( $staging_file ) ) {
	echo "❌ Staging functions.php not found: {$staging_file}" . PHP_EOL;
	exit( 1 );
}

$content = file_get_contents( $staging_file );

// Use reflection to access private method
$reflection = new ReflectionClass( 'VibeCode\Deploy\Services\ThemeDeployService' );
$method = $reflection->getMethod( 'extract_shortcode_registrations' );
$method->setAccessible( true );

$shortcodes = $method->invoke( null, $content );

echo "=== Shortcode Extraction Test ===" . PHP_EOL;
echo "Staging file: {$staging_file}" . PHP_EOL;
echo "File size: " . number_format( strlen( $content ) ) . " bytes" . PHP_EOL;
echo "add_shortcode occurrences: " . substr_count( $content, 'add_shortcode' ) . PHP_EOL;
echo PHP_EOL;

echo "Found " . count( $shortcodes ) . " extracted shortcodes:" . PHP_EOL;
foreach ( $shortcodes as $tag => $code ) {
	$length = strlen( $code );
	$first_line = substr( $code, 0, min( 100, $length ) );
	echo "  ✅ {$tag} (length: {$length})" . PHP_EOL;
	echo "     Preview: " . trim( preg_replace( '/\s+/', ' ', $first_line ) ) . "..." . PHP_EOL;
}
echo PHP_EOL;

if ( count( $shortcodes ) === 0 ) {
	echo "❌ No shortcodes extracted!" . PHP_EOL;
	exit( 1 );
}

echo "✅ Shortcode extraction is working" . PHP_EOL;
