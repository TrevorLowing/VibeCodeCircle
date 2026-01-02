<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Theme Deployment Service
 *
 * Deploys theme files (functions.php, ACF JSON) from staging ZIP to child theme.
 * Implements smart merge for functions.php to preserve existing code while updating CPTs and shortcodes.
 *
 * @package VibeCode\Deploy\Services
 */
final class ThemeDeployService {

	/**
	 * Deploy theme files from staging build to child theme.
	 *
	 * @param string $build_root Path to extracted staging build root.
	 * @param string $theme_slug Child theme slug (e.g., 'my-site-etch-child').
	 * @return array Results with 'created', 'updated', 'errors' keys.
	 */
	public static function deploy_theme_files( string $build_root, string $theme_slug ): array {
		$results = array(
			'created' => array(),
			'updated' => array(),
			'errors' => array(),
		);

		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		$staging_theme_dir = $build_root . '/theme';

		// Ensure child theme exists
		if ( ! self::ensure_child_theme_exists( $theme_slug ) ) {
			$results['errors'][] = "Failed to create or verify child theme: {$theme_slug}";
			return $results;
		}

		// Deploy functions.php with smart merge
		if ( is_dir( $staging_theme_dir ) && file_exists( $staging_theme_dir . '/functions.php' ) ) {
			$merge_result = self::smart_merge_functions_php(
				$staging_theme_dir . '/functions.php',
				$theme_dir . '/functions.php'
			);
			if ( $merge_result['success'] ) {
				if ( $merge_result['created'] ) {
					$results['created'][] = 'functions.php';
				} else {
					$results['updated'][] = 'functions.php';
				}
			} else {
				$results['errors'][] = 'functions.php: ' . $merge_result['error'];
			}
		}

		// Deploy ACF JSON files
		$staging_acf_dir = $staging_theme_dir . '/acf-json';
		$theme_acf_dir = $theme_dir . '/acf-json';
		if ( is_dir( $staging_acf_dir ) ) {
			$acf_result = self::copy_acf_json_files( $staging_acf_dir, $theme_acf_dir );
			$results['created'] = array_merge( $results['created'], $acf_result['created'] );
			$results['updated'] = array_merge( $results['updated'], $acf_result['updated'] );
			$results['errors'] = array_merge( $results['errors'], $acf_result['errors'] );
		}

		return $results;
	}

	/**
	 * Smart merge functions.php from staging into theme.
	 *
	 * Preserves existing code while updating/adding CPT registrations and shortcodes.
	 * 
	 * Merge strategy:
	 * 1. Extract CPT registration code from staging functions.php
	 * 2. Extract shortcode registration code from staging functions.php
	 * 3. Remove old CPT/shortcode registrations from theme functions.php
	 * 4. Insert new CPT/shortcode registrations into theme functions.php
	 * 5. Preserve all other existing code in theme functions.php
	 *
	 * @param string $staging_file Path to staging functions.php.
	 * @param string $theme_file Path to theme functions.php.
	 * @return array Result with 'success', 'created', 'error' keys.
	 */
	private static function smart_merge_functions_php( string $staging_file, string $theme_file ): array {
		$staging_content = file_get_contents( $staging_file );
		if ( $staging_content === false ) {
			return array( 'success' => false, 'error' => 'Unable to read staging functions.php' );
		}

		$theme_content = '';
		$created = false;
		if ( file_exists( $theme_file ) ) {
			$theme_content = file_get_contents( $theme_file );
			if ( $theme_content === false ) {
				return array( 'success' => false, 'error' => 'Unable to read theme functions.php' );
			}
		} else {
			$created = true;
			// Start with basic PHP opening tag if file doesn't exist
			$theme_content = "<?php\n\n";
		}

		// Extract CPT registrations from staging
		$staging_cpts = self::extract_cpt_registrations( $staging_content );
		$staging_shortcodes = self::extract_shortcode_registrations( $staging_content );
		$staging_acf_filters = self::extract_acf_filters( $staging_content );

		// Merge CPTs
		$theme_content = self::merge_cpt_registrations( $theme_content, $staging_cpts );

		// Merge shortcodes
		$theme_content = self::merge_shortcode_registrations( $theme_content, $staging_shortcodes );

		// Ensure ACF JSON filters exist
		$theme_content = self::ensure_acf_filters( $theme_content, $staging_acf_filters );

		// Write merged content
		if ( file_put_contents( $theme_file, $theme_content ) === false ) {
			return array( 'success' => false, 'error' => 'Unable to write theme functions.php' );
		}

		return array( 'success' => true, 'created' => $created );
	}

