<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * EtchWP Compliance Service
 *
 * Comprehensive EtchWP compliance checking and validation.
 * Checks block editability, post content preservation, template structure,
 * image handling, block conversion accuracy, and child theme structure.
 *
 * @package VibeCode\Deploy\Services
 */
final class EtchWPComplianceService {
	/**
	 * Run comprehensive compliance check on a page.
	 *
	 * @param int $page_id WordPress page/post ID.
	 * @return array Compliance check results with pass/fail status for each area.
	 */
	public static function run_comprehensive_check( int $page_id ): array {
		$results = array(
			'page_id' => $page_id,
			'page_title' => get_the_title( $page_id ),
			'page_slug' => get_post_field( 'post_name', $page_id ),
			'checks' => array(),
			'overall_status' => 'pass',
			'score' => 0,
			'total_checks' => 0,
		);

		// Get page content
		$content = get_post_field( 'post_content', $page_id );
		if ( ! is_string( $content ) ) {
			$content = '';
		}

		// Run all compliance checks
		$results['checks']['block_editability'] = self::check_block_editability( $content );
		$results['checks']['post_content_preservation'] = self::check_post_content_preservation( $page_id );
		$results['checks']['image_handling'] = self::check_image_handling( $content );
		$results['checks']['block_conversion'] = self::check_block_conversion( $content );

		// Calculate overall score
		$passed = 0;
		$total = 0;
		foreach ( $results['checks'] as $check ) {
			if ( isset( $check['status'] ) ) {
				$total++;
				if ( $check['status'] === 'pass' ) {
					$passed++;
				}
			}
		}

		$results['total_checks'] = $total;
		$results['score'] = $total > 0 ? round( ( $passed / $total ) * 100, 1 ) : 0;
		$results['overall_status'] = ( $results['score'] >= 100 ) ? 'pass' : ( ( $results['score'] >= 70 ) ? 'warning' : 'fail' );

		return $results;
	}

	/**
	 * Check block editability (etchData on all blocks).
	 *
	 * @param string $content Page/post content (Gutenberg blocks).
	 * @return array Check results with status, count, and issues.
	 */
	public static function check_block_editability( string $content ): array {
		$result = array(
			'status' => 'pass',
			'total_blocks' => 0,
			'blocks_with_etchData' => 0,
			'blocks_without_etchData' => 0,
			'percentage' => 0,
			'issues' => array(),
		);

		// Parse blocks from content
		$blocks = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}

