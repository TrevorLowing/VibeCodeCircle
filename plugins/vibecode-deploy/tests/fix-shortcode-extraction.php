<?php
/**
 * Test shortcode extraction from staging functions.php
 */

$staging_file = '/Users/tlowing/CascadeProjects/windsurf-project/CFA/vibecode-deploy-staging/theme/functions.php';

if ( ! file_exists( $staging_file ) ) {
	echo "❌ Staging functions.php not found: {$staging_file}" . PHP_EOL;
	exit( 1 );
}

$content = file_get_contents( $staging_file );
echo "=== Testing Shortcode Extraction ===" . PHP_EOL;
echo "File size: " . number_format( strlen( $content ) ) . " bytes" . PHP_EOL;
echo PHP_EOL;

// Count add_shortcode occurrences
$shortcode_count = substr_count( $content, 'add_shortcode' );
echo "Found {$shortcode_count} 'add_shortcode' occurrences" . PHP_EOL;
echo PHP_EOL;

// Try current pattern
echo "=== Current Pattern Test ===" . PHP_EOL;
$pattern = '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:function\s*\([^)]*\)\s*\{[^}]*\}|\$[a-zA-Z_][a-zA-Z0-9_]*|array\s*\([^)]*\))\s*\)\s*;/s';
preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
echo "Matches found: " . count( $matches ) . PHP_EOL;
foreach ( $matches as $i => $match ) {
	echo "  Match " . ( $i + 1 ) . ": " . $match[1][0] . " (length: " . strlen( $match[0][0] ) . ")" . PHP_EOL;
}
echo PHP_EOL;

// Try improved pattern with non-greedy match
echo "=== Improved Pattern Test (non-greedy) ===" . PHP_EOL;
$pattern2 = '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(function\s*\([^)]*\)\s*\{.*?\})\s*\)\s*;/s';
preg_match_all( $pattern2, $content, $matches2, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
echo "Matches found: " . count( $matches2 ) . PHP_EOL;
foreach ( $matches2 as $i => $match ) {
	echo "  Match " . ( $i + 1 ) . ": " . $match[1][0] . " (length: " . strlen( $match[0][0] ) . ")" . PHP_EOL;
}
echo PHP_EOL;

// Try balanced brace matching approach
echo "=== Balanced Brace Matching Test ===" . PHP_EOL;
function extract_shortcode_with_balanced_braces( $content, $start_pos ) {
	// Find add_shortcode(
	$add_pos = strpos( $content, 'add_shortcode', $start_pos );
	if ( $add_pos === false ) {
		return null;
	}
	
	// Find opening parenthesis
	$open_paren = strpos( $content, '(', $add_pos );
	if ( $open_paren === false ) {
		return null;
	}
	
	// Find tag (first quoted string)
	if ( ! preg_match( '/[\'"]([^\'"]+)[\'"]/', $content, $tag_match, 0, $open_paren ) ) {
		return null;
	}
	$tag = $tag_match[1];
	
	// Find comma after tag
	$comma_pos = strpos( $content, ',', $tag_match[0][1] + strlen( $tag_match[0] ) );
	if ( $comma_pos === false ) {
		return null;
	}
	
	// Find function keyword
	$func_pos = strpos( $content, 'function', $comma_pos );
	if ( $func_pos === false ) {
		return null;
	}
	
	// Find opening brace of function
	$brace_start = strpos( $content, '{', $func_pos );
	if ( $brace_start === false ) {
		return null;
	}
	
	// Balance braces to find closing brace
	$brace_count = 1;
	$pos = $brace_start + 1;
	$brace_end = false;
	
	while ( $pos < strlen( $content ) && $brace_count > 0 ) {
		$char = $content[ $pos ];
		if ( $char === '{' ) {
			$brace_count++;
		} elseif ( $char === '}' ) {
			$brace_count--;
			if ( $brace_count === 0 ) {
				$brace_end = $pos;
				break;
			}
		}
		$pos++;
	}
	
	if ( $brace_end === false ) {
		return null;
	}
	
	// Find closing parenthesis and semicolon
	$close_paren = strpos( $content, ')', $brace_end );
	if ( $close_paren === false ) {
		return null;
	}
	$semicolon = strpos( $content, ';', $close_paren );
	if ( $semicolon === false ) {
		return null;
	}
	
	$full_block = substr( $content, $add_pos, $semicolon - $add_pos + 1 );
	
	return array(
		'tag' => $tag,
		'start' => $add_pos,
		'end' => $semicolon + 1,
		'block' => $full_block,
	);
}

$pos = 0;
$extracted = array();
while ( ( $result = extract_shortcode_with_balanced_braces( $content, $pos ) ) !== null ) {
	$extracted[] = $result;
	$pos = $result['end'];
}

echo "Extracted shortcodes: " . count( $extracted ) . PHP_EOL;
foreach ( $extracted as $i => $ext ) {
	echo "  " . ( $i + 1 ) . ": {$ext['tag']} (length: " . strlen( $ext['block'] ) . ")" . PHP_EOL;
}