	/**
	 * Extract CPT registration code blocks from functions.php content.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array of CPT slugs => registration code blocks.
	 */
	private static function extract_cpt_registrations( string $content ): array {
		$cpts = array();
		// Match register_post_type('slug', array(...)) blocks
		$pattern = '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*array\s*\([^)]*(?:\([^)]*\)[^)]*)*\)\s*\)\s*;/s';
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches as $match ) {
				$slug = $match[1][0];
				$cpts[ $slug ] = $match[0][0];
			}
		}
		return $cpts;
	}

	/**
	 * Extract shortcode registration code blocks from functions.php content.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array of shortcode tags => registration code blocks.
	 */
	private static function extract_shortcode_registrations( string $content ): array {
		$shortcodes = array();
		// Match add_shortcode('tag', function/callable) blocks
		$pattern = '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:function\s*\([^)]*\)\s*\{[^}]*\}|\$[a-zA-Z_][a-zA-Z0-9_]*|array\s*\([^)]*\))\s*\)\s*;/s';
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches as $match ) {
				$tag = $match[1][0];
				// Extract full shortcode block (may span multiple lines)
				$start_pos = $match[0][1];
				$end_pos = $start_pos + strlen( $match[0][0] );
				// Try to capture full closure
				$full_block = substr( $content, $start_pos, $end_pos - $start_pos );
				$shortcodes[ $tag ] = $full_block;
			}
		}
		return $shortcodes;
	}

	/**
	 * Extract ACF JSON path filter configurations.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array with 'save_json' and 'load_json' filter code.
	 */
	private static function extract_acf_filters( string $content ): array {
		$filters = array(
			'save_json' => '',
			'load_json' => '',
		);
		// Match acf/settings/save_json filter
		if ( preg_match( '/add_filter\s*\(\s*[\'"]acf\/settings\/save_json[\'"]\s*,[^)]+\)\s*;/s', $content, $match ) ) {
			$filters['save_json'] = $match[0];
		}
		// Match acf/settings/load_json filter
		if ( preg_match( '/add_filter\s*\(\s*[\'"]acf\/settings\/load_json[\'"]\s*,[^)]+\)\s*;/s', $content, $match ) ) {
			$filters['load_json'] = $match[0];
		}
		return $filters;
	}

	/**
	 * Merge CPT registrations into theme content.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_cpts CPT registrations from staging.
	 * @return string Merged content.
	 */
	private static function merge_cpt_registrations( string $theme_content, array $staging_cpts ): string {
		foreach ( $staging_cpts as $slug => $registration_code ) {
			// Check if CPT already exists in theme
			$pattern = '/register_post_type\s*\(\s*[\'"]' . preg_quote( $slug, '/' ) . '[\'"]\s*,/';
			if ( preg_match( $pattern, $theme_content ) ) {
				// Replace existing registration
				$theme_content = preg_replace(
					'/register_post_type\s*\(\s*[\'"]' . preg_quote( $slug, '/' ) . '[\'"]\s*,\s*array\s*\([^)]*(?:\([^)]*\)[^)]*)*\)\s*\)\s*;/s',
					$registration_code,
					$theme_content
				);
			} else {
				// Add new registration before closing PHP tag or at end
				if ( strpos( $theme_content, 'register_post_type' ) !== false ) {
					// Insert after last register_post_type
					$pattern = '/(register_post_type\s*\([^;]+;\s*)/s';
					$theme_content = preg_replace( $pattern, '$1' . "\n" . $registration_code . "\n", $theme_content, -1, $count );
					if ( $count === 0 ) {
						// Fallback: append before closing PHP tag
						$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
					}
				} else {
					// Add after init hook or at end
					$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
				}
			}
		}
		return $theme_content;
	}

	/**
	 * Merge shortcode registrations into theme content.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_shortcodes Shortcode registrations from staging.
	 * @return string Merged content.
	 */
	private static function merge_shortcode_registrations( string $theme_content, array $staging_shortcodes ): string {
		foreach ( $staging_shortcodes as $tag => $registration_code ) {
			// Check if shortcode already exists
			$pattern = '/add_shortcode\s*\(\s*[\'"]' . preg_quote( $tag, '/' ) . '[\'"]\s*,/';
			if ( preg_match( $pattern, $theme_content ) ) {
				// Replace existing shortcode (simplified - may need more sophisticated matching)
				$theme_content = preg_replace(
					'/add_shortcode\s*\(\s*[\'"]' . preg_quote( $tag, '/' ) . '[\'"]\s*,[^;]+;\s*/s',
					$registration_code,
					$theme_content,
					1
				);
			} else {
				// Add new shortcode
				$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
			}
		}
		return $theme_content;
	}

	/**
	 * Ensure ACF JSON path filters exist in theme content.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_filters ACF filter code from staging.
	 * @return string Content with ACF filters ensured.
	 */
	private static function ensure_acf_filters( string $theme_content, array $staging_filters ): string {
		// Check for save_json filter
		if ( ! empty( $staging_filters['save_json'] ) && strpos( $theme_content, "acf/settings/save_json" ) === false ) {
			$theme_content = rtrim( $theme_content ) . "\n\n" . $staging_filters['save_json'] . "\n";
		}
		// Check for load_json filter
		if ( ! empty( $staging_filters['load_json'] ) && strpos( $theme_content, "acf/settings/load_json" ) === false ) {
			$theme_content = rtrim( $theme_content ) . "\n\n" . $staging_filters['load_json'] . "\n";
		}
		return $theme_content;
	}

	/**
	 * Copy ACF JSON files from staging to theme directory.
	 *
	 * @param string $staging_acf_dir Path to staging acf-json directory.
	 * @param string $theme_acf_dir Path to theme acf-json directory.
	 * @return array Results with 'created', 'updated', 'errors' keys.
	 */
	private static function copy_acf_json_files( string $staging_acf_dir, string $theme_acf_dir ): array {
		$results = array(
			'created' => array(),
			'updated' => array(),
			'errors' => array(),
		);

		if ( ! is_dir( $staging_acf_dir ) ) {
			return $results;
		}

		// Ensure theme acf-json directory exists
		if ( ! is_dir( $theme_acf_dir ) ) {
			wp_mkdir_p( $theme_acf_dir );
		}

		// Get all JSON files from staging
		$files = glob( $staging_acf_dir . '/*.json' );
		foreach ( $files as $staging_file ) {
			$filename = basename( $staging_file );
			$theme_file = $theme_acf_dir . '/' . $filename;

			$staging_content = file_get_contents( $staging_file );
			if ( $staging_content === false ) {
				$results['errors'][] = "Unable to read: {$filename}";
				continue;
			}

			$created = ! file_exists( $theme_file );
			if ( file_put_contents( $theme_file, $staging_content ) !== false ) {
				if ( $created ) {
					$results['created'][] = "acf-json/{$filename}";
				} else {
					$results['updated'][] = "acf-json/{$filename}";
				}
			} else {
				$results['errors'][] = "Unable to write: acf-json/{$filename}";
			}
		}

		return $results;
	}

	/**
	 * Ensure child theme exists and is properly configured.
	 *
	 * @param string $theme_slug Child theme slug.
	 * @param string $parent_theme Parent theme slug (default: 'etch-theme').
	 * @return bool True if theme exists or was created successfully.
	 */
	private static function ensure_child_theme_exists( string $theme_slug, string $parent_theme = 'etch-theme' ): bool {
		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;

		// If theme already exists, verify it's a child theme
		if ( is_dir( $theme_dir ) ) {
			$style_file = $theme_dir . '/style.css';
			if ( file_exists( $style_file ) ) {
				$style_content = file_get_contents( $style_file );
				if ( strpos( $style_content, 'Template:' ) !== false ) {
					return true; // Already a child theme
				}
			}
		}

		// Create theme directory if needed
		if ( ! is_dir( $theme_dir ) ) {
			wp_mkdir_p( $theme_dir );
		}

		// Create or update style.css with child theme header
		$style_file = $theme_dir . '/style.css';
		$theme_name = ucwords( str_replace( array( '-', '_' ), ' ', $theme_slug ) );
		$style_content = "/*
Theme Name: {$theme_name} Child
Template: {$parent_theme}
Version: 1.0.0
*/
";

		if ( file_put_contents( $style_file, $style_content ) === false ) {
			return false;
		}

		// Create basic index.php if it doesn't exist
		$index_file = $theme_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			$index_content = "<?php\n// Silence is golden.\n";
			file_put_contents( $index_file, $index_content );
		}

		return true;
	}
}
