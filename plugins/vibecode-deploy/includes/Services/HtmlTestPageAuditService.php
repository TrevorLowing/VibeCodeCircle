<?php
/**
 * HTML Test Page Audit Service
 *
 * Analyzes HTML test pages and generates compliance audit reports
 * for block conversion accuracy, etchData compliance, and EtchWP IDE editability.
 *
 * @package VibeCode\Deploy
 */

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * HTML Test Page Audit Service
 */
final class HtmlTestPageAuditService {

	/**
	 * Analyze test page and generate audit report.
	 *
	 * @param string $html_content HTML content to analyze.
	 * @param string $source Source type ('html_file' or 'wordpress_page').
	 * @return array Audit results with report data.
	 */
	public static function analyze_test_page( string $html_content, string $source = 'html_file' ): array {
		// Parse HTML to identify all elements
		$html_elements = self::parse_html_elements( $html_content );
		
		// Convert to blocks using Importer
		$block_content = Importer::html_to_etch_blocks( $html_content );
		
		// Parse blocks
		$blocks = self::parse_blocks( $block_content );
		
		// Analyze each element
		$analysis = self::analyze_elements( $html_elements, $blocks );
		
		// Calculate compliance scores
		$scores = self::calculate_compliance_scores( $analysis );
		
		// Categorize issues
		$issues = self::categorize_issues( $analysis );
		
		return array(
			'source' => $source,
			'timestamp' => current_time( 'mysql' ),
			'plugin_version' => defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION : 'unknown',
			'html_elements' => $html_elements,
			'blocks' => $blocks,
			'analysis' => $analysis,
			'scores' => $scores,
			'issues' => $issues,
		);
	}

	/**
	 * Analyze WordPress page blocks.
	 *
	 * @param int $page_id WordPress page ID.
	 * @return array Audit results.
	 */
	public static function analyze_wordpress_page( int $page_id ): array {
		$page = get_post( $page_id );
		if ( ! $page ) {
			return array(
				'error' => 'Page not found',
			);
		}
		
		// Get block content
		$block_content = $page->post_content;
		
		// Parse blocks using WordPress function if available
		if ( function_exists( 'parse_blocks' ) ) {
			$wp_blocks = parse_blocks( $block_content );
		} else {
			$wp_blocks = self::parse_blocks( $block_content );
		}
		
		// Analyze blocks
		$analysis = self::analyze_wordpress_blocks( $wp_blocks );
		
		// Calculate compliance scores
		$scores = self::calculate_compliance_scores_from_blocks( $analysis );
		
		// Categorize issues
		$issues = self::categorize_issues_from_blocks( $analysis );
		
		return array(
			'source' => 'wordpress_page',
			'page_id' => $page_id,
			'page_title' => $page->post_title,
			'page_url' => get_permalink( $page_id ),
			'timestamp' => current_time( 'mysql' ),
			'plugin_version' => defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION : 'unknown',
			'blocks' => $wp_blocks,
			'analysis' => $analysis,
			'scores' => $scores,
			'issues' => $issues,
		);
	}

