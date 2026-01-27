<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;

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
	 * Get child theme slug from project slug.
	 *
	 * @param string $project_slug Project slug.
	 * @return string Child theme slug (e.g., 'cfa-etch-child').
	 */
	public static function get_child_theme_slug( string $project_slug ): string {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return 'default-etch-child';
		}
		return $project_slug . '-etch-child';
	}

	/**
	 * Deploy theme files from staging build to child theme.
	 *
	 * @param string $build_root Path to extracted staging build root.
	 * @param string $theme_slug Child theme slug (e.g., 'my-site-etch-child').
	 * @param array  $selected_theme_files Optional array of theme file names to deploy (e.g., 'functions.php', 'acf-json/*.json').
	 * @return array Results with 'created', 'updated', 'errors', 'snapshots' keys.
	 */
	public static function deploy_theme_files( string $build_root, string $theme_slug, array $selected_theme_files = array() ): array {
		// Get project_slug for logging (plugin is agnostic - works with any project)
		$project_slug = Settings::get_all()['project_slug'] ?? '';
		
		$results = array(
			'created' => array(),
			'updated' => array(),
			'errors' => array(),
			'snapshots' => array(), // File snapshots for rollback
		);

		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		$staging_theme_dir = $build_root . '/theme';

		// Ensure child theme exists and is activated
		$theme_setup = self::ensure_child_theme_exists( $theme_slug );
		if ( ! $theme_setup['success'] ) {
			$error_msg = $theme_setup['error'] ?? "Failed to create or verify child theme: {$theme_slug}";
			$results['errors'][] = $error_msg;
			return $results;
		}

		// Log theme creation/activation
		if ( $theme_setup['created'] ) {
			Logger::info( 'Child theme created.', array( 'theme_slug' => $theme_slug ) );
		}
		if ( $theme_setup['activated'] ) {
			Logger::info( 'Child theme activated.', array( 'theme_slug' => $theme_slug ) );
		} elseif ( $theme_setup['error'] !== null ) {
			Logger::warning( 'Child theme activation failed.', array(
				'theme_slug' => $theme_slug,
				'error' => $theme_setup['error'],
			) );
		}

		// Determine which files to deploy
		$deploy_functions = empty( $selected_theme_files ) || in_array( 'functions.php', $selected_theme_files, true );
		$deploy_acf = empty( $selected_theme_files ) || in_array( 'acf-json/*.json', $selected_theme_files, true ) || array_filter( $selected_theme_files, function( $file ) {
			return strpos( (string) $file, 'acf-json/' ) === 0;
		} );

		// Deploy functions.php with smart merge
		if ( $deploy_functions && is_dir( $staging_theme_dir ) && file_exists( $staging_theme_dir . '/functions.php' ) ) {
			// Capture snapshot before deployment (for backup/rollback)
			$theme_file = $theme_dir . '/functions.php';
			$backup_file = $theme_file . '.backup';
			if ( file_exists( $theme_file ) ) {
				$before_content = file_get_contents( $theme_file );
				if ( $before_content !== false ) {
					$results['snapshots']['functions.php'] = $before_content;
					// Create backup file for emergency restore if merge fails
					file_put_contents( $backup_file, $before_content );
				}
			}

			$merge_result = self::smart_merge_functions_php(
				$staging_theme_dir . '/functions.php',
				$theme_file
			);
			if ( $merge_result['success'] ) {
				// Verify CPT code exists in merged file (generic verification - works for any project)
				if ( file_exists( $theme_file ) ) {
					$merged_content = file_get_contents( $theme_file );
					if ( $merged_content !== false ) {
						// Generic verification - works for any project's bespoke CPTs
						// Count register_post_type calls (generic pattern)
						$cpt_count = preg_match_all( '/register_post_type\s*\(/i', $merged_content );
						
						// Check for add_action('init', ...) patterns (generic)
						$has_init_action = preg_match( '/add_action\s*\(\s*[\'"]init[\'"]\s*,/i', $merged_content ) === 1;
						
						// Extract found CPT slugs for debugging (if project_slug available, optionally filter by prefix)
						$found_cpt_slugs = array();
						if ( preg_match_all( '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $merged_content, $cpt_matches ) ) {
							$found_cpt_slugs = array_unique( $cpt_matches[1] );
							// Optionally filter by project prefix if project_slug is available
							if ( ! empty( $project_slug ) ) {
								$prefixed_cpts = array_filter( $found_cpt_slugs, function( $slug ) use ( $project_slug ) {
									return strpos( $slug, $project_slug . '_' ) === 0;
								} );
								$found_cpt_slugs = array_values( $prefixed_cpts );
							}
						}
						
						$cpt_verification = array(
							'file_exists' => true,
							'contains_register_post_type' => strpos( $merged_content, 'register_post_type' ) !== false,
							'register_post_type_count' => $cpt_count,
							'contains_add_action_init' => $has_init_action,
							'found_cpt_slugs' => $found_cpt_slugs,
							'project_slug' => $project_slug,
						);
						Logger::info( 'CPT verification after merge', $cpt_verification, $project_slug ?: 'vibecode-deploy' );
						
						// CRITICAL: Manually trigger CPT registration immediately after writing functions.php
						// WordPress has already loaded functions.php and fired 'init', so we need to manually
						// include the file and call the registration functions to register CPTs immediately
						if ( $cpt_count > 0 ) {
							// Try to find and call the CPT registration function directly
							// Pattern: function {project}_register_post_types() or similar
							$registration_function_pattern = '/function\s+([a-z0-9_]+register_post_types?)\s*\(/i';
							if ( preg_match( $registration_function_pattern, $merged_content, $func_match ) ) {
								$registration_function = $func_match[1];
								
								// CRITICAL: Check if function exists before trying to call it
								// WordPress loads functions.php automatically, so the function should already be available
								// We do NOT re-include the file as that would cause "Cannot redeclare" fatal errors
								
								// Check if function already exists
								if ( function_exists( $registration_function ) ) {
									// Function exists - just call it directly
									// No need to include the file again (WordPress already loaded it)
									$registration_function();
									Logger::info( 'Manually triggered CPT registration function', array(
										'function_name' => $registration_function,
										'cpt_count' => $cpt_count,
									), $project_slug ?: 'vibecode-deploy' );
									
									// CRITICAL: Verify CPTs are registered and have data
								// Check that CPTs are actually queryable and have proper settings
								global $wp_post_types;
								if ( isset( $wp_post_types ) && ! empty( $project_slug ) ) {
									$prefixed_cpts = array();
									foreach ( $wp_post_types as $post_type => $post_type_obj ) {
										if ( strpos( $post_type, $project_slug . '_' ) === 0 ) {
											// Ensure show_ui is explicitly set to true
											if ( ! isset( $post_type_obj->show_ui ) || $post_type_obj->show_ui !== true ) {
												$wp_post_types[ $post_type ]->show_ui = true;
											}
											// Note: show_in_menu will be set by the menu function filter
											// Don't override it here - let the filter handle nesting
											$prefixed_cpts[ $post_type ] = array(
												'slug' => $post_type,
												'show_ui' => $wp_post_types[ $post_type ]->show_ui ?? false,
												'show_in_menu' => $wp_post_types[ $post_type ]->show_in_menu ?? false,
												'public' => $wp_post_types[ $post_type ]->public ?? false,
											);
										}
									}
									
									if ( ! empty( $prefixed_cpts ) ) {
										Logger::info( 'CPTs registered and verified', array(
											'cpt_count' => count( $prefixed_cpts ),
											'cpt_details' => $prefixed_cpts,
										), $project_slug ?: 'vibecode-deploy' );
										
										// Verify CPTs are queryable by testing a simple query
										foreach ( array_keys( $prefixed_cpts ) as $cpt_slug ) {
											$test_query = new \WP_Query( array(
												'post_type' => $cpt_slug,
												'posts_per_page' => 1,
												'no_found_rows' => true,
											) );
											Logger::info( 'CPT query test', array(
												'cpt_slug' => $cpt_slug,
												'query_worked' => $test_query instanceof \WP_Query,
												'post_count' => $test_query->post_count ?? 0,
											), $project_slug ?: 'vibecode-deploy' );
										}
									}
								}
								} else {
									// Function doesn't exist - this shouldn't happen if functions.php was written correctly
									// WordPress should have loaded it automatically
									Logger::warning( 'CPT registration function not found - may need page refresh', array(
										'function_name' => $registration_function,
										'theme_file' => $theme_file,
										'file_exists' => file_exists( $theme_file ),
									), $project_slug ?: 'vibecode-deploy' );
								}
							} else {
								// Fallback: Try to trigger init hooks manually for anonymous functions
								// This is less reliable but may work for anonymous function registrations
								Logger::info( 'No named CPT registration function found, CPTs will register on next page load', array(
									'cpt_count' => $cpt_count,
								), $project_slug ?: 'vibecode-deploy' );
							}
						}
						
						// Verify CPTs are actually registered in WordPress (generic check)
						if ( function_exists( 'get_post_types' ) ) {
							$registered_cpts = get_post_types( array( 'public' => true ), 'names' );
							$all_registered_cpts = get_post_types( array(), 'names' );
							
							// Generic: Count how many CPTs with project prefix are registered (if project_slug available)
							$prefixed_registered_count = 0;
							if ( ! empty( $project_slug ) ) {
								$prefixed_registered_count = count( array_filter( $all_registered_cpts, function( $slug ) use ( $project_slug ) {
									return strpos( $slug, $project_slug . '_' ) === 0;
								} ) );
							}
							
							$cpt_registration_check = array(
								'registered_cpt_count' => count( $registered_cpts ),
								'all_registered_cpt_count' => count( $all_registered_cpts ),
								'prefixed_cpt_count' => $prefixed_registered_count,
								'project_slug' => $project_slug,
								'all_registered_cpts' => array_values( $all_registered_cpts ),
							);
							Logger::info( 'CPT registration check in WordPress', $cpt_registration_check, $project_slug ?: 'vibecode-deploy' );
						} else {
							Logger::warning( 'get_post_types() not available during deployment - CPTs will be registered on next page load', array(), $project_slug ?: 'vibecode-deploy' );
						}
					}
				}
				
				if ( $merge_result['created'] ) {
					$results['created'][] = 'functions.php';
				} else {
					$results['updated'][] = 'functions.php';
				}
				// Remove backup file if merge was successful
				if ( file_exists( $backup_file ) ) {
					@unlink( $backup_file );
				}
			} else {
				$results['errors'][] = 'functions.php: ' . $merge_result['error'];
				// If merge failed and backup exists, restore it
				if ( file_exists( $backup_file ) && isset( $results['snapshots']['functions.php'] ) ) {
					file_put_contents( $theme_file, $results['snapshots']['functions.php'] );
					$project_slug = Settings::get_all()['project_slug'] ?? '';
					Logger::info( 'Restored functions.php from backup after failed merge.', array(), $project_slug );
				}
			}
		}

		// Deploy ACF JSON files
		if ( $deploy_acf ) {
			$staging_acf_dir = $staging_theme_dir . '/acf-json';
			$theme_acf_dir = $theme_dir . '/acf-json';
			if ( is_dir( $staging_acf_dir ) ) {
				// Capture snapshots before deployment
				if ( is_dir( $theme_acf_dir ) ) {
					$json_files = glob( $theme_acf_dir . '/*.json' ) ?: array();
					foreach ( $json_files as $json_file ) {
						$filename = basename( $json_file );
						$content = file_get_contents( $json_file );
						if ( $content !== false ) {
							if ( ! isset( $results['snapshots']['acf-json'] ) ) {
								$results['snapshots']['acf-json'] = array();
							}
							$results['snapshots']['acf-json'][ $filename ] = $content;
						}
					}
				}

				$acf_result = self::copy_acf_json_files( $staging_acf_dir, $theme_acf_dir );
				$results['created'] = array_merge( $results['created'], $acf_result['created'] );
				$results['updated'] = array_merge( $results['updated'], $acf_result['updated'] );
				$results['errors'] = array_merge( $results['errors'], $acf_result['errors'] );
			}
		}

		// Flush rewrite rules if functions.php was deployed (CPTs may have changed)
		if ( $deploy_functions && ( ! empty( $results['created'] ) || ! empty( $results['updated'] ) ) ) {
			flush_rewrite_rules( false ); // Soft flush (faster)
			Logger::info( 'Flushed rewrite rules after functions.php deployment', array(
				'created' => in_array( 'functions.php', $results['created'], true ),
				'updated' => in_array( 'functions.php', $results['updated'], true ),
			), $project_slug ?: 'vibecode-deploy' );
			
			// Force WordPress to reload functions.php by clearing opcode cache if available
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset();
				Logger::info( 'Cleared opcode cache to force functions.php reload', array(), $project_slug ?: 'vibecode-deploy' );
			}
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
		// Get project_slug for logging (plugin is agnostic - works with any project)
		$project_slug = Settings::get_all()['project_slug'] ?? '';
		
		$staging_content = file_get_contents( $staging_file );
		if ( $staging_content === false ) {
			return array( 'success' => false, 'error' => 'Unable to read staging functions.php' );
		}
		
		// Validate staging file syntax before merging
		$staging_syntax = self::validate_php_syntax( $staging_content );
		if ( ! $staging_syntax['valid'] ) {
			Logger::error( 'Staging functions.php has syntax errors. Cannot merge.', array(
				'error' => $staging_syntax['error'],
				'staging_file' => $staging_file,
			), $project_slug ?: 'vibecode-deploy' );
			return array( 'success' => false, 'error' => 'Staging functions.php has syntax errors: ' . $staging_syntax['error'] );
		}

		$theme_content = '';
		$created = false;
		if ( file_exists( $theme_file ) ) {
			$theme_content = file_get_contents( $theme_file );
			if ( $theme_content === false ) {
				return array( 'success' => false, 'error' => 'Unable to read theme functions.php' );
			}
			
			// Validate existing theme file syntax before merging
			$theme_syntax = self::validate_php_syntax( $theme_content );
			if ( ! $theme_syntax['valid'] ) {
				Logger::error( 'Existing theme functions.php has syntax errors. Cannot merge safely.', array(
					'error' => $theme_syntax['error'],
					'theme_file' => $theme_file,
				), $project_slug ?: 'vibecode-deploy' );
				return array( 'success' => false, 'error' => 'Existing theme functions.php has syntax errors: ' . $theme_syntax['error'] . ' Please fix manually before deploying.' );
			}
		} else {
			$created = true;
			// Start with basic PHP opening tag if file doesn't exist
			$theme_content = "<?php\n\n";
		}
		
		Logger::info( 'Starting functions.php smart merge.', array(
			'staging_file' => $staging_file,
			'theme_file' => $theme_file,
			'created' => $created,
			'staging_size' => strlen( $staging_content ),
			'theme_size' => strlen( $theme_content ),
		), $project_slug ?: 'vibecode-deploy' );

		// Extract CPT registrations from staging
		$staging_cpts = self::extract_cpt_registrations( $staging_content );
		Logger::info( 'CPT extraction result', array(
			'has_init_block' => ! empty( $staging_cpts['init_block'] ),
			'init_block_length' => strlen( $staging_cpts['init_block'] ?? '' ),
			'individual_count' => count( $staging_cpts['individual'] ?? array() ),
		), $project_slug ?: 'vibecode-deploy' );
		$staging_shortcodes = self::extract_shortcode_registrations( $staging_content );
		$staging_acf_filters = self::extract_acf_filters( $staging_content );
		$staging_helper_functions = self::extract_helper_functions( $staging_content, $project_slug );

		// Merge helper functions first (they may be used by shortcodes)
		$theme_content = self::merge_helper_functions( $theme_content, $staging_helper_functions );
		
		// Log extracted helper functions for debugging (especially shortcode rendering filters)
		if ( ! empty( $staging_helper_functions ) ) {
			$helper_function_names = array_keys( $staging_helper_functions );
			$shortcode_filter_functions = array_filter( $helper_function_names, function( $name ) {
				return strpos( $name, 'ensure_shortcode' ) !== false;
			} );
			if ( ! empty( $shortcode_filter_functions ) ) {
				Logger::info( 'Extracted shortcode rendering filter functions', array(
					'functions' => $shortcode_filter_functions,
					'total_helpers' => count( $staging_helper_functions ),
				), $project_slug ?: 'vibecode-deploy' );
			}
		}
		
		// Validate after helper functions merge
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			Logger::error( 'PHP syntax error after helper functions merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
				'step' => 'merge_helper_functions',
			), $project_slug ?: 'vibecode-deploy' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after helper functions merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}

		// Merge CPTs (remove show_in_menu from staging so filter can set it)
		$theme_content = self::merge_cpt_registrations( $theme_content, $staging_cpts, $project_slug );
		
		// Validate after CPT merge
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			Logger::error( 'PHP syntax error after CPT merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
				'step' => 'merge_cpt_registrations',
				'content_preview' => substr( $theme_content, 0, 500 ),
				'content_length' => strlen( $theme_content ),
			), $project_slug ?: 'vibecode-deploy' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after CPT merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}
		
		// Generic verification - check for register_post_type pattern (works for any project)
		$cpt_count = preg_match_all( '/register_post_type\s*\(/i', $theme_content );
		Logger::info( 'CPT merge completed successfully, syntax valid', array(
			'content_length' => strlen( $theme_content ),
			'register_post_type_count' => $cpt_count,
			'contains_register_post_type' => $cpt_count > 0,
		), $project_slug ?: 'vibecode-deploy' );

		// Merge shortcodes
		$before_shortcode_merge = strlen( $theme_content );
		$theme_content = self::merge_shortcode_registrations( $theme_content, $staging_shortcodes );
		$after_shortcode_merge = strlen( $theme_content );
		
		Logger::info( 'Shortcode merge completed', array(
			'content_length_before' => $before_shortcode_merge,
			'content_length_after' => $after_shortcode_merge,
			'shortcodes_count' => count( $staging_shortcodes ),
		), $project_slug ?: 'vibecode-deploy' );
		
		// Validate after shortcode merge
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			// Log detailed error information
			$error_line = 0;
			if ( preg_match( '/line (\d+)/', $syntax_check['error'], $line_match ) ) {
				$error_line = (int) $line_match[1];
			}
			$lines = explode( "\n", $theme_content );
			$context_start = max( 0, $error_line - 5 );
			$context_end = min( count( $lines ), $error_line + 5 );
			$context_lines = array_slice( $lines, $context_start, $context_end - $context_start );
			
			Logger::error( 'PHP syntax error after shortcode merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
				'step' => 'merge_shortcode_registrations',
				'error_line' => $error_line,
				'context_lines' => $context_lines,
				'content_length' => strlen( $theme_content ),
				'content_preview_around_error' => $error_line > 0 ? implode( "\n", $context_lines ) : substr( $theme_content, 0, 1000 ),
			), $project_slug ?: 'vibecode-deploy' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after shortcode merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}

		// Ensure ACF JSON filters exist
		$theme_content = self::ensure_acf_filters( $theme_content, $staging_acf_filters );
		
		// Add menu creation function to organize CPTs under parent menu (if CPTs exist)
		$theme_content = self::ensure_cpt_menu_structure( $theme_content, $project_slug );
		
		// Validate after ACF filters
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			Logger::error( 'PHP syntax error after ACF filters merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
				'step' => 'ensure_acf_filters',
			), $project_slug ?: 'vibecode-deploy' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after ACF filters merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}

		// CRITICAL: Final validation after ALL merges are complete
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			Logger::error( 'PHP syntax error detected after functions.php merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
				'step' => 'final_validation',
			), $project_slug ?: 'vibecode-deploy' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}

		// Write merged content (only if syntax is valid)
		$write_result = file_put_contents( $theme_file, $theme_content );
		if ( $write_result === false ) {
			Logger::error( 'Failed to write merged functions.php', array(
				'theme_file' => $theme_file,
			) );
			return array( 'success' => false, 'error' => 'Unable to write theme functions.php' );
		}
		
		// Generic verification - check for register_post_type and add_action('init') patterns
		$cpt_count = preg_match_all( '/register_post_type\s*\(/i', $theme_content );
		$has_init_action = preg_match( '/add_action\s*\(\s*[\'"]init[\'"]\s*,/i', $theme_content ) === 1;
		
		Logger::info( 'Successfully wrote merged functions.php', array(
			'theme_file' => $theme_file,
			'bytes_written' => $write_result,
			'final_content_length' => strlen( $theme_content ),
			'register_post_type_count' => $cpt_count,
			'contains_register_post_type' => $cpt_count > 0,
			'contains_add_action_init' => $has_init_action,
		), $project_slug ?: 'vibecode-deploy' );

		// Double-check the written file has valid syntax
		$written_check = self::validate_php_syntax_file( $theme_file );
		if ( ! $written_check['valid'] ) {
			Logger::error( 'PHP syntax error in written file. Attempting to restore from backup.', array(
				'error' => $written_check['error'],
				'file' => $theme_file,
			), $project_slug ?: 'vibecode-deploy' );
			// Try to restore from backup if it exists
			$backup_file = $theme_file . '.backup';
			if ( file_exists( $backup_file ) ) {
				copy( $backup_file, $theme_file );
				return array( 
					'success' => false, 
					'error' => 'Written file had syntax errors. Restored from backup. Original error: ' . $written_check['error'] 
				);
			}
			return array( 
				'success' => false, 
				'error' => 'Written file has syntax errors and no backup available: ' . $written_check['error'] 
			);
		}

		return array( 'success' => true, 'created' => $created );
	}

	/**
	 * Extract CPT registration code blocks from functions.php content.
	 *
	 * First tries to extract the entire add_action('init', function() { ... }) block
	 * that contains CPT registrations. If that's not found, extracts individual
	 * register_post_type() calls.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array with 'init_block' (full add_action block) and/or 'individual' (array of slug => code).
	 */
	private static function extract_cpt_registrations( string $content ): array {
		$result = array(
			'init_block' => '',
			'individual' => array(),
		);
		
		Logger::info( 'Extracting CPT registrations from staging functions.php', array(
			'content_length' => strlen( $content ),
		) );
		
		// First, try to extract the entire add_action('init', function() { ... }) block containing CPTs
		// Look for add_action('init', function() { ... }) that contains register_post_type
		$init_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*function\s*\([^)]*\)\s*\{/';
		$pos = 0;
		while ( preg_match( $init_pattern, $content, $init_match, PREG_OFFSET_CAPTURE, $pos ) ) {
			$init_start = $init_match[0][1];
			$brace_start = strpos( $content, '{', $init_start );
			
			if ( $brace_start !== false ) {
				// Balance braces to find the closing brace of the closure
				$brace_count = 1;
				$search_pos = $brace_start + 1;
				$brace_end = false;
				
				while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
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
				
				if ( $brace_end !== false ) {
					// Find closing parenthesis and semicolon
					$close_paren = strpos( $content, ')', $brace_end );
					if ( $close_paren !== false ) {
						$semicolon = strpos( $content, ';', $close_paren );
						if ( $semicolon !== false ) {
							$full_block = substr( $content, $init_start, $semicolon - $init_start + 1 );
							// Check if this block contains register_post_type
							if ( strpos( $full_block, 'register_post_type' ) !== false ) {
								$result['init_block'] = $full_block;
								Logger::info( 'Found anonymous function CPT registration block', array(
									'block_length' => strlen( $full_block ),
									'preview' => substr( $full_block, 0, 100 ) . '...',
								) );
								return $result; // Return early if we found the init block with CPTs
							}
						}
					}
				}
			}
			// Continue searching for next add_action('init', ...) block
			$pos = $init_start + 1;
		}
		
		// Second, try to extract named function pattern: function_name() + add_action('init', 'function_name')
		// Look for function definitions that contain register_post_type, then find their add_action calls
		$function_def_pattern = '/function\s+([a-z0-9_]+)\s*\([^)]*\)\s*\{/i';
		$pos = 0;
		while ( preg_match( $function_def_pattern, $content, $func_match, PREG_OFFSET_CAPTURE, $pos ) ) {
			$function_name = $func_match[1][0];
			$func_start = $func_match[0][1];
			$brace_start = strpos( $content, '{', $func_start );
			
			if ( $brace_start !== false ) {
				// Balance braces to find the closing brace of the function
				$brace_count = 1;
				$search_pos = $brace_start + 1;
				$brace_end = false;
				
				while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
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
				
				if ( $brace_end !== false ) {
					// Extract the function definition
					$function_block = substr( $content, $func_start, $brace_end - $func_start + 1 );
					
					// Check if this function contains register_post_type
					if ( strpos( $function_block, 'register_post_type' ) !== false ) {
						Logger::info( 'Found function with register_post_type', array(
							'function_name' => $function_name,
							'function_length' => strlen( $function_block ),
						) );
						
						// Find add_action call for this function (search after function definition)
						// Increased window from 200 to 500 to handle comments/whitespace
						$search_after_func = substr( $content, $brace_end + 1, 500 );
						// Improved pattern to handle whitespace variations and comments
						$add_action_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]' . preg_quote( $function_name, '/' ) . '[\'"]\s*\)\s*;/i';
						
						if ( preg_match( $add_action_pattern, $search_after_func, $action_match ) ) {
							$add_action_call = $action_match[0];
							
							Logger::info( 'Found add_action call for named function', array(
								'function_name' => $function_name,
								'add_action_call' => $add_action_call,
							) );
							
							// Combine function definition and add_action call
							// Order: function definition first, then add_action (matching staging structure)
							$full_block = $function_block . "\n" . $add_action_call;
							
							$result['init_block'] = $full_block;
							Logger::info( 'Successfully extracted named function CPT registration block', array(
								'function_name' => $function_name,
								'block_length' => strlen( $full_block ),
								'preview' => substr( $full_block, 0, 150 ) . '...',
							) );
							return $result; // Return early if we found the named function block with CPTs
						} else {
							Logger::warning( 'Function contains register_post_type but add_action call not found', array(
								'function_name' => $function_name,
								'search_window_preview' => substr( $search_after_func, 0, 200 ),
							) );
						}
					}
				}
			}
			
			// Continue searching for next function definition
			$pos = $func_start + 1;
		}
		
		// Fallback: Extract individual register_post_type() calls
		$pattern = '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*array\s*\([^)]*(?:\([^)]*\)[^)]*)*\)\s*\)\s*;/s';
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches as $match ) {
				$slug = $match[1][0];
				$result['individual'][ $slug ] = $match[0][0];
			}
		}
		
		Logger::info( 'CPT extraction complete', array(
			'has_init_block' => ! empty( $result['init_block'] ),
			'init_block_length' => strlen( $result['init_block'] ),
			'individual_count' => count( $result['individual'] ),
			'individual_slugs' => array_keys( $result['individual'] ),
		) );
		
		return $result;
	}

	/**
	 * Extract shortcode registration code blocks from functions.php content.
	 *
	 * First tries to extract named function pattern (function + add_action).
	 * Falls back to extracting individual add_shortcode() calls.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array with 'init_block' (function + add_action) and/or individual shortcode registrations.
	 */
	private static function extract_shortcode_registrations( string $content ): array {
		$result = array(
			'init_block' => '',
			'individual' => array(),
		);
		
		// First, try to extract named function pattern: function {project}_register_shortcodes() { ... } + add_action
		// Look for function definitions that contain add_shortcode, then find their add_action calls
		$function_def_pattern = '/function\s+([a-z0-9_]+)\s*\([^)]*\)\s*\{/i';
		$pos = 0;
		while ( preg_match( $function_def_pattern, $content, $func_match, PREG_OFFSET_CAPTURE, $pos ) ) {
			$function_name = $func_match[1][0];
			$func_start = $func_match[0][1];
			$brace_start = strpos( $content, '{', $func_start );
			
			if ( $brace_start !== false ) {
				// Balance braces to find the closing brace of the function
				$brace_count = 1;
				$search_pos = $brace_start + 1;
				$brace_end = false;
				
				while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
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
				
				if ( $brace_end !== false ) {
					// Extract the function definition
					$function_block = substr( $content, $func_start, $brace_end - $func_start + 1 );
					
					// Check if this function contains add_shortcode
					if ( strpos( $function_block, 'add_shortcode' ) !== false ) {
						Logger::info( 'Found function with add_shortcode', array(
							'function_name' => $function_name,
							'function_length' => strlen( $function_block ),
						) );
						
						// Find add_action call for this function (search after function definition)
						$search_after_func = substr( $content, $brace_end + 1, 500 );
						$add_action_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]' . preg_quote( $function_name, '/' ) . '[\'"]\s*\)\s*;/i';
						
						if ( preg_match( $add_action_pattern, $search_after_func, $action_match ) ) {
							$add_action_call = $action_match[0];
							
							Logger::info( 'Found add_action call for shortcode function', array(
								'function_name' => $function_name,
								'add_action_call' => $add_action_call,
							) );
							
							// Combine function definition and add_action call
							$full_block = $function_block . "\n" . $add_action_call;
							
							$result['init_block'] = $full_block;
							Logger::info( 'Successfully extracted named function shortcode registration block', array(
								'function_name' => $function_name,
								'block_length' => strlen( $full_block ),
							) );
							return $result; // Return early if we found the named function block
						}
					}
				}
			}
			
			// Continue searching for next function definition
			$pos = $func_start + 1;
		}
		
		// Fallback: Extract individual add_shortcode() calls
		$shortcodes = array();
		$pos = 0;
		
		while ( ( $add_pos = strpos( $content, 'add_shortcode', $pos ) ) !== false ) {
			// Find opening parenthesis
			$open_paren = strpos( $content, '(', $add_pos );
			if ( $open_paren === false ) {
				break;
			}
			
			// Find tag (first quoted string) - use PREG_OFFSET_CAPTURE to get position
			if ( ! preg_match( '/[\'"]([^\'"]+)[\'"]/', $content, $tag_match, PREG_OFFSET_CAPTURE, $open_paren ) ) {
				$pos = $add_pos + 1;
				continue;
			}
			$tag = $tag_match[1][0];
			$tag_end_pos = $tag_match[0][1] + strlen( $tag_match[0][0] );
			
			// Find comma after tag
			$comma_pos = strpos( $content, ',', $tag_end_pos );
			if ( $comma_pos === false ) {
				$pos = $add_pos + 1;
				continue;
			}
			
			// Find function keyword
			$func_pos = strpos( $content, 'function', $comma_pos );
			if ( $func_pos === false ) {
				$pos = $add_pos + 1;
				continue;
			}
			
			// Find opening brace of function
			$brace_start = strpos( $content, '{', $func_pos );
			if ( $brace_start === false ) {
				$pos = $add_pos + 1;
				continue;
			}
			
			// Balance braces to find closing brace
			$brace_count = 1;
			$search_pos = $brace_start + 1;
			$brace_end = false;
			
			while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
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
				$pos = $add_pos + 1;
				continue;
			}
			
			// Find closing parenthesis and semicolon
			$close_paren = strpos( $content, ')', $brace_end );
			if ( $close_paren === false ) {
				$pos = $add_pos + 1;
				continue;
			}
			$semicolon = strpos( $content, ';', $close_paren );
			if ( $semicolon === false ) {
				$pos = $add_pos + 1;
				continue;
			}
			
			// Extract full block
			$full_block = substr( $content, $add_pos, $semicolon - $add_pos + 1 );
			$shortcodes[ $tag ] = $full_block;
			
			// Move past this match
			$pos = $semicolon + 1;
		}
		
		// Store individual shortcodes in result
		$result['individual'] = $shortcodes;
		
		Logger::info( 'Shortcode extraction complete', array(
			'has_init_block' => ! empty( $result['init_block'] ),
			'init_block_length' => strlen( $result['init_block'] ),
			'individual_count' => count( $result['individual'] ),
		) );
		
		return $result;
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
	 * If staging has an add_action('init', ...) block, replaces the entire block.
	 * Otherwise, merges individual register_post_type() calls.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_cpts CPT registrations from staging (with 'init_block' and/or 'individual' keys).
	 * @param string $project_slug Project slug for removing conflicting show_in_menu settings.
	 * @return string Merged content.
	 */
	private static function merge_cpt_registrations( string $theme_content, array $staging_cpts, string $project_slug = '' ): string {
		// Get project_slug for logging (plugin is agnostic - works with any project)
		$project_slug = Settings::get_all()['project_slug'] ?? '';
		
		$initial_length = strlen( $theme_content );
		
		Logger::info( 'Starting CPT registration merge', array(
			'has_init_block' => ! empty( $staging_cpts['init_block'] ),
			'init_block_length' => strlen( $staging_cpts['init_block'] ?? '' ),
			'individual_count' => count( $staging_cpts['individual'] ?? array() ),
			'theme_content_length' => $initial_length,
		), $project_slug ?: 'vibecode-deploy' );
		
		// If we have an init block, replace the entire add_action('init', ...) block
		if ( ! empty( $staging_cpts['init_block'] ) ) {
			// Check if staging block is a named function (contains function definition + add_action)
			$is_named_function = preg_match( '/function\s+([a-z0-9_]+)\s*\(/i', $staging_cpts['init_block'], $staging_func_match );
			
			Logger::info( 'CPT merge: init block detected', array(
				'is_named_function' => (bool) $is_named_function,
				'function_name' => $is_named_function ? $staging_func_match[1] : null,
			) );
			
			if ( $is_named_function ) {
				// Handle named function pattern: remove function definition and add_action call
				$staging_function_name = $staging_func_match[1];
				
				Logger::info( 'CPT merge: Processing named function', array(
					'function_name' => $staging_function_name,
				) );
				
				// Remove existing function definition
				$function_pattern = '/function\s+' . preg_quote( $staging_function_name, '/' ) . '\s*\([^)]*\)\s*\{/';
				$pos = 0;
				while ( preg_match( $function_pattern, $theme_content, $func_match, PREG_OFFSET_CAPTURE, $pos ) ) {
					$func_start = $func_match[0][1];
					$brace_start = strpos( $theme_content, '{', $func_start );
					
					if ( $brace_start !== false ) {
						// Balance braces to find the closing brace
						$brace_count = 1;
						$search_pos = $brace_start + 1;
						$brace_end = false;
						
						while ( $search_pos < strlen( $theme_content ) && $brace_count > 0 ) {
							$char = $theme_content[ $search_pos ];
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
						
						if ( $brace_end !== false ) {
							// Check if this function contains register_post_type
							$function_block = substr( $theme_content, $func_start, $brace_end - $func_start + 1 );
							if ( strpos( $function_block, 'register_post_type' ) !== false ) {
								// Remove the function definition
								$before = substr( $theme_content, 0, $func_start );
								$after = substr( $theme_content, $brace_end + 1 );
								$theme_content = $before . $after;
								Logger::info( 'CPT merge: Removed existing function definition', array(
									'function_name' => $staging_function_name,
									'removed_length' => strlen( $function_block ),
								) );
								// Continue searching from the same position
								$pos = $func_start;
								continue;
							}
						}
					}
					$pos = $func_start + 1;
				}
				
				// Remove existing add_action('init', 'function_name') calls
				$action_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]' . preg_quote( $staging_function_name, '/' ) . '[\'"]\s*\)\s*;/i';
				$before_action_remove = strlen( $theme_content );
				$theme_content = preg_replace( $action_pattern, '', $theme_content );
				$after_action_remove = strlen( $theme_content );
				if ( $before_action_remove !== $after_action_remove ) {
					Logger::info( 'CPT merge: Removed existing add_action call', array(
						'function_name' => $staging_function_name,
						'removed_length' => $before_action_remove - $after_action_remove,
					) );
				}
			} else {
				// Handle anonymous function pattern: remove existing add_action('init', function() { ... }) blocks
				$init_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*function\s*\([^)]*\)\s*\{/';
				$pos = 0;
			
			while ( preg_match( $init_pattern, $theme_content, $match, PREG_OFFSET_CAPTURE, $pos ) ) {
				$init_start = $match[0][1];
				$brace_start = strpos( $theme_content, '{', $init_start );
				
				if ( $brace_start !== false ) {
					// Balance braces to find the closing brace
					$brace_count = 1;
					$search_pos = $brace_start + 1;
					$brace_end = false;
					
					while ( $search_pos < strlen( $theme_content ) && $brace_count > 0 ) {
						$char = $theme_content[ $search_pos ];
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
					
					if ( $brace_end !== false ) {
						$close_paren = strpos( $theme_content, ')', $brace_end );
						if ( $close_paren !== false ) {
							$semicolon = strpos( $theme_content, ';', $close_paren );
							if ( $semicolon !== false ) {
								$block_content = substr( $theme_content, $init_start, $semicolon - $init_start + 1 );
								// Only remove if it contains register_post_type
								if ( strpos( $block_content, 'register_post_type' ) !== false ) {
									$before = substr( $theme_content, 0, $init_start );
									$after = substr( $theme_content, $semicolon + 1 );
									$theme_content = $before . $after;
									// Continue searching from the same position
									$pos = $init_start;
									continue;
								}
							}
						}
					}
				}
				$pos = $init_start + 1;
				}
			}
			
			// Remove show_in_menu from staging block so filter can set it
			// This prevents conflicts where staging has show_in_menu => true but filter wants to set parent menu slug
			$staging_block = $staging_cpts['init_block'];
			if ( ! empty( $project_slug ) ) {
				// Remove show_in_menu settings from CPT registrations in staging block
				// Pattern: 'show_in_menu' => true, or "show_in_menu" => true,
				$staging_block = preg_replace( "/['\"]show_in_menu['\"]\s*=>\s*(true|false|['\"][^'\"]+['\"])\s*,?\s*/i", '', $staging_block );
				// Also remove menu_position as it conflicts with nested menu structure
				$staging_block = preg_replace( "/['\"]menu_position['\"]\s*=>\s*\d+\s*,?\s*/i", '', $staging_block );
			}
			
			// Add the new init block at the end
			$before_add = strlen( $theme_content );
			$theme_content = rtrim( $theme_content ) . "\n\n" . $staging_block . "\n";
			$after_add = strlen( $theme_content );
			
			Logger::info( 'CPT merge: Added new init block', array(
				'block_length' => strlen( $staging_cpts['init_block'] ),
				'content_length_before' => $before_add,
				'content_length_after' => $after_add,
				'block_preview' => substr( $staging_cpts['init_block'], 0, 150 ) . '...',
			) );
			
			return $theme_content;
		}
		
		// Fallback: Handle individual register_post_type() calls
		$individual_cpts = $staging_cpts['individual'] ?? array();
		foreach ( $individual_cpts as $slug => $registration_code ) {
			// Remove ALL existing registrations for this post type (handle duplicates)
			// CRITICAL: Only remove registrations at top level, not inside closures/functions
			// Use balanced brace/parenthesis matching to correctly match multi-line registrations
			$pos = 0;
			$removed_count = 0;
			
			while ( ( $match_pos = strpos( $theme_content, 'register_post_type', $pos ) ) !== false ) {
				// Check if this is the post type we're looking for
				$slug_pattern = '/register_post_type\s*\(\s*[\'"]' . preg_quote( $slug, '/' ) . '[\'"]\s*,/';
				if ( ! preg_match( $slug_pattern, substr( $theme_content, $match_pos, 200 ) ) ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// CRITICAL: Check if this registration is inside a closure or function
				// Count braces/parentheses backwards from match_pos to see if we're inside a function/closure
				$before_match = substr( $theme_content, 0, $match_pos );
				$brace_count = 0;
				$paren_count = 0;
				$in_function = false;
				$in_closure = false;
				
				// Scan backwards to find if we're inside a function or closure
				for ( $i = strlen( $before_match ) - 1; $i >= 0; $i-- ) {
					$char = $before_match[ $i ];
					if ( $char === '}' ) {
						$brace_count++;
					} elseif ( $char === '{' ) {
						$brace_count--;
						if ( $brace_count < 0 ) {
							// We're inside a function/closure - DON'T remove this registration
							$in_function = true;
							break;
						}
					} elseif ( $char === ')' ) {
						$paren_count++;
					} elseif ( $char === '(' ) {
						$paren_count--;
						if ( $paren_count < 0 ) {
							// Check if this is a function/closure definition
							$before_paren = substr( $before_match, max( 0, $i - 20 ), 20 );
							if ( preg_match( '/(function|=>)\s*$/', $before_paren ) ) {
								$in_closure = true;
								break;
							}
						}
					}
				}
				
				// Skip if inside a function or closure
				if ( $in_function || $in_closure ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Find the opening parenthesis
				$open_paren = strpos( $theme_content, '(', $match_pos );
				if ( $open_paren === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Find the opening brace of the array argument
				$array_start = strpos( $theme_content, 'array', $open_paren );
				if ( $array_start === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Find the opening parenthesis of array()
				$array_paren = strpos( $theme_content, '(', $array_start );
				if ( $array_paren === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Balance parentheses to find the closing parenthesis of array()
				$paren_count = 1;
				$search_pos = $array_paren + 1;
				$array_close = false;
				
				while ( $search_pos < strlen( $theme_content ) && $paren_count > 0 ) {
					$char = $theme_content[ $search_pos ];
					if ( $char === '(' ) {
						$paren_count++;
					} elseif ( $char === ')' ) {
						$paren_count--;
						if ( $paren_count === 0 ) {
							$array_close = $search_pos;
							break;
						}
					}
					$search_pos++;
				}
				
				if ( $array_close === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Find the closing parenthesis of register_post_type() and semicolon
				$func_close = strpos( $theme_content, ')', $array_close );
				if ( $func_close === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				$semicolon = strpos( $theme_content, ';', $func_close );
				if ( $semicolon === false ) {
					$pos = $match_pos + 1;
					continue;
				}
				
				// Find the start of the line containing this registration (for clean removal)
				$line_start = $match_pos;
				while ( $line_start > 0 && $theme_content[ $line_start - 1 ] !== "\n" ) {
					$line_start--;
				}
				
				// Find the end of the line after the semicolon
				$line_end = $semicolon + 1;
				while ( $line_end < strlen( $theme_content ) && $theme_content[ $line_end ] !== "\n" ) {
					$line_end++;
				}
				if ( $line_end < strlen( $theme_content ) ) {
					$line_end++; // Include the newline
				}
				
				// Remove this registration (entire line)
				$before = substr( $theme_content, 0, $line_start );
				$after = substr( $theme_content, $line_end );
				
				$theme_content = $before . $after;
				$removed_count++;
				// Continue searching from the same position (content shifted)
				$pos = $line_start;
			}
			
			// Add the new registration
			if ( $removed_count > 0 ) {
				$project_slug = Settings::get_all()['project_slug'] ?? '';
				Logger::info( 'Removed duplicate CPT registrations before adding new one.', array(
					'post_type' => $slug,
					'removed_count' => $removed_count,
				), $project_slug ?: 'vibecode-deploy' );
			}
			
			// Add new registration at the end (after all other CPT registrations if any exist)
			if ( strpos( $theme_content, 'register_post_type' ) !== false ) {
				// Find the last register_post_type and add after it
				$last_pos = strrpos( $theme_content, 'register_post_type' );
				if ( $last_pos !== false ) {
					// Find the semicolon after the last registration
					$semicolon_pos = strpos( $theme_content, ';', $last_pos );
					if ( $semicolon_pos !== false ) {
						$before = substr( $theme_content, 0, $semicolon_pos + 1 );
						$after = substr( $theme_content, $semicolon_pos + 1 );
						$theme_content = $before . "\n" . $registration_code . "\n" . $after;
					} else {
						// Fallback: append at end
						$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
					}
				} else {
					// Fallback: append at end
					$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
				}
			} else {
				// No existing registrations, add at end
				$theme_content = rtrim( $theme_content ) . "\n\n" . $registration_code . "\n";
			}
		}
		
		// Note: PHP syntax validation happens at the end of smart_merge_functions_php()
		// after ALL merges are complete, not here after each individual merge step
		
		return $theme_content;
	}
	
	/**
	 * Validate PHP syntax by checking if code can be parsed.
	 *
	 * Uses php -l to validate syntax. This is the most reliable method.
	 *
	 * @param string $php_code PHP code to validate.
	 * @return array Array with 'valid' (bool) and 'error' (string) keys.
	 */
	private static function validate_php_syntax( string $php_code ): array {
		// Use php -l via shell if available
		$temp_file = sys_get_temp_dir() . '/vibecode-deploy-syntax-check-' . uniqid( '', true ) . '.php';
		
		if ( file_put_contents( $temp_file, $php_code ) === false ) {
			$project_slug = Settings::get_all()['project_slug'] ?? '';
			Logger::warning( 'Could not create temp file for PHP syntax validation.', array(), $project_slug );
			// Don't block deployment if we can't validate, but log it
			return array( 'valid' => true, 'error' => 'Could not create temp file for validation' );
		}
		
		// Use php -l to check syntax (most reliable method)
		$output = array();
		$return_var = 0;
		$php_binary = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
		$command = escapeshellarg( $php_binary ) . ' -l ' . escapeshellarg( $temp_file ) . ' 2>&1';
		@exec( $command, $output, $return_var );
		
		// Clean up temp file
		@unlink( $temp_file );
		
		if ( $return_var === 0 ) {
			return array( 'valid' => true, 'error' => '' );
		} else {
			$error = implode( "\n", $output );
			// Extract line number from error if available
			if ( preg_match( '/on line (\d+)/i', $error, $matches ) ) {
				$line_num = (int) $matches[1];
				$error = "Parse error on line {$line_num}: " . $error;
			}
			return array( 'valid' => false, 'error' => trim( $error ) );
		}
	}
	
	/**
	 * Validate PHP syntax of an existing file.
	 *
	 * @param string $file_path Path to PHP file to validate.
	 * @return array Array with 'valid' (bool) and 'error' (string) keys.
	 */
	private static function validate_php_syntax_file( string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return array( 'valid' => false, 'error' => 'File does not exist: ' . $file_path );
		}
		
		// Use php -l to check syntax
		$output = array();
		$return_var = 0;
		$php_binary = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
		$command = escapeshellarg( $php_binary ) . ' -l ' . escapeshellarg( $file_path ) . ' 2>&1';
		@exec( $command, $output, $return_var );
		
		if ( $return_var === 0 ) {
			return array( 'valid' => true, 'error' => '' );
		} else {
			$error = implode( "\n", $output );
			// Extract line number from error if available
			if ( preg_match( '/on line (\d+)/i', $error, $matches ) ) {
				$line_num = (int) $matches[1];
				$error = "Parse error on line {$line_num}: " . $error;
			}
			return array( 'valid' => false, 'error' => trim( $error ) );
		}
	}

	/**
	 * Merge shortcode registrations into theme content.
	 *
	 * If staging has an init block (function + add_action), replaces the entire block.
	 * Otherwise, merges individual add_shortcode() calls.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_shortcodes Shortcode registrations from staging (with 'init_block' and/or 'individual' keys).
	 * @return string Merged content.
	 */
	private static function merge_shortcode_registrations( string $theme_content, array $staging_shortcodes ): string {
		// If we have an init block, replace the entire function + add_action block
		if ( ! empty( $staging_shortcodes['init_block'] ) ) {
			// Check if staging block is a named function (contains function definition + add_action)
			$is_named_function = preg_match( '/function\s+([a-z0-9_]+)\s*\(/i', $staging_shortcodes['init_block'], $staging_func_match );
			
			$project_slug = Settings::get_all()['project_slug'] ?? '';
			Logger::info( 'Shortcode merge: init block detected', array(
				'is_named_function' => (bool) $is_named_function,
				'function_name' => $is_named_function ? $staging_func_match[1] : null,
			), $project_slug ?: 'vibecode-deploy' );
			
			if ( $is_named_function ) {
				// Handle named function pattern: remove function definition and add_action call
				$staging_function_name = $staging_func_match[1];
				
				// Remove existing function definition
				$function_pattern = '/function\s+' . preg_quote( $staging_function_name, '/' ) . '\s*\([^)]*\)\s*\{/';
				$pos = 0;
				while ( preg_match( $function_pattern, $theme_content, $func_match, PREG_OFFSET_CAPTURE, $pos ) ) {
					$func_start = $func_match[0][1];
					$brace_start = strpos( $theme_content, '{', $func_start );
					
					if ( $brace_start !== false ) {
						// Balance braces to find the closing brace
						$brace_count = 1;
						$search_pos = $brace_start + 1;
						$brace_end = false;
						
						while ( $search_pos < strlen( $theme_content ) && $brace_count > 0 ) {
							$char = $theme_content[ $search_pos ];
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
						
						if ( $brace_end !== false ) {
							// Check if this function contains add_shortcode
							$function_block = substr( $theme_content, $func_start, $brace_end - $func_start + 1 );
							if ( strpos( $function_block, 'add_shortcode' ) !== false ) {
								// Remove the function definition
								$before = substr( $theme_content, 0, $func_start );
								$after = substr( $theme_content, $brace_end + 1 );
								$theme_content = $before . $after;
								$project_slug = Settings::get_all()['project_slug'] ?? '';
								Logger::info( 'Shortcode merge: Removed existing function definition', array(
									'function_name' => $staging_function_name,
								), $project_slug ?: 'vibecode-deploy' );
								// Continue searching from the same position
								$pos = $func_start;
								continue;
							}
						}
					}
					$pos = $func_start + 1;
				}
				
				// Remove existing add_action('init', 'function_name') calls
				$action_pattern = '/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]' . preg_quote( $staging_function_name, '/' ) . '[\'"]\s*\)\s*;/i';
				$theme_content = preg_replace( $action_pattern, '', $theme_content );
			}
			
			// Add the new init block at the end
			$theme_content = rtrim( $theme_content ) . "\n\n" . $staging_shortcodes['init_block'] . "\n";
			$project_slug = Settings::get_all()['project_slug'] ?? '';
			Logger::info( 'Shortcode merge: Added new init block', array(
				'block_length' => strlen( $staging_shortcodes['init_block'] ),
			), $project_slug ?: 'vibecode-deploy' );
			return $theme_content;
		}
		
		// Fallback: Handle individual add_shortcode() calls
		$individual_shortcodes = $staging_shortcodes['individual'] ?? array();
		foreach ( $individual_shortcodes as $tag => $registration_code ) {
			// Check if shortcode already exists
			$pattern = '/add_shortcode\s*\(\s*[\'"]' . preg_quote( $tag, '/' ) . '[\'"]\s*,/';
			if ( preg_match( $pattern, $theme_content ) ) {
				// Replace existing shortcode - match multi-line closures
				$theme_content = preg_replace(
					'/add_shortcode\s*\(\s*[\'"]' . preg_quote( $tag, '/' ) . '[\'"]\s*,\s*function\s*\([^)]*\)\s*\{.*?\}\s*\)\s*;/s',
					$registration_code,
					$theme_content,
					1
				);
			} else {
				// Add new shortcode at the end
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
	 * Extract helper functions from functions.php content.
	 *
	 * Extracts standalone functions (not closures) that start with a project prefix
	 * (e.g., 'cfa_', 'bgp_') and are used by shortcodes or CPTs.
	 *
	 * @param string $content Functions.php content.
	 * @param string $project_slug Project slug for matching project-specific prefixes.
	 * @return array Array of function names => function code blocks.
	 */
	private static function extract_helper_functions( string $content, string $project_slug = '' ): array {
		$functions = array();
		$pos = 0;

		// Match function declarations: function function_name($params) { ... }
		// Look for functions that start with common prefixes used by shortcodes
		$pattern = '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/';
		
		// Build prefix pattern including project-specific prefix
		$prefix_pattern = '/^(cfa_|helper_|get_|render_|normalize_|is_|migrate_|format_|days_|ensure_)/';
		if ( ! empty( $project_slug ) ) {
			$prefix_pattern = '/^(' . preg_quote( $project_slug, '/' ) . '_|cfa_|helper_|get_|render_|normalize_|is_|migrate_|format_|days_|ensure_)/';
		}
		
		while ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE, $pos ) ) {
			$func_name = $matches[1][0];
			$func_start = $matches[0][1];
			
			// Only extract functions that start with common prefixes (cfa_, project-specific prefixes)
			// or are explicitly helper functions (contain 'helper', 'get_', 'render_', 'normalize_', 'is_', 'ensure_')
			if ( preg_match( $prefix_pattern, $func_name ) ) {
				// Find opening brace
				$brace_start = strpos( $content, '{', $func_start );
				if ( $brace_start === false ) {
					$pos = $func_start + 1;
					continue;
				}

				// Balance braces to find closing brace
				$brace_count = 1;
				$search_pos = $brace_start + 1;
				$brace_end = false;

				while ( $search_pos < strlen( $content ) && $brace_count > 0 ) {
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

				if ( $brace_end !== false ) {
					// Extract full function including the closing brace
					$func_code = substr( $content, $func_start, $brace_end - $func_start + 1 );
					
					// CRITICAL: Search for add_filter/add_action calls that reference this function
					// These calls appear after the function definition and must be included
					$search_start = $brace_end + 1;
					$search_end = min( $search_start + 1000, strlen( $content ) ); // Look at next ~20 lines
					$after_function = substr( $content, $search_start, $search_end - $search_start );
					
					// Match add_filter or add_action calls with this function name
					// Pattern: add_filter('hook', 'function_name', ...) or add_action('hook', 'function_name', ...)
					$filter_pattern = '/add_(?:filter|action)\s*\(\s*[^,]+,\s*[\'"]' . preg_quote( $func_name, '/' ) . '[\'"]/';
					
					if ( preg_match( $filter_pattern, $after_function, $filter_match, PREG_OFFSET_CAPTURE ) ) {
						// Found add_filter/add_action call - extract it along with any preceding comments
						$filter_match_pos = $filter_match[0][1];
						
						// Find the start of the line containing the filter call
						$line_start = strrpos( substr( $after_function, 0, $filter_match_pos ), "\n" );
						if ( $line_start === false ) {
							$line_start = 0;
						} else {
							$line_start++; // Include the newline
						}
						
						// Find the end of the line (semicolon or newline)
						$line_end = strpos( $after_function, "\n", $filter_match_pos );
						if ( $line_end === false ) {
							$line_end = strlen( $after_function );
						} else {
							$line_end++; // Include the newline
						}
						
						// Check for preceding comments (PHP comment style: // or /* */)
						$before_filter = substr( $after_function, 0, $line_start );
						$comment_pattern = '/(\/\/[^\n]*|\/\*[\s\S]*?\*\/)\s*$/';
						if ( preg_match( $comment_pattern, $before_filter, $comment_match ) ) {
							// Include the comment
							$comment_start = strrpos( $before_filter, "\n", -strlen( $comment_match[0] ) );
							if ( $comment_start !== false ) {
								$line_start = $comment_start + 1;
							}
						}
						
						// Extract the filter call (and comment if found)
						$filter_code = substr( $after_function, $line_start, $line_end - $line_start );
						
						// Append filter call to function code
						$func_code .= "\n" . $filter_code;
						
						// Check for multiple filter calls (less common but possible)
						$remaining = substr( $after_function, $line_end );
						while ( preg_match( $filter_pattern, $remaining, $next_match, PREG_OFFSET_CAPTURE ) ) {
							$next_pos = $next_match[0][1];
							$next_line_start = strrpos( substr( $remaining, 0, $next_pos ), "\n" );
							if ( $next_line_start === false ) {
								$next_line_start = 0;
							} else {
								$next_line_start++;
							}
							$next_line_end = strpos( $remaining, "\n", $next_pos );
							if ( $next_line_end === false ) {
								$next_line_end = strlen( $remaining );
							} else {
								$next_line_end++;
							}
							$next_filter_code = substr( $remaining, $next_line_start, $next_line_end - $next_line_start );
							$func_code .= $next_filter_code;
							$remaining = substr( $remaining, $next_line_end );
						}
					}
					
					$functions[ $func_name ] = $func_code;
					$pos = $brace_end + 1;
				} else {
					$pos = $func_start + 1;
				}
			} else {
				$pos = $func_start + 1;
			}
		}

		return $functions;
	}

	/**
	 * Merge helper functions into theme content.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_functions Array of function names => function code blocks.
	 * @return string Merged content.
	 */
	private static function merge_helper_functions( string $theme_content, array $staging_functions ): string {
		// Get project_slug for logging
		$project_slug = Settings::get_all()['project_slug'] ?? '';
		
		foreach ( $staging_functions as $func_name => $func_code ) {
			// Check if function already exists
			$pattern = '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\(/';
			if ( preg_match( $pattern, $theme_content, $match, PREG_OFFSET_CAPTURE ) ) {
				// Log when replacing existing function (especially shortcode filters)
				if ( strpos( $func_name, 'ensure_shortcode' ) !== false ) {
					Logger::info( 'Replacing existing shortcode rendering function', array(
						'function_name' => $func_name,
						'has_add_filter' => strpos( $func_code, 'add_filter' ) !== false,
					), $project_slug ?: 'vibecode-deploy' );
				}
				// Function exists - find its start and end
				$func_start = $match[0][1];
				$brace_start = strpos( $theme_content, '{', $func_start );
				
				if ( $brace_start !== false ) {
					// Balance braces to find the closing brace
					$brace_count = 1;
					$search_pos = $brace_start + 1;
					$brace_end = false;
					
					while ( $search_pos < strlen( $theme_content ) && $brace_count > 0 ) {
						$char = $theme_content[ $search_pos ];
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
					
					if ( $brace_end !== false ) {
						// Check for add_filter/add_action calls after the function
						$search_start = $brace_end + 1;
						$search_end = min( $search_start + 200, strlen( $theme_content ) );
						$after_function = substr( $theme_content, $search_start, $search_end - $search_start );
						
						// Find all add_filter/add_action calls for this function
						$filter_pattern = '/add_(?:filter|action)\s*\(\s*[^,]+,\s*[\'"]' . preg_quote( $func_name, '/' ) . '[\'"]/';
						$filter_end = $brace_end;
						
						if ( preg_match( $filter_pattern, $after_function, $filter_match, PREG_OFFSET_CAPTURE ) ) {
							// Find the end of the last filter call
							$last_filter_pos = $filter_match[0][1];
							$line_end = strpos( $after_function, "\n", $last_filter_pos );
							if ( $line_end !== false ) {
								$filter_end = $search_start + $line_end;
							} else {
								$filter_end = $search_start + strlen( $after_function );
							}
						}
						
						// Replace function (and any existing filter calls) with new code
						$before = substr( $theme_content, 0, $func_start );
						$after = substr( $theme_content, $filter_end + 1 );
						$theme_content = $before . $func_code . "\n" . $after;
					} else {
						// Fallback: use regex replacement
						$theme_content = preg_replace(
							'/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*\{[^}]*\}/s',
							$func_code,
							$theme_content,
							1
						);
					}
				}
				} else {
				// Add new function before first CPT registration or shortcode
				$insert_pos = strpos( $theme_content, 'register_post_type' );
				if ( $insert_pos === false ) {
					$insert_pos = strpos( $theme_content, 'add_shortcode' );
				}
				if ( $insert_pos === false ) {
					// Add at the end
					$theme_content = rtrim( $theme_content ) . "\n\n" . $func_code . "\n";
				} else {
					// Add before first CPT/shortcode
					$theme_content = substr( $theme_content, 0, $insert_pos ) . $func_code . "\n\n" . substr( $theme_content, $insert_pos );
				}
				
				// Log when adding new function (especially shortcode filters)
				if ( strpos( $func_name, 'ensure_shortcode' ) !== false ) {
					Logger::info( 'Added new shortcode rendering function', array(
						'function_name' => $func_name,
						'has_add_filter' => strpos( $func_code, 'add_filter' ) !== false,
						'code_length' => strlen( $func_code ),
					), $project_slug ?: 'vibecode-deploy' );
				}
			}
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
	 * @return array Result with 'success' (bool), 'created' (bool), 'activated' (bool), 'error' (string|null).
	 */
	private static function ensure_child_theme_exists( string $theme_slug, string $parent_theme = 'etch-theme' ): array {
		$result = array(
			'success' => false,
			'created' => false,
			'activated' => false,
			'error' => null,
		);

		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		$theme_existed = is_dir( $theme_dir );

		// If theme already exists, verify it's a child theme
		if ( $theme_existed ) {
			$style_file = $theme_dir . '/style.css';
			if ( file_exists( $style_file ) ) {
				$style_content = file_get_contents( $style_file );
				if ( strpos( $style_content, 'Template:' ) !== false ) {
					// Theme exists and is a child theme - try to activate it
					$activation_result = self::activate_child_theme( $theme_slug );
					$result['success'] = true;
					$result['activated'] = $activation_result['activated'];
					$result['error'] = $activation_result['error'];
					return $result;
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
			$result['error'] = "Failed to create style.css";
			return $result;
		}

		// Create basic index.php if it doesn't exist
		$index_file = $theme_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			$index_content = "<?php\n// Silence is golden.\n";
			if ( file_put_contents( $index_file, $index_content ) === false ) {
				$result['error'] = "Failed to create index.php";
				return $result;
			}
		}

		$result['success'] = true;
		$result['created'] = ! $theme_existed;

		// Activate the child theme
		$activation_result = self::activate_child_theme( $theme_slug );
		$result['activated'] = $activation_result['activated'];
		if ( $activation_result['error'] !== null ) {
			$result['error'] = $activation_result['error'];
		}

		return $result;
	}

	/**
	 * Activate child theme if not already active.
	 *
	 * @param string $theme_slug Child theme slug.
	 * @return array Result with 'activated' (bool) and 'error' (string|null).
	 */
	private static function activate_child_theme( string $theme_slug ): array {
		$result = array(
			'activated' => false,
			'error' => null,
		);

		// Check if user has permission to switch themes
		if ( ! current_user_can( 'switch_themes' ) ) {
			$result['error'] = 'User does not have permission to switch themes';
			return $result;
		}

		// Check if theme exists
		$theme = wp_get_theme( $theme_slug );
		if ( ! $theme->exists() ) {
			$result['error'] = "Theme does not exist: {$theme_slug}";
			return $result;
		}

		// Check if theme is already active
		$active_theme = wp_get_theme();
		if ( $active_theme->get_stylesheet() === $theme_slug ) {
			$result['activated'] = false; // Already active
			return $result;
		}

		// Switch to the child theme
		$switch_result = switch_theme( $theme_slug );
		if ( is_wp_error( $switch_result ) ) {
			$result['error'] = $switch_result->get_error_message();
			return $result;
		}

		$result['activated'] = true;
		Logger::info( 'Child theme activated.', array( 'theme_slug' => $theme_slug ) );
		return $result;
	}

	/**
	 * Ensure CPT menu structure exists with parent menu item and nested CPTs.
	 *
	 * Creates a parent menu item with uppercase project prefix and nests all CPTs under it.
	 * Also ensures all CPTs have explicit show_ui and show_in_menu settings.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param string $project_slug Project slug (e.g., 'bgp', 'cfa').
	 * @return string Content with menu structure function added.
	 */
	private static function ensure_cpt_menu_structure( string $theme_content, string $project_slug ): string {
		if ( empty( $project_slug ) ) {
			return $theme_content; // Can't create menu without project slug
		}
		
		// Check if CPTs exist in the content
		$cpt_count = preg_match_all( '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $theme_content, $cpt_matches );
		if ( $cpt_count === 0 ) {
			return $theme_content; // No CPTs to organize
		}
		
		// Extract CPT slugs with project prefix
		$cpt_slugs = array();
		foreach ( $cpt_matches[1] as $cpt_slug ) {
			if ( strpos( $cpt_slug, $project_slug . '_' ) === 0 ) {
				$cpt_slugs[] = $cpt_slug;
			}
		}
		
		if ( empty( $cpt_slugs ) ) {
			return $theme_content; // No CPTs with project prefix
		}
		
		// Check if menu creation function already exists
		$menu_function_name = $project_slug . '_create_cpt_menu';
		$menu_function_pattern = '/function\s+' . preg_quote( $menu_function_name, '/' ) . '\s*\(/';
		if ( preg_match( $menu_function_pattern, $theme_content ) ) {
			// Menu function already exists, skip
			return $theme_content;
		}
		
		// Generate parent menu slug (uppercase project prefix)
		$parent_menu_slug = strtoupper( $project_slug );
		$parent_menu_title = strtoupper( $project_slug );
		
		// Generate menu creation function code
		// CRITICAL: We need to create the parent menu FIRST, then modify CPTs to nest under it
		// The parent menu must exist before CPTs can nest under it
		$menu_function_code = "\n\n/**\n * Create Admin Menu Structure for CPTs\n * \n * Creates a parent menu item with uppercase project prefix and nests all CPTs under it.\n * This provides better organization in the WordPress admin menu.\n * \n * Auto-generated by Vibe Code Deploy plugin.\n */\n";
		
		// Function 1: Create parent menu (runs early on admin_menu)
		$parent_menu_function = "function {$menu_function_name}_parent() {\n";
		$parent_menu_function .= "\t\$parent_menu_slug = '" . esc_attr( $parent_menu_slug ) . "';\n";
		$parent_menu_function .= "\t\$parent_menu_title = '" . esc_attr( $parent_menu_title ) . "';\n\n";
		$parent_menu_function .= "\t// Create parent menu item (must exist before CPTs can nest under it)\n";
		$parent_menu_function .= "\tadd_menu_page(\n";
		$parent_menu_function .= "\t\t\$parent_menu_title,\n";
		$parent_menu_function .= "\t\t\$parent_menu_title,\n";
		$parent_menu_function .= "\t\t'manage_options',\n";
		$parent_menu_function .= "\t\t\$parent_menu_slug,\n";
		$parent_menu_function .= "\t\t'',\n";
		$parent_menu_function .= "\t\t'dashicons-admin-generic',\n";
		$parent_menu_function .= "\t\t20 // Position in menu\n";
		$parent_menu_function .= "\t);\n";
		$parent_menu_function .= "}\n";
		$parent_menu_function .= "add_action( 'admin_menu', '{$menu_function_name}_parent', 5 ); // Priority 5 to run early\n\n";
		
		// Function 2: Modify CPTs to nest under parent menu using register_post_type_args filter
		// This filter runs during CPT registration, allowing us to set show_in_menu before registration completes
		$menu_function_code .= $parent_menu_function;
		$menu_function_code .= "function {$menu_function_name}() {\n";
		$menu_function_code .= "\t\$project_slug = '" . esc_attr( $project_slug ) . "';\n";
		$menu_function_code .= "\t\$parent_menu_slug = '" . esc_attr( $parent_menu_slug ) . "';\n\n";
		$menu_function_code .= "\t// Modify CPT registrations to nest under parent menu\n";
		$menu_function_code .= "\t// Use register_post_type_args filter to modify during registration\n";
		$menu_function_code .= "\t\$cpt_slugs = array(";
		foreach ( $cpt_slugs as $cpt_slug ) {
			$menu_function_code .= "\n\t\t'" . esc_attr( $cpt_slug ) . "',";
		}
		$menu_function_code .= "\n\t);\n\n";
		$menu_function_code .= "\tadd_filter( 'register_post_type_args', function( \$args, \$post_type ) use ( \$cpt_slugs, \$parent_menu_slug ) {\n";
		$menu_function_code .= "\t\tif ( in_array( \$post_type, \$cpt_slugs, true ) ) {\n";
		$menu_function_code .= "\t\t\t// Nest CPT under parent menu\n";
		$menu_function_code .= "\t\t\t\$args['show_in_menu'] = \$parent_menu_slug;\n";
		$menu_function_code .= "\t\t\t// Ensure show_ui is true for menu visibility and ACF compatibility\n";
		$menu_function_code .= "\t\t\t\$args['show_ui'] = true;\n";
		$menu_function_code .= "\t\t\t// Ensure publicly_queryable is true for ACF to detect CPT\n";
		$menu_function_code .= "\t\t\tif ( ! isset( \$args['publicly_queryable'] ) ) {\n";
		$menu_function_code .= "\t\t\t\t\$args['publicly_queryable'] = true;\n";
		$menu_function_code .= "\t\t\t}\n";
		$menu_function_code .= "\t\t\t// Ensure show_in_rest is true for ACF compatibility\n";
		$menu_function_code .= "\t\t\tif ( ! isset( \$args['show_in_rest'] ) ) {\n";
		$menu_function_code .= "\t\t\t\t\$args['show_in_rest'] = true;\n";
		$menu_function_code .= "\t\t\t}\n";
		$menu_function_code .= "\t\t}\n";
		$menu_function_code .= "\t\treturn \$args;\n";
		$menu_function_code .= "\t}, 10, 2 );\n";
		$menu_function_code .= "}\n";
		$menu_function_code .= "add_action( 'init', '{$menu_function_name}', 5 ); // Priority 5 to run BEFORE CPT registration (default is 10)\n\n";
		
		// Function 3: Add ACF filter to include non-public CPTs in Post Types settings
		$acf_function_name = $project_slug . '_acf_include_cpts';
		$menu_function_code .= "function {$acf_function_name}( \$post_types, \$args ) {\n";
		$menu_function_code .= "\t// Ensure all project CPTs appear in ACF Post Types settings\n";
		$menu_function_code .= "\t// This is especially important for non-public CPTs (like FAQ)\n";
		$menu_function_code .= "\t\$cpt_slugs = array(";
		foreach ( $cpt_slugs as $cpt_slug ) {
			$menu_function_code .= "\n\t\t'" . esc_attr( $cpt_slug ) . "',";
		}
		$menu_function_code .= "\n\t);\n\n";
		$menu_function_code .= "\tforeach ( \$cpt_slugs as \$cpt_slug ) {\n";
		$menu_function_code .= "\t\tif ( ! in_array( \$cpt_slug, \$post_types, true ) ) {\n";
		$menu_function_code .= "\t\t\t\$post_types[] = \$cpt_slug;\n";
		$menu_function_code .= "\t\t}\n";
		$menu_function_code .= "\t}\n";
		$menu_function_code .= "\treturn \$post_types;\n";
		$menu_function_code .= "}\n";
		$menu_function_code .= "add_filter( 'acf/get_post_types', '{$acf_function_name}', 10, 2 );\n";
		
		// Add menu function BEFORE CPT registration (so it's available when CPTs register)
		// Find the first register_post_type or add_action('init', ...) for CPT registration
		$insert_pos = strpos( $theme_content, "function " . $project_slug . "_register_post_types" );
		if ( $insert_pos === false ) {
			// Fallback: look for add_action('init', ...) for CPT registration
			$insert_pos = strpos( $theme_content, "add_action( 'init', '" . $project_slug . "_register_post_types" );
		}
		if ( $insert_pos === false ) {
			// Fallback: look for any register_post_type
			$insert_pos = strpos( $theme_content, "register_post_type" );
		}
		if ( $insert_pos === false ) {
			// Fallback: add at end before closing PHP tag or at very end
			$theme_content = rtrim( $theme_content ) . $menu_function_code . "\n";
		} else {
			// Find start of line before the CPT registration
			$line_start = strrpos( substr( $theme_content, 0, $insert_pos ), "\n" );
			if ( $line_start === false ) {
				$line_start = 0;
			} else {
				$line_start++; // Include the newline
			}
			$before = substr( $theme_content, 0, $line_start );
			$after = substr( $theme_content, $line_start );
			$theme_content = $before . $menu_function_code . $after;
		}
		
		Logger::info( 'Added CPT menu structure function', array(
			'function_name' => $menu_function_name,
			'parent_menu_slug' => $parent_menu_slug,
			'cpt_count' => count( $cpt_slugs ),
			'cpt_slugs' => $cpt_slugs,
		), $project_slug ?: 'vibecode-deploy' );
		
		return $theme_content;
	}
}
