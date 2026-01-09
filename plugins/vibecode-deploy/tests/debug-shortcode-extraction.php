<?php
/**
 * Debug shortcode extraction step by step
 */

$staging_file = '/Users/tlowing/CascadeProjects/windsurf-project/CFA/vibecode-deploy-staging/theme/functions.php';

if ( ! file_exists( $staging_file ) ) {
	echo "❌ File not found: {$staging_file}" . PHP_EOL;
	exit( 1 );
}

$content = file_get_contents( $staging_file );
$pos = 0;
$found_count = 0;

echo "=== Debugging Shortcode Extraction ===" . PHP_EOL;
echo "File size: " . number_format( strlen( $content ) ) . " bytes" . PHP_EOL;
echo "add_shortcode occurrences: " . substr_count( $content, 'add_shortcode' ) . PHP_EOL;
echo PHP_EOL;

while ( ( $add_pos = strpos( $content, 'add_shortcode', $pos ) ) !== false && $found_count < 2 ) {
	echo "--- Match " . ( $found_count + 1 ) . " ---" . PHP_EOL;
	echo "add_shortcode found at position: {$add_pos}" . PHP_EOL;
	
	// Find opening parenthesis
	$open_paren = strpos( $content, '(', $add_pos );
	if ( $open_paren === false ) {
		echo "  ❌ No opening parenthesis found" . PHP_EOL;
		break;
	}
	echo "  ✅ Opening parenthesis at: {$open_paren}" . PHP_EOL;
	
	// Find tag (first quoted string)
	if ( ! preg_match( '/[\'"]([^\'"]+)[\'"]/', $content, $tag_match, PREG_OFFSET_CAPTURE, $open_paren ) ) {
		echo "  ❌ No tag found" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	$tag = $tag_match[1][0];
	$tag_end_pos = $tag_match[0][1] + strlen( $tag_match[0][0] );
	echo "  ✅ Tag found: {$tag} (ends at position: {$tag_end_pos})" . PHP_EOL;
	
	// Find comma after tag
	$comma_pos = strpos( $content, ',', $tag_end_pos );
	if ( $comma_pos === false ) {
		echo "  ❌ No comma found after tag" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Comma found at: {$comma_pos}" . PHP_EOL;
	
	// Show context around comma
	$context_start = max( 0, $comma_pos - 20 );
	$context_end = min( strlen( $content ), $comma_pos + 100 );
	$context = substr( $content, $context_start, $context_end - $context_start );
	echo "  Context: " . trim( preg_replace( '/\s+/', ' ', $context ) ) . PHP_EOL;
	
	// Find function keyword
	$func_pos = strpos( $content, 'function', $comma_pos );
	if ( $func_pos === false ) {
		echo "  ❌ No 'function' keyword found after comma" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Function keyword found at: {$func_pos}" . PHP_EOL;
	
	// Find opening brace of function
	$brace_start = strpos( $content, '{', $func_pos );
	if ( $brace_start === false ) {
		echo "  ❌ No opening brace found" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Opening brace at: {$brace_start}" . PHP_EOL;
	
	// Balance braces to find closing brace
	$brace_count = 1;
	$search_pos = $brace_start + 1;
	$brace_end = false;
	$max_search = min( strlen( $content ), $brace_start + 5000 ); // Limit search
	
	while ( $search_pos < $max_search && $brace_count > 0 ) {
		$char = $content[ $search_pos ];
		if ( $char === '{' ) {
			$brace_count++;
		} elseif ( $char === '}' ) {
			$brace_count--;
			if ( $brace_count === 0 ) {
				$brace_end = $search_pos;
				break;
			}
		}
		$search_pos++;
	}
	
	if ( $brace_end === false ) {
		echo "  ❌ No closing brace found (searched " . ( $max_search - $brace_start ) . " chars)" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Closing brace at: {$brace_end}" . PHP_EOL;
	
	// Find closing parenthesis and semicolon
	$close_paren = strpos( $content, ')', $brace_end );
	if ( $close_paren === false ) {
		echo "  ❌ No closing parenthesis found" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Closing parenthesis at: {$close_paren}" . PHP_EOL;
	
	$semicolon = strpos( $content, ';', $close_paren );
	if ( $semicolon === false ) {
		echo "  ❌ No semicolon found" . PHP_EOL;
		$pos = $add_pos + 1;
		continue;
	}
	echo "  ✅ Semicolon at: {$semicolon}" . PHP_EOL;
	
	// Extract full block
	$full_block = substr( $content, $add_pos, $semicolon - $add_pos + 1 );
	echo "  ✅ Extracted block length: " . strlen( $full_block ) . PHP_EOL;
	
	$found_count++;
	$pos = $semicolon + 1;
}

echo PHP_EOL . "=== Summary ===" . PHP_EOL;
echo "Successfully extracted: {$found_count} shortcodes" . PHP_EOL;
