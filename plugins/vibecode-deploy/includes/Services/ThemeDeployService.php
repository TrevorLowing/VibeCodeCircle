<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

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
	 * @param array  $selected_theme_files Optional array of theme file names to deploy (e.g., 'functions.php', 'acf-json/*.json').
	 * @return array Results with 'created', 'updated', 'errors', 'snapshots' keys.
	 */
	public static function deploy_theme_files( string $build_root, string $theme_slug, array $selected_theme_files = array() ): array {
		$results = array(
			'created' => array(),
			'updated' => array(),
			'errors' => array(),
			'snapshots' => array(), // File snapshots for rollback
		);

		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		$staging_theme_dir = $build_root . '/theme';

		// Ensure child theme exists
		if ( ! self::ensure_child_theme_exists( $theme_slug ) ) {
			$results['errors'][] = "Failed to create or verify child theme: {$theme_slug}";
			return $results;
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
					Logger::info( 'Restored functions.php from backup after failed merge.', array(), 'cfa' );
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
		$staging_helper_functions = self::extract_helper_functions( $staging_content );

		// Merge helper functions first (they may be used by shortcodes)
		$theme_content = self::merge_helper_functions( $theme_content, $staging_helper_functions );

		// Merge CPTs
		$theme_content = self::merge_cpt_registrations( $theme_content, $staging_cpts );

		// Merge shortcodes
		$theme_content = self::merge_shortcode_registrations( $theme_content, $staging_shortcodes );

		// Ensure ACF JSON filters exist
		$theme_content = self::ensure_acf_filters( $theme_content, $staging_acf_filters );

		// CRITICAL: Validate PHP syntax after ALL merges are complete
		$syntax_check = self::validate_php_syntax( $theme_content );
		if ( ! $syntax_check['valid'] ) {
			Logger::error( 'PHP syntax error detected after functions.php merge. File NOT written.', array(
				'error' => $syntax_check['error'],
				'file' => $theme_file,
			), 'cfa' );
			return array( 
				'success' => false, 
				'error' => 'PHP syntax error after merge: ' . $syntax_check['error'] . ' File was NOT written to prevent site breakage.' 
			);
		}

		// Write merged content (only if syntax is valid)
		if ( file_put_contents( $theme_file, $theme_content ) === false ) {
			return array( 'success' => false, 'error' => 'Unable to write theme functions.php' );
		}

		// Double-check the written file has valid syntax
		$written_check = self::validate_php_syntax_file( $theme_file );
		if ( ! $written_check['valid'] ) {
			Logger::error( 'PHP syntax error in written file. Attempting to restore from backup.', array(
				'error' => $written_check['error'],
				'file' => $theme_file,
			), 'cfa' );
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
	 * Uses balanced brace matching to correctly extract multi-line shortcode closures.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array of shortcode tags => registration code blocks.
	 */
	private static function extract_shortcode_registrations( string $content ): array {
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
	 * Removes ALL existing registrations for each post type before adding the new one
	 * to prevent duplicates. Uses balanced brace matching for multi-line registrations.
	 *
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_cpts CPT registrations from staging.
	 * @return string Merged content.
	 */
	private static function merge_cpt_registrations( string $theme_content, array $staging_cpts ): string {
		foreach ( $staging_cpts as $slug => $registration_code ) {
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
				Logger::info( 'Removed duplicate CPT registrations before adding new one.', array(
					'post_type' => $slug,
					'removed_count' => $removed_count,
				), 'cfa' );
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
			Logger::warning( 'Could not create temp file for PHP syntax validation.', array(), 'cfa' );
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
	 * @param string $theme_content Current theme functions.php content.
	 * @param array  $staging_shortcodes Shortcode registrations from staging.
	 * @return string Merged content.
	 */
	private static function merge_shortcode_registrations( string $theme_content, array $staging_shortcodes ): string {
		foreach ( $staging_shortcodes as $tag => $registration_code ) {
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
	 * (e.g., 'cfa_') and are used by shortcodes or CPTs.
	 *
	 * @param string $content Functions.php content.
	 * @return array Array of function names => function code blocks.
	 */
	private static function extract_helper_functions( string $content ): array {
		$functions = array();
		$pos = 0;

		// Match function declarations: function function_name($params) { ... }
		// Look for functions that start with common prefixes used by shortcodes
		$pattern = '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/';
		while ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE, $pos ) ) {
			$func_name = $matches[1][0];
			$func_start = $matches[0][1];
			
			// Only extract functions that start with common prefixes (cfa_, project-specific prefixes)
			// or are explicitly helper functions (contain 'helper', 'get_', 'render_', 'normalize_', 'is_')
			if ( preg_match( '/^(cfa_|helper_|get_|render_|normalize_|is_|migrate_|format_|days_)/', $func_name ) ) {
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
		foreach ( $staging_functions as $func_name => $func_code ) {
			// Check if function already exists
			$pattern = '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\(/';
			if ( preg_match( $pattern, $theme_content ) ) {
				// Replace existing function - match multi-line function definitions
				$theme_content = preg_replace(
					'/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*\{.*?\}\s*;/s',
					$func_code,
					$theme_content,
					1
				);
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
