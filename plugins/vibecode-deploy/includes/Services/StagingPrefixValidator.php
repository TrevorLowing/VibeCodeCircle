<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Validates prefix compliance of staging files (config, theme/functions.php, theme/acf-json).
 *
 * Run after zip extraction; does not require WordPress CPT/taxonomy registration.
 *
 * @package VibeCode\Deploy\Services
 */
final class StagingPrefixValidator {

	/** Taxonomy slugs that are allowed without project prefix (WordPress core or shared). */
	private const TAXONOMY_ALLOWLIST = array( 'category', 'post_tag', 'post_format', 'nav_menu' );

	/**
	 * Validate all prefix rules against the extracted staging build.
	 *
	 * @param string $build_root   Path to build root (staging directory).
	 * @param string $project_slug Project slug (e.g. 'cfa', 'bgp').
	 * @param array  $settings     Plugin settings (prefix_validation_mode, prefix_validation_scope).
	 * @return array{ 'errors': string[], 'warnings': string[] }
	 */
	public static function validate( string $build_root, string $project_slug, array $settings ): array {
		$errors   = array();
		$warnings = array();

		$mode  = isset( $settings['prefix_validation_mode'] ) && is_string( $settings['prefix_validation_mode'] )
			? strtolower( trim( (string) $settings['prefix_validation_mode'] ) )
			: 'warn';
		$scope = isset( $settings['prefix_validation_scope'] ) && is_string( $settings['prefix_validation_scope'] )
			? strtolower( trim( (string) $settings['prefix_validation_scope'] ) )
			: 'all';

		if ( $mode === 'off' || $project_slug === '' || ! is_dir( $build_root ) ) {
			return array( 'errors' => $errors, 'warnings' => $warnings );
		}

		$as_errors = $mode === 'fail';
		$as_warnings = $mode === 'warn';

		// Shortcodes from config
		if ( $scope === 'all' || $scope === 'shortcodes' ) {
			$result = self::validate_shortcodes( $build_root, $project_slug );
			$warnings = array_merge( $warnings, $result['warnings'] );
			$violations = $result['violations'];
			if ( $as_errors ) {
				$errors = array_merge( $errors, $violations );
			} elseif ( $as_warnings ) {
				$warnings = array_merge( $warnings, $violations );
			}
		}

		// CPT and taxonomy from theme/functions.php
		if ( $scope === 'all' || $scope === 'cpts' ) {
			$result = self::validate_cpts_and_taxonomies( $build_root, $project_slug );
			$violations = $result['violations'];
			if ( $as_errors ) {
				$errors = array_merge( $errors, $violations );
			} elseif ( $as_warnings ) {
				$warnings = array_merge( $warnings, $violations );
			}
		}

		// ACF group key and field names
		if ( $scope === 'all' ) {
			$result = self::validate_acf( $build_root, $project_slug );
			$violations = $result['violations'];
			if ( $as_errors ) {
				$errors = array_merge( $errors, $violations );
			} elseif ( $as_warnings ) {
				$warnings = array_merge( $warnings, $violations );
			}
		}

		return array( 'errors' => $errors, 'warnings' => $warnings );
	}

	/**
	 * Validate shortcode names in vibecode-deploy-shortcodes.json.
	 *
	 * @param string $build_root   Build root path.
	 * @param string $project_slug Project slug.
	 * @return array{ 'violations': string[], 'warnings': string[] }
	 */
	private static function validate_shortcodes( string $build_root, string $project_slug ): array {
		$violations = array();
		$warnings   = array();

		$config = ShortcodePlaceholderService::load_config( $build_root );
		if ( isset( $config['_error'] ) ) {
			$warnings[] = 'Shortcode config not found or invalid; skipping shortcode prefix check.';
			return array( 'violations' => $violations, 'warnings' => $warnings );
		}

		$names = array();
		if ( isset( $config['pages'] ) && is_array( $config['pages'] ) ) {
			foreach ( $config['pages'] as $page_slug => $page_config ) {
				if ( ! is_array( $page_config ) ) {
					continue;
				}
				foreach ( array( 'required_shortcodes', 'recommended_shortcodes' ) as $key ) {
					if ( ! isset( $page_config[ $key ] ) || ! is_array( $page_config[ $key ] ) ) {
						continue;
					}
					foreach ( $page_config[ $key ] as $item ) {
						$name = is_array( $item ) && isset( $item['name'] ) ? $item['name'] : $item;
						if ( is_string( $name ) && $name !== '' ) {
							$names[ $name ] = true;
						}
					}
				}
			}
		}
		if ( isset( $config['post_types'] ) && is_array( $config['post_types'] ) ) {
			foreach ( $config['post_types'] as $cpt => $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				foreach ( array( 'required_shortcodes', 'recommended_shortcodes' ) as $key ) {
					if ( ! isset( $rule[ $key ] ) || ! is_array( $rule[ $key ] ) ) {
						continue;
					}
					foreach ( $rule[ $key ] as $item ) {
						$name = is_array( $item ) && isset( $item['name'] ) ? $item['name'] : $item;
						if ( is_string( $name ) && $name !== '' ) {
							$names[ $name ] = true;
						}
					}
				}
			}
		}

		foreach ( array_keys( $names ) as $name ) {
			if ( ! ShortcodePlaceholderService::matches_project_prefix( $name, $project_slug ) ) {
				$violations[] = sprintf(
					'Shortcode "%s" in config does not match project prefix "%s".',
					$name,
					$project_slug
				);
			}
		}

		return array( 'violations' => $violations, 'warnings' => $warnings );
	}

