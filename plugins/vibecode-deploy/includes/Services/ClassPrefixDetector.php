<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Service for detecting CSS class prefix from staging files.
 *
 * Scans HTML and CSS files to detect the class prefix pattern used in the project.
 */
final class ClassPrefixDetector {
	/**
	 * Detect class prefix from staging files.
	 *
	 * Scans HTML and CSS files for common class name patterns and extracts the prefix.
	 *
	 * @param string $build_root Path to the build root directory (staging files).
	 * @return string Detected prefix (with trailing dash) or empty string if not detected.
	 */
	public static function detect_from_staging( string $build_root ): string {
		\VibeCode\Deploy\Logger::info( 'Class prefix detection: function called.', array( 'build_root' => $build_root, 'build_root_exists' => is_dir( $build_root ) ), '' );
		
		if ( ! is_dir( $build_root ) ) {
			\VibeCode\Deploy\Logger::warning( 'Class prefix detection: build_root does not exist.', array( 'build_root' => $build_root ), '' );
			return '';
		}

		// Set a reasonable timeout for detection (10 seconds max)
		$original_time_limit = ini_get( 'max_execution_time' );
		@set_time_limit( 10 );
		\VibeCode\Deploy\Logger::info( 'Class prefix detection: timeout set.', array( 'original_limit' => $original_time_limit, 'new_limit' => 10 ), '' );

		$prefixes = array();
		// Common class names to detect (order matters - most common first)
		$common_classes = array( 'main', 'hero', 'header', 'footer', 'container', 'button', 'btn', 'page-section', 'page-card', 'page-content', 'nav', 'logo' );

		// Scan HTML files
		\VibeCode\Deploy\Logger::info( 'Class prefix detection: calling find_files for HTML.', array( 'build_root' => $build_root ), '' );
		$html_files = self::find_files( $build_root, array( 'html' ) );
		\VibeCode\Deploy\Logger::info( 'Class prefix detection: scanning HTML files.', array( 'build_root' => $build_root, 'html_files_count' => count( $html_files ) ), '' );
		$html_files_scanned = 0;
		foreach ( $html_files as $file ) {
			$html_files_scanned++;
			$content = @file_get_contents( $file );
			if ( ! is_string( $content ) || strlen( $content ) === 0 ) {
				continue;
			}

			// Limit content size to prevent memory issues (first 100KB should be enough for class detection)
			if ( strlen( $content ) > 100000 ) {
				$content = substr( $content, 0, 100000 );
			}

			// Look for class attributes with common class names
			foreach ( $common_classes as $class_name ) {
				// Pattern: class="prefix-main" or class="prefix-hero" etc.
				// Also match: className="prefix-main" (for block templates)
				if ( preg_match( '/\b(?:class|className)=["\']([a-z0-9-]+-' . preg_quote( $class_name, '/' ) . ')/i', $content, $matches ) ) {
					$full_class = $matches[1];
					$prefix = self::extract_prefix( $full_class, $class_name );
					if ( $prefix !== '' ) {
						$prefixes[ $prefix ] = ( $prefixes[ $prefix ] ?? 0 ) + 1;
						// Found a prefix, can break early
						break;
					}
				}
			}
			
			// If we found a prefix, no need to scan more files
			if ( ! empty( $prefixes ) ) {
				break;
			}
		}
		\VibeCode\Deploy\Logger::info( 'Class prefix detection: HTML files scanned.', array( 'html_files_scanned' => $html_files_scanned, 'prefixes_found' => count( $prefixes ) ), '' );

		// Scan CSS files (only if no prefix found in HTML)
		$css_files = array(); // Initialize to prevent null count() error
		if ( empty( $prefixes ) ) {
			$css_files = self::find_files( $build_root, array( 'css' ) );
			\VibeCode\Deploy\Logger::info( 'Class prefix detection: scanning CSS files.', array( 'build_root' => $build_root, 'css_files_count' => count( $css_files ) ), '' );
			$css_files_scanned = 0;
			foreach ( $css_files as $file ) {
				$css_files_scanned++;
				$content = @file_get_contents( $file );
				if ( ! is_string( $content ) || strlen( $content ) === 0 ) {
					continue;
				}

				// Limit content size to prevent memory issues (first 200KB should be enough for class detection)
				if ( strlen( $content ) > 200000 ) {
					$content = substr( $content, 0, 200000 );
				}

				// Look for CSS selectors with common class names
				foreach ( $common_classes as $class_name ) {
					// Pattern: .prefix-main, .prefix-hero, etc.
					if ( preg_match( '/\.([a-z0-9-]+-' . preg_quote( $class_name, '/' ) . ')\b/i', $content, $matches ) ) {
						$full_class = $matches[1];
						$prefix = self::extract_prefix( $full_class, $class_name );
						if ( $prefix !== '' ) {
							$prefixes[ $prefix ] = ( $prefixes[ $prefix ] ?? 0 ) + 1;
							// Found a prefix, can break early
							break;
						}
					}
				}
				
				// If we found a prefix, no need to scan more files
				if ( ! empty( $prefixes ) ) {
					break;
				}
			}
			\VibeCode\Deploy\Logger::info( 'Class prefix detection: CSS files scanned.', array( 'css_files_scanned' => $css_files_scanned, 'prefixes_found' => count( $prefixes ) ), '' );
		}

		// Return the most common prefix (with highest count)
		if ( empty( $prefixes ) ) {
			\VibeCode\Deploy\Logger::warning( 'Class prefix detection: no prefixes found.', array(
				'build_root' => $build_root,
				'html_files_count' => count( $html_files ),
				'css_files_count' => count( $css_files ),
				'common_classes' => $common_classes,
			), '' );
			return '';
		}

		arsort( $prefixes, SORT_NUMERIC );
		$detected_prefix = (string) array_key_first( $prefixes );

		// Ensure prefix ends with dash
		if ( $detected_prefix !== '' && ! str_ends_with( $detected_prefix, '-' ) ) {
			$detected_prefix .= '-';
		}

		\VibeCode\Deploy\Logger::info( 'Class prefix detection: prefix detected.', array(
			'build_root' => $build_root,
			'detected_prefix' => $detected_prefix,
			'all_prefixes' => $prefixes,
			'html_files_count' => count( $html_files ),
			'css_files_count' => count( $css_files ),
		), '' );

		// Restore original time limit
		if ( isset( $original_time_limit ) && $original_time_limit !== false ) {
			@set_time_limit( (int) $original_time_limit );
		}

		return $detected_prefix;
	}