	/**
	 * Generate markdown report from audit results.
	 *
	 * @param array $audit_results Audit results from analyze_test_page() or analyze_wordpress_page().
	 * @return string Markdown report content.
	 */
	public static function generate_markdown_report( array $audit_results ): string {
		if ( isset( $audit_results['error'] ) ) {
			return '# Audit Report Error\n\n' . $audit_results['error'];
		}
		
		$report = "# HTML Test Page Audit Report\n\n";
		$report .= "**Generated:** " . ( $audit_results['timestamp'] ?? current_time( 'mysql' ) ) . "\n";
		$report .= "**Source:** " . ( $audit_results['source'] ?? 'unknown' ) . "\n";
		$report .= "**Plugin Version:** " . ( $audit_results['plugin_version'] ?? 'unknown' ) . "\n";
		
		if ( isset( $audit_results['page_title'] ) ) {
			$report .= "**Page:** " . esc_html( $audit_results['page_title'] ) . " (ID: " . $audit_results['page_id'] . ")\n";
			$report .= "**Page URL:** " . esc_url( $audit_results['page_url'] ?? '' ) . "\n";
		}
		
		$report .= "\n---\n\n";
		
		// Executive Summary
		$scores = $audit_results['scores'] ?? array();
		$report .= "## Executive Summary\n\n";
		$report .= "- **Total Elements Analyzed:** " . ( $scores['total'] ?? 0 ) . "\n";
		$report .= "- **Conversion Success Rate:** " . ( $scores['conversion_success_rate'] ?? 0 ) . "%\n";
		$report .= "- **Compliance Score:** " . ( $scores['overall_score'] ?? 0 ) . "%\n";
		
		$overall_status = '✅ Pass';
		if ( ( $scores['overall_score'] ?? 0 ) < 80 ) {
			$overall_status = '❌ Fail';
		} elseif ( ( $scores['overall_score'] ?? 0 ) < 95 ) {
			$overall_status = '⚠️ Warning';
		}
		$report .= "- **Overall Status:** " . $overall_status . "\n\n";
		
		// Key Metrics
		$report .= "### Key Metrics\n\n";
		$report .= "- Elements with etchData: " . ( $scores['etchData_coverage'] ?? 0 ) . "%\n";
		$report .= "- Proper block types: " . ( $scores['block_type_accuracy'] ?? 0 ) . "%\n";
		$report .= "- Editable in EtchWP: " . ( $scores['editability'] ?? 0 ) . "%\n\n";
		
		// Block Type Coverage
		if ( isset( $audit_results['analysis'] ) && is_array( $audit_results['analysis'] ) ) {
			$report .= self::generate_block_coverage_section( $audit_results['analysis'] );
		}
		
		// Issues and Warnings
		if ( isset( $audit_results['issues'] ) && is_array( $audit_results['issues'] ) ) {
			$report .= self::generate_issues_section( $audit_results['issues'] );
		}
		
		// Element-by-Element Analysis (if available)
		if ( isset( $audit_results['analysis'] ) && is_array( $audit_results['analysis'] ) ) {
			$report .= self::generate_element_analysis_section( $audit_results['analysis'] );
		}
		
		// Recommendations
		$report .= self::generate_recommendations_section( $scores, $audit_results['issues'] ?? array() );
		
		// Compliance Summary
		$report .= self::generate_compliance_summary( $scores );
		
		$report .= "\n---\n";
		$report .= "*Report generated by Vibe Code Deploy Plugin v" . ( $audit_results['plugin_version'] ?? 'unknown' ) . "*\n";
		
		return $report;
	}

	/**
	 * Parse HTML elements from content.
	 *
	 * @param string $html_content HTML content.
	 * @return array Array of element data.
	 */
	private static function parse_html_elements( string $html_content ): array {
		$elements = array();
		
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"><!doctype html><html><body><div id="vibecode-deploy-audit-root">' . $html_content . '</div></body></html>' );
		libxml_clear_errors();
		
		if ( ! $loaded ) {
			return $elements;
		}
		
		$xpath = new \DOMXPath( $dom );
		$root = $xpath->query( '//*[@id="vibecode-deploy-audit-root"]' )->item( 0 );
		if ( ! $root ) {
			return $elements;
		}
		
		// Get all elements
		$all_elements = $xpath->query( './/*', $root );
		foreach ( $all_elements as $el ) {
			if ( ! ( $el instanceof \DOMElement ) ) {
				continue;
			}
			
			$tag = strtolower( $el->tagName );
			$attrs = array();
			foreach ( $el->attributes as $attr ) {
				$attrs[ $attr->name ] = $attr->value;
			}
			
			// Get expected conversion
			$expected = self::get_expected_conversion( $tag, $attrs );
			
			$elements[] = array(
				'tag' => $tag,
				'attributes' => $attrs,
				'expected' => $expected,
				'context' => self::get_element_context( $el ),
			);
		}
		
		return $elements;
	}