	/**
	 * Validate CPT and taxonomy slugs in theme/functions.php.
	 *
	 * @param string $build_root   Build root path.
	 * @param string $project_slug Project slug.
	 * @return array{ 'violations': string[] }
	 */
	private static function validate_cpts_and_taxonomies( string $build_root, string $project_slug ): array {
		$violations = array();

		$theme_dir = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'theme';
		$php_file  = $theme_dir . DIRECTORY_SEPARATOR . 'functions.php';
		if ( ! is_file( $php_file ) || ! is_readable( $php_file ) ) {
			return array( 'violations' => $violations );
		}

		$content = file_get_contents( $php_file );
		if ( ! is_string( $content ) ) {
			return array( 'violations' => $violations );
		}

		$built_in = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );

		// register_post_type( 'slug', ... or register_post_type( "slug", ...
		if ( preg_match_all( "/register_post_type\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m ) && ! empty( $m[1] ) ) {
			$slugs = array_unique( $m[1] );
			foreach ( $slugs as $slug ) {
				$slug = trim( $slug );
				if ( $slug === '' || in_array( $slug, $built_in, true ) ) {
					continue;
				}
				if ( ! ShortcodePlaceholderService::matches_project_prefix( $slug, $project_slug ) ) {
					$violations[] = sprintf(
						'CPT "%s" in theme/functions.php does not match project prefix "%s".',
						$slug,
						$project_slug
					);
				}
			}
		}

		// register_taxonomy( 'slug', ...
		if ( preg_match_all( "/register_taxonomy\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m ) && ! empty( $m[1] ) ) {
			$slugs = array_unique( $m[1] );
			foreach ( $slugs as $slug ) {
				$slug = trim( $slug );
				if ( $slug === '' || in_array( $slug, self::TAXONOMY_ALLOWLIST, true ) ) {
					continue;
				}
				if ( ! ShortcodePlaceholderService::matches_project_prefix( $slug, $project_slug ) ) {
					$violations[] = sprintf(
						'Taxonomy "%s" in theme/functions.php does not match project prefix "%s".',
						$slug,
						$project_slug
					);
				}
			}
		}

		return array( 'violations' => $violations );
	}

	/**
	 * Validate ACF field group keys and field names in theme/acf-json/*.json.
	 *
	 * @param string $build_root   Build root path.
	 * @param string $project_slug Project slug.
	 * @return array{ 'violations': string[] }
	 */
	private static function validate_acf( string $build_root, string $project_slug ): array {
		$violations = array();

		$acf_dir = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'theme' . DIRECTORY_SEPARATOR . 'acf-json';
		if ( ! is_dir( $acf_dir ) ) {
			return array( 'violations' => $violations );
		}

		$expected_group_prefix = 'group_' . $project_slug . '_';
		$expected_field_prefix = $project_slug . '_';

		$files = glob( $acf_dir . DIRECTORY_SEPARATOR . '*.json' );
		if ( ! is_array( $files ) ) {
			return array( 'violations' => $violations );
		}

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) {
				continue;
			}
			$raw = file_get_contents( $file );
			if ( ! is_string( $raw ) ) {
				continue;
			}
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				continue;
			}

			$filename = basename( $file );

			// Group key must be group_{slug}_...
			if ( isset( $data['key'] ) && is_string( $data['key'] ) ) {
				if ( strpos( $data['key'], $expected_group_prefix ) !== 0 ) {
					$violations[] = sprintf(
						'ACF group key "%s" in %s should start with "%s".',
						$data['key'],
						$filename,
						$expected_group_prefix
					);
				}
			}

			// Field names (and sub_fields) should start with project_slug_
			self::validate_acf_fields( $data['fields'] ?? array(), $expected_field_prefix, $filename, $violations );
		}

		return array( 'violations' => $violations );
	}

	/**
	 * Recursively validate ACF field names and sub_fields.
	 *
	 * @param array  $fields     ACF fields array.
	 * @param string $prefix     Expected prefix (e.g. cfa_).
	 * @param string $filename   File name for messages.
	 * @param array  $violations Output violations (modified in place).
	 */
	private static function validate_acf_fields( array $fields, string $prefix, string $filename, array &$violations ): void {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( isset( $field['name'] ) && is_string( $field['name'] ) && $field['name'] !== '' ) {
				if ( strpos( $field['name'], $prefix ) !== 0 ) {
					$violations[] = sprintf(
						'ACF field name "%s" in %s should start with "%s".',
						$field['name'],
						$filename,
						$prefix
					);
				}
			}
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				self::validate_acf_fields( $field['sub_fields'], $prefix, $filename, $violations );
			}
		}
	}
}