	/**
	 * Extract prefix from a full class name.
	 *
	 * @param string $full_class Full class name (e.g., "cfa-main").
	 * @param string $class_name Base class name (e.g., "main").
	 * @return string Prefix with trailing dash (e.g., "cfa-") or empty string.
	 */
	private static function extract_prefix( string $full_class, string $class_name ): string {
		$full_class = strtolower( trim( $full_class ) );
		$class_name = strtolower( trim( $class_name ) );

		// Check if class name is at the end
		if ( ! str_ends_with( $full_class, $class_name ) ) {
			return '';
		}

		// Extract prefix (everything before the class name)
		$prefix = substr( $full_class, 0, -strlen( $class_name ) );

		// Validate prefix format (lowercase letters, numbers, hyphens)
		if ( ! preg_match( '/^[a-z0-9-]+$/', $prefix ) ) {
			return '';
		}

		// Ensure prefix ends with dash
		if ( $prefix !== '' && ! str_ends_with( $prefix, '-' ) ) {
			$prefix .= '-';
		}

		return $prefix;
	}

	/**
	 * Find files with specific extensions in a directory recursively.
	 *
	 * @param string $dir Directory to search.
	 * @param array  $extensions Array of file extensions (without dot).
	 * @return array Array of file paths.
	 */
	private static function find_files( string $dir, array $extensions ): array {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$ext = strtolower( $file->getExtension() );
			if ( in_array( $ext, $extensions, true ) ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}
}