	/**
	 * Parse Gutenberg blocks from content.
	 *
	 * @param string $block_content Block content (with <!-- wp:block --> comments).
	 * @return array Array of parsed blocks.
	 */
	private static function parse_blocks( string $block_content ): array {
		$blocks = array();
		
		// Pattern to match block comments: <!-- wp:block-type {"attrs":{}} -->
		$pattern = '/<!--\s*wp:([a-z0-9\/-]+)\s+({[^}]*})?\s*-->/i';
		
		preg_match_all( $pattern, $block_content, $matches, PREG_OFFSET_CAPTURE );
		
		foreach ( $matches[0] as $index => $match ) {
			$block_type = $matches[1][ $index ][0];
			$attrs_json = isset( $matches[2][ $index ][0] ) ? $matches[2][ $index ][0] : '{}';
			
			$attrs = json_decode( $attrs_json, true );
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}
			
			// Check for etchData
			$has_etchData = isset( $attrs['metadata']['etchData'] ) && is_array( $attrs['metadata']['etchData'] );
			$etchData = $has_etchData ? $attrs['metadata']['etchData'] : null;
			
			$blocks[] = array(
				'block_type' => $block_type,
				'attrs' => $attrs,
				'has_etchData' => $has_etchData,
				'etchData' => $etchData,
				'is_editable' => $has_etchData, // Blocks with etchData are editable in EtchWP
			);
		}
		