		$result['total_blocks'] = count( $blocks );

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
				continue;
			}

			// Skip core blocks that don't need etchData (core/paragraph, core/heading, etc. are fine without it)
			// But we want to check if they have etchData for EtchWP editability
			$has_etchData = false;
			if ( isset( $block['attrs']['metadata']['etchData'] ) && is_array( $block['attrs']['metadata']['etchData'] ) ) {
				$has_etchData = true;
			}

			if ( $has_etchData ) {
				$result['blocks_with_etchData']++;
			} else {
				$result['blocks_without_etchData']++;
				$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : 'unknown';
				$result['issues'][] = array(
					'block' => $block_name,
					'message' => 'Block missing etchData metadata',
				);
			}
		}

		// Calculate percentage
		if ( $result['total_blocks'] > 0 ) {
			$result['percentage'] = round( ( $result['blocks_with_etchData'] / $result['total_blocks'] ) * 100, 1 );
		}

		// Determine status
		if ( $result['percentage'] >= 95 ) {
			$result['status'] = 'pass';
		} elseif ( $result['percentage'] >= 70 ) {
			$result['status'] = 'warning';
		} else {
			$result['status'] = 'fail';
		}

		return $result;
	}

	/**
	 * Check post content preservation.
	 *
	 * @param int $page_id WordPress page/post ID.
	 * @return array Check results with status and message.
	 */
	public static function check_post_content_preservation( int $page_id ): array {
		$result = array(
			'status' => 'pass',
			'has_content' => false,
			'content_length' => 0,
			'message' => '',
		);

		$content = get_post_field( 'post_content', $page_id );
		if ( is_string( $content ) && $content !== '' ) {
			$result['has_content'] = true;
			$result['content_length'] = strlen( $content );
			$result['message'] = 'Post content is populated';
		} else {
			$result['status'] = 'fail';
			$result['message'] = 'Post content is empty (may be cleared for custom templates)';
		}

		// Check if EtchWP is active
		if ( defined( 'ETCHWP_VERSION' ) || function_exists( 'etchwp_version' ) ) {
			if ( ! $result['has_content'] ) {
				$result['status'] = 'fail';
				$result['message'] = 'EtchWP is active but post_content is empty';
			}
		}

		return $result;
	}

	/**
	 * Check image handling compliance.
	 *
	 * @param string $content Page/post content (Gutenberg blocks).
	 * @return array Check results with status, count, and issues.
	 */
	public static function check_image_handling( string $content ): array {
		$result = array(
			'status' => 'pass',
			'total_images' => 0,
			'images_with_absolute_urls' => 0,
			'images_with_etchData' => 0,
			'issues' => array(),
		);

		// Parse blocks
		$blocks = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
				continue;
			}

			// Check image blocks
			if ( $block['blockName'] === 'core/image' || ( isset( $block['blockName'] ) && strpos( (string) $block['blockName'], 'image' ) !== false ) ) {
				$result['total_images']++;

				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$url = isset( $attrs['url'] ) && is_string( $attrs['url'] ) ? $attrs['url'] : '';

				// Check for absolute URL
				if ( $url !== '' && ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) ) {
					$result['images_with_absolute_urls']++;
				} else {
					$result['issues'][] = array(
						'block' => isset( $block['blockName'] ) ? (string) $block['blockName'] : 'unknown',
						'message' => 'Image block has relative or missing URL',
					);
				}

				// Check for etchData
				if ( isset( $attrs['metadata']['etchData'] ) && is_array( $attrs['metadata']['etchData'] ) ) {
					$result['images_with_etchData']++;
				} else {
					$result['issues'][] = array(
						'block' => isset( $block['blockName'] ) ? (string) $block['blockName'] : 'unknown',
						'message' => 'Image block missing etchData metadata',
					);
				}
			}
		}

		// Determine status
		if ( $result['total_images'] === 0 ) {
			$result['status'] = 'pass'; // No images to check
		} elseif ( $result['images_with_absolute_urls'] === $result['total_images'] && $result['images_with_etchData'] === $result['total_images'] ) {
			$result['status'] = 'pass';
		} elseif ( $result['images_with_absolute_urls'] >= ( $result['total_images'] * 0.8 ) && $result['images_with_etchData'] >= ( $result['total_images'] * 0.8 ) ) {
			$result['status'] = 'warning';
		} else {
			$result['status'] = 'fail';
		}

		return $result;
	}

	/**
	 * Check block conversion accuracy.
	 *
	 * @param string $content Page/post content (Gutenberg blocks).
	 * @return array Check results with status and issues.
	 */
	public static function check_block_conversion( string $content ): array {
		$result = array(
			'status' => 'pass',
			'total_blocks' => 0,
			'semantic_blocks' => 0,
			'html_blocks' => 0,
			'issues' => array(),
		);

		// Parse blocks
		$blocks = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}

		$result['total_blocks'] = count( $blocks );

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
				continue;
			}

			$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';

			// Count semantic blocks (core/paragraph, core/heading, core/image, etc.)
			if ( strpos( $block_name, 'core/' ) === 0 ) {
				$result['semantic_blocks']++;
			} elseif ( $block_name === 'core/html' ) {
				$result['html_blocks']++;
				// HTML blocks are acceptable but should have etchData
				if ( ! isset( $block['attrs']['metadata']['etchData'] ) || ! is_array( $block['attrs']['metadata']['etchData'] ) ) {
					$result['issues'][] = array(
						'block' => $block_name,
						'message' => 'HTML block missing etchData metadata',
					);
				}
			}
		}

		// Determine status
		if ( $result['total_blocks'] === 0 ) {
			$result['status'] = 'fail'; // No blocks found
		} elseif ( $result['html_blocks'] > ( $result['total_blocks'] * 0.3 ) ) {
			$result['status'] = 'warning'; // Too many HTML blocks (should be semantic blocks)
		} elseif ( count( $result['issues'] ) > 0 ) {
			$result['status'] = 'warning';
		} else {
			$result['status'] = 'pass';
		}

		return $result;
	}

	/**
	 * Check template structure compliance.
	 *
	 * @param string $template_slug Template slug to check.
	 * @return array Check results with status and issues.
	 */
	public static function check_template_structure( string $template_slug ): array {
		$result = array(
			'status' => 'pass',
			'template_slug' => $template_slug,
			'issues' => array(),
		);

		// Check if template exists
		$template_path = get_template_directory() . '/' . $template_slug . '.php';
		$block_template_path = get_template_directory() . '/templates/' . $template_slug . '.html';

		if ( file_exists( $template_path ) ) {
			// Check if it's a PHP template (not recommended for EtchWP)
			$content = file_get_contents( $template_path );
			if ( is_string( $content ) && strpos( $content, 'get_header()' ) !== false ) {
				$result['issues'][] = array(
					'message' => 'Template uses get_header() instead of block markup',
				);
			}
			if ( is_string( $content ) && strpos( $content, 'get_footer()' ) !== false ) {
				$result['issues'][] = array(
					'message' => 'Template uses get_footer() instead of block markup',
				);
			}
		} elseif ( file_exists( $block_template_path ) ) {
			// Block template exists (good)
			$result['status'] = 'pass';
		} else {
			$result['issues'][] = array(
				'message' => 'Template file not found',
			);
		}

		if ( count( $result['issues'] ) > 0 ) {
			$result['status'] = 'warning';
		}

		return $result;
	}

	/**
	 * Check child theme structure compliance.
	 *
	 * @param string $project_slug Project slug to check theme for.
	 * @return array Check results with status and issues.
	 */
	public static function check_child_theme_structure( string $project_slug ): array {
		$result = array(
			'status' => 'pass',
			'project_slug' => $project_slug,
			'issues' => array(),
		);

		// Check if child theme exists
		$theme = wp_get_theme();
		if ( ! $theme->exists() ) {
			$result['status'] = 'fail';
			$result['issues'][] = array(
				'message' => 'Active theme not found',
			);
			return $result;
		}

		// Check if it's a child theme
		$parent = $theme->get( 'Template' );
		if ( $parent === '' ) {
			$result['issues'][] = array(
				'message' => 'Active theme is not a child theme',
			);
		}

		// Check for functions.php
		$functions_path = $theme->get_stylesheet_directory() . '/functions.php';
		if ( ! file_exists( $functions_path ) ) {
			$result['issues'][] = array(
				'message' => 'functions.php not found in child theme',
			);
		}

		// Check for ACF JSON directory
		$acf_json_path = $theme->get_stylesheet_directory() . '/acf-json';
		if ( ! is_dir( $acf_json_path ) ) {
			$result['issues'][] = array(
				'message' => 'acf-json directory not found in child theme',
			);
		}

		if ( count( $result['issues'] ) > 0 ) {
			$result['status'] = count( $result['issues'] ) >= 2 ? 'fail' : 'warning';
		}

		return $result;
	}
}
