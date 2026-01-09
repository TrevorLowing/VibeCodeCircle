<?php
/**
 * Test shortcode extraction from staging functions.php
 */

require_once dirname( dirname( __FILE__ ) ) . '/includes/Services/ThemeDeployService.php';

use VibeCode\Deploy\Services\ThemeDeployService;

$staging_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/CFA/vibecode-deploy-staging/theme/functions.php';

if ( ! file_exists( $staging_file ) ) {
	echo "❌ Staging functions.php not found" . PHP_EOL;
	exit( 1 );
}

$content = file_get_contents( $staging_file );

// Use reflection to access private method
$reflection = new ReflectionClass( 'VibeCode\Deploy\Services\ThemeDeployService' );
$method = $reflection->getMethod( 'extract_shortcode_registrations' );
$method->setAccessible( true );

$shortcodes = $method->invoke( null, $content );

echo "=== Shortcode Extraction Test ===" . PHP_EOL;
echo "Found " . count( $shortcodes ) . " shortcodes:" . PHP_EOL;
foreach ( $shortcodes as $tag => $code ) {
	$length = strlen( $code );
	$first_line = substr( $code, 0, min( 80, $length ) );
	echo "  ✅ {$tag} (length: {$length})" . PHP_EOL;
	echo "     Preview: " . trim( $first_line ) . "..." . PHP_EOL;
}
echo PHP_EOL;

if ( count( $shortcodes ) === 0 ) {
	echo "❌ No shortcodes extracted!" . PHP_EOL;
	exit( 1 );
}

echo "✅ Shortcode extraction is working" . PHP_EOL;