		return $blocks;
	}

	/**
	 * Analyze WordPress blocks (from parse_blocks() function).
	 *
	 * @param array $wp_blocks WordPress parsed blocks.
	 * @return array Analysis results.
	 */
	private static function analyze_wordpress_blocks( array $wp_blocks ): array {
		$analysis = array();
		
		foreach ( $wp_blocks as $block ) {
			$block_type = $block['blockName'] ?? 'unknown';
			$attrs = $block['attrs'] ?? array();
			
			// Check for etchData
			$has_etchData = isset( $attrs['metadata']['etchData'] ) && is_array( $attrs['metadata']['etchData'] );
			$etchData = $has_etchData ? $attrs['metadata']['etchData'] : null;
			
			// Determine expected block type from etchData if available
			$expected_type = self::get_expected_type_from_etchData( $etchData, $block_type );
			
			$analysis[] = array(
				'block_type' => $block_type,
				'expected_type' => $expected_type,
				'has_etchData' => $has_etchData,
				'is_editable' => $has_etchData,
				'correct_type' => ( $block_type === $expected_type || $expected_type === 'any' ),
				'status' => $has_etchData && ( $block_type === $expected_type || $expected_type === 'any' ) ? 'pass' : ( $has_etchData ? 'warning' : 'fail' ),
			);
		}
		
		return $analysis;
	}

	/**
	 * Analyze elements against blocks.
	 *
	 * @param array $html_elements HTML elements.
	 * @param array $blocks Parsed blocks.
	 * @return array Analysis results.
	 */
	private static function analyze_elements( array $html_elements, array $blocks ): array {
		$analysis = array();
		$block_index = 0;
		
		foreach ( $html_elements as $element ) {
			$expected = $element['expected'];
			$actual_block = isset( $blocks[ $block_index ] ) ? $blocks[ $block_index ] : null;
			
			if ( $actual_block ) {
				$actual_type = $actual_block['block_type'];
				$has_etchData = $actual_block['has_etchData'] ?? false;
				$is_editable = $actual_block['is_editable'] ?? false;
				$correct_type = ( $actual_type === $expected['block_type'] || $expected['block_type'] === 'inline' );
				
				// Determine status
				$status = 'pass';
				if ( ! $has_etchData && $expected['has_etchData'] ) {
					$status = 'fail';
				} elseif ( ! $correct_type ) {
					$status = 'warning';
				} elseif ( ! $is_editable && $expected['has_etchData'] ) {
					$status = 'warning';
				}
				
				$analysis[] = array(
					'element' => $element['tag'],
					'expected_type' => $expected['block_type'],
					'actual_type' => $actual_type,
					'has_etchData' => $has_etchData,
					'expected_etchData' => $expected['has_etchData'],
					'is_editable' => $is_editable,
					'correct_type' => $correct_type,
					'status' => $status,
					'notes' => self::generate_notes( $element, $expected, $actual_block ),
				);
				
				$block_index++;
			} else {
				// Element not converted to block
				$analysis[] = array(
					'element' => $element['tag'],
					'expected_type' => $expected['block_type'],
					'actual_type' => 'none',
					'has_etchData' => false,
					'expected_etchData' => $expected['has_etchData'],
					'is_editable' => false,
					'correct_type' => false,
					'status' => 'fail',
					'notes' => 'Element not converted to block',
				);
			}
		}
		
		return $analysis;
	}

	/**
	 * Get expected conversion for an element.
	 *
	 * @param string $tag HTML tag name.
	 * @param array  $attrs HTML attributes.
	 * @return array Expected conversion data.
	 */
	private static function get_expected_conversion( string $tag, array $attrs ): array {
		$expected = array(
			'h1' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 1 ),
			'h2' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 2 ),
			'h3' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 3 ),
			'h4' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 4 ),
			'h5' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 5 ),
			'h6' => array( 'block_type' => 'heading', 'has_etchData' => true, 'level' => 6 ),
			'p' => array( 'block_type' => 'paragraph', 'has_etchData' => true ),
			'ul' => array( 'block_type' => 'list', 'has_etchData' => true ),
			'ol' => array( 'block_type' => 'list', 'has_etchData' => true ),
			'li' => array( 'block_type' => 'list-item', 'has_etchData' => true ),
			'img' => array( 'block_type' => 'image', 'has_etchData' => true ),
			'blockquote' => array( 'block_type' => 'quote', 'has_etchData' => true ),
			'pre' => array( 'block_type' => 'preformatted', 'has_etchData' => true ),
			'table' => array( 'block_type' => 'table', 'has_etchData' => true ),
			'span' => array( 'block_type' => 'inline', 'has_etchData' => false, 'should_stay_inline' => true ),
			'strong' => array( 'block_type' => 'inline', 'has_etchData' => false, 'should_stay_inline' => true ),
			'em' => array( 'block_type' => 'inline', 'has_etchData' => false, 'should_stay_inline' => true ),
			'a' => array( 'block_type' => 'inline', 'has_etchData' => false, 'should_stay_inline' => true ),
		);
		
		// Default for structural elements
		if ( ! isset( $expected[ $tag ] ) ) {
			$structural = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form' );
			if ( in_array( $tag, $structural, true ) ) {
				return array( 'block_type' => 'group', 'has_etchData' => true );
			}
			
			// Default: html block with etchData
			return array( 'block_type' => 'html', 'has_etchData' => true );
		}
		
		return $expected[ $tag ];
	}

	/**
	 * Get element context.
	 *
	 * @param \DOMElement $el DOM element.
	 * @return string Context description.
	 */
	private static function get_element_context( \DOMElement $el ): string {
		$parent = $el->parentNode;
		if ( $parent instanceof \DOMElement ) {
			return 'nested in ' . strtolower( $parent->tagName );
		}
		return 'standalone';
	}

	/**
	 * Get expected type from etchData.
	 *
	 * @param array|null $etchData etchData array.
	 * @param string     $current_type Current block type.
	 * @return string Expected block type.
	 */
	private static function get_expected_type_from_etchData( ?array $etchData, string $current_type ): string {
		if ( ! $etchData || ! isset( $etchData['block']['tag'] ) ) {
			return 'any';
		}
		
		$tag = $etchData['block']['tag'];
		$expected = self::get_expected_conversion( $tag, array() );
		
		return $expected['block_type'] ?? 'any';
	}

	/**
	 * Generate notes for analysis.
	 *
	 * @param array $element Element data.
	 * @param array $expected Expected conversion.
	 * @param array $actual_block Actual block data.
	 * @return string Notes.
	 */
	private static function generate_notes( array $element, array $expected, array $actual_block ): string {
		$notes = array();
		
		if ( ! $actual_block['has_etchData'] && $expected['has_etchData'] ) {
			$notes[] = 'Missing etchData';
		}
		
		if ( $actual_block['block_type'] !== $expected['block_type'] && $expected['block_type'] !== 'inline' ) {
			$notes[] = 'Wrong block type (expected: ' . $expected['block_type'] . ', got: ' . $actual_block['block_type'] . ')';
		}
		
		if ( isset( $expected['should_stay_inline'] ) && $expected['should_stay_inline'] && $actual_block['block_type'] !== 'inline' ) {
			$notes[] = 'Should stay inline but was converted to block';
		}
		
		return implode( '; ', $notes );
	}

	/**
	 * Calculate compliance scores.
	 *
	 * @param array $analysis Analysis results.
	 * @return array Scores.
	 */
	private static function calculate_compliance_scores( array $analysis ): array {
		$total = count( $analysis );
		if ( $total === 0 ) {
			return array(
				'total' => 0,
				'etchData_coverage' => 0,
				'block_type_accuracy' => 0,
				'editability' => 0,
				'overall_score' => 0,
				'conversion_success_rate' => 0,
			);
		}
		
		$with_etchData = count( array_filter( $analysis, fn( $r ) => $r['has_etchData'] ?? false ) );
		$correct_type = count( array_filter( $analysis, fn( $r ) => $r['correct_type'] ?? false ) );
		$editable = count( array_filter( $analysis, fn( $r ) => $r['is_editable'] ?? false ) );
		$passed = count( array_filter( $analysis, fn( $r ) => ( $r['status'] ?? '' ) === 'pass' ) );
		
		return array(
			'total' => $total,
			'etchData_coverage' => round( ( $with_etchData / $total ) * 100, 1 ),
			'block_type_accuracy' => round( ( $correct_type / $total ) * 100, 1 ),
			'editability' => round( ( $editable / $total ) * 100, 1 ),
			'overall_score' => round( ( ( $with_etchData + $correct_type + $editable ) / ( $total * 3 ) ) * 100, 1 ),
			'conversion_success_rate' => round( ( $passed / $total ) * 100, 1 ),
		);
	}

	/**
	 * Calculate compliance scores from blocks.
	 *
	 * @param array $analysis Analysis results.
	 * @return array Scores.
	 */
	private static function calculate_compliance_scores_from_blocks( array $analysis ): array {
		return self::calculate_compliance_scores( $analysis );
	}

	/**
	 * Categorize issues.
	 *
	 * @param array $analysis Analysis results.
	 * @return array Categorized issues.
	 */
	private static function categorize_issues( array $analysis ): array {
		$issues = array(
			'critical' => array(),
			'warnings' => array(),
			'info' => array(),
		);
		
		foreach ( $analysis as $item ) {
			$status = $item['status'] ?? 'pass';
			$element = $item['element'] ?? 'unknown';
			$notes = $item['notes'] ?? '';
			
			if ( $status === 'fail' ) {
				$issues['critical'][] = array(
					'element' => $element,
					'issue' => $notes ?: 'Conversion failed',
					'recommendation' => self::get_recommendation( $item ),
				);
			} elseif ( $status === 'warning' ) {
				$issues['warnings'][] = array(
					'element' => $element,
					'issue' => $notes ?: 'Minor issue',
					'recommendation' => self::get_recommendation( $item ),
				);
			} elseif ( $notes ) {
				$issues['info'][] = array(
					'element' => $element,
					'note' => $notes,
				);
			}
		}
		
		return $issues;
	}

	/**
	 * Categorize issues from blocks.
	 *
	 * @param array $analysis Analysis results.
	 * @return array Categorized issues.
	 */
	private static function categorize_issues_from_blocks( array $analysis ): array {
		return self::categorize_issues( $analysis );
	}

	/**
	 * Get recommendation for issue.
	 *
	 * @param array $item Analysis item.
	 * @return string Recommendation.
	 */
	private static function get_recommendation( array $item ): string {
		if ( ! ( $item['has_etchData'] ?? false ) && ( $item['expected_etchData'] ?? false ) ) {
			return 'Add etchData metadata to block for EtchWP IDE editability';
		}
		
		if ( ! ( $item['correct_type'] ?? false ) ) {
			return 'Verify block type conversion logic for ' . ( $item['element'] ?? 'element' );
		}
		
		return 'Review conversion logic';
	}

	/**
	 * Generate block coverage section.
	 *
	 * @param array $analysis Analysis results.
	 * @return string Markdown section.
	 */
	private static function generate_block_coverage_section( array $analysis ): string {
		$html = "## Block Type Coverage\n\n";
		$html .= "| Block Type | Count | With etchData | Percentage |\n";
		$html .= "|------------|-------|---------------|------------|\n";
		
		// Count blocks by type
		$type_counts = array();
		foreach ( $analysis as $item ) {
			$type = $item['actual_type'] ?? 'unknown';
			if ( ! isset( $type_counts[ $type ] ) ) {
				$type_counts[ $type ] = array( 'total' => 0, 'with_etchData' => 0 );
			}
			$type_counts[ $type ]['total']++;
			if ( $item['has_etchData'] ?? false ) {
				$type_counts[ $type ]['with_etchData']++;
			}
		}
		
		foreach ( $type_counts as $type => $counts ) {
			$percentage = $counts['total'] > 0 ? round( ( $counts['with_etchData'] / $counts['total'] ) * 100, 1 ) : 0;
			$html .= "| `" . esc_html( $type ) . "` | " . $counts['total'] . " | " . $counts['with_etchData'] . " | " . $percentage . "% |\n";
		}
		
		$html .= "\n";
		return $html;
	}

	/**
	 * Generate issues section.
	 *
	 * @param array $issues Categorized issues.
	 * @return string Markdown section.
	 */
	private static function generate_issues_section( array $issues ): string {
		$html = "## Issues and Warnings\n\n";
		
		// Critical Issues
		$html .= "### Critical Issues (" . count( $issues['critical'] ?? array() ) . ")\n\n";
		if ( ! empty( $issues['critical'] ) ) {
			foreach ( $issues['critical'] as $issue ) {
				$html .= "1. **" . esc_html( $issue['element'] ?? 'Unknown' ) . "**: " . esc_html( $issue['issue'] ?? '' ) . "\n";
				$html .= "   - **Recommendation**: " . esc_html( $issue['recommendation'] ?? '' ) . "\n\n";
			}
		} else {
			$html .= "None found.\n\n";
		}
		
		// Warnings
		$html .= "### Warnings (" . count( $issues['warnings'] ?? array() ) . ")\n\n";
		if ( ! empty( $issues['warnings'] ) ) {
			foreach ( $issues['warnings'] as $issue ) {
				$html .= "1. **" . esc_html( $issue['element'] ?? 'Unknown' ) . "**: " . esc_html( $issue['issue'] ?? '' ) . "\n";
				$html .= "   - **Recommendation**: " . esc_html( $issue['recommendation'] ?? '' ) . "\n\n";
			}
		} else {
			$html .= "None found.\n\n";
		}
		
		// Info
		if ( ! empty( $issues['info'] ) ) {
			$html .= "### Information (" . count( $issues['info'] ) . ")\n\n";
			foreach ( $issues['info'] as $info ) {
				$html .= "- " . esc_html( $info['element'] ?? 'Unknown' ) . ": " . esc_html( $info['note'] ?? '' ) . "\n";
			}
			$html .= "\n";
		}
		
		return $html;
	}

	/**
	 * Generate element analysis section.
	 *
	 * @param array $analysis Analysis results.
	 * @return string Markdown section.
	 */
	private static function generate_element_analysis_section( array $analysis ): string {
		$html = "## Element-by-Element Analysis\n\n";
		$html .= "| Element | Expected | Actual | etchData | Status | Notes |\n";
		$html .= "|---------|----------|--------|----------|--------|-------|\n";
		
		// Group by element type for readability
		$grouped = array();
		foreach ( $analysis as $item ) {
			$element = $item['element'] ?? 'unknown';
			if ( ! isset( $grouped[ $element ] ) ) {
				$grouped[ $element ] = array();
			}
			$grouped[ $element ][] = $item;
		}
		
		foreach ( $grouped as $element => $items ) {
			foreach ( $items as $item ) {
				$expected = $item['expected_type'] ?? 'unknown';
				$actual = $item['actual_type'] ?? 'none';
				$etchData = ( $item['has_etchData'] ?? false ) ? '✅ Yes' : '❌ No';
				$status_icon = ( $item['status'] ?? '' ) === 'pass' ? '✅' : ( ( $item['status'] ?? '' ) === 'warning' ? '⚠️' : '❌' );
				$status = ucfirst( $item['status'] ?? 'unknown' );
				$notes = $item['notes'] ?? '-';
				
				$html .= "| `" . esc_html( $element ) . "` | `" . esc_html( $expected ) . "` | `" . esc_html( $actual ) . "` | " . $etchData . " | " . $status_icon . " " . $status . " | " . esc_html( $notes ) . " |\n";
			}
		}
		
		$html .= "\n";
		return $html;
	}

	/**
	 * Generate recommendations section.
	 *
	 * @param array $scores Compliance scores.
	 * @param array $issues Categorized issues.
	 * @return string Markdown section.
	 */
	private static function generate_recommendations_section( array $scores, array $issues ): string {
		$html = "## Recommendations\n\n";
		
		$etchData_score = $scores['etchData_coverage'] ?? 0;
		$type_score = $scores['block_type_accuracy'] ?? 0;
		$editability_score = $scores['editability'] ?? 0;
		
		if ( $etchData_score >= 95 ) {
			$html .= "1. ✅ Excellent etchData coverage (" . $etchData_score . "%)\n";
		} elseif ( $etchData_score >= 80 ) {
			$html .= "1. ⚠️ Good etchData coverage (" . $etchData_score . "%), but could be improved\n";
		} else {
			$html .= "1. ❌ Low etchData coverage (" . $etchData_score . "%), needs improvement\n";
		}
		
		if ( $type_score >= 95 ) {
			$html .= "2. ✅ Excellent block type accuracy (" . $type_score . "%)\n";
		} elseif ( $type_score >= 80 ) {
			$html .= "2. ⚠️ Good block type accuracy (" . $type_score . "%), but could be improved\n";
		} else {
			$html .= "2. ❌ Low block type accuracy (" . $type_score . "%), needs improvement\n";
		}
		
		if ( $editability_score >= 95 ) {
			$html .= "3. ✅ Excellent EtchWP editability (" . $editability_score . "%)\n";
		} elseif ( $editability_score >= 80 ) {
			$html .= "3. ⚠️ Good EtchWP editability (" . $editability_score . "%), but could be improved\n";
		} else {
			$html .= "3. ❌ Low EtchWP editability (" . $editability_score . "%), needs improvement\n";
		}
		
		$critical_count = count( $issues['critical'] ?? array() );
		$warnings_count = count( $issues['warnings'] ?? array() );
		
		if ( $critical_count > 0 ) {
			$html .= "4. ❌ Address " . $critical_count . " critical issue(s) immediately\n";
		}
		
		if ( $warnings_count > 0 ) {
			$html .= "5. ⚠️ Review " . $warnings_count . " warning(s) for potential improvements\n";
		}
		
		$html .= "\n";
		return $html;
	}

	/**
	 * Generate compliance summary.
	 *
	 * @param array $scores Compliance scores.
	 * @return string Markdown section.
	 */
	private static function generate_compliance_summary( array $scores ): string {
		$html = "## Compliance Summary\n\n";
		
		$etchData_score = $scores['etchData_coverage'] ?? 0;
		$type_score = $scores['block_type_accuracy'] ?? 0;
		$editability_score = $scores['editability'] ?? 0;
		$overall = $scores['overall_score'] ?? 0;
		
		$etchData_status = $etchData_score >= 90 ? '✅ Pass' : ( $etchData_score >= 70 ? '⚠️ Warning' : '❌ Fail' );
		$type_status = $type_score >= 90 ? '✅ Pass' : ( $type_score >= 70 ? '⚠️ Warning' : '❌ Fail' );
		$editability_status = $editability_score >= 90 ? '✅ Pass' : ( $editability_score >= 70 ? '⚠️ Warning' : '❌ Fail' );
		$overall_status = $overall >= 90 ? '✅ **PASS**' : ( $overall >= 70 ? '⚠️ **WARNING**' : '❌ **FAIL**' );
		
		$html .= "- **Structural Rules Compliance:** " . $type_status . "\n";
		$html .= "- **etchData Compliance:** " . $etchData_status . " (" . $etchData_score . "%)\n";
		$html .= "- **Block Type Compliance:** " . $type_status . " (" . $type_score . "%)\n";
		$html .= "- **EtchWP Editability:** " . $editability_status . " (" . $editability_score . "%)\n\n";
		$html .= "**Overall Compliance Status:** " . $overall_status . "\n\n";
		
		return $html;
	}
}
