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
		if ( ! is_dir( $build_root ) ) {
			return '';
		}

		$prefixes = array();
		$common_classes = array( 'main', 'hero', 'header', 'footer', 'container', 'button', 'page-section', 'page-card' );

		// Scan HTML files
		$html_files = self::find_files( $build_root, array( 'html' ) );
		foreach ( $html_files as $file ) {
			$content = file_get_contents( $file );
			if ( ! is_string( $content ) ) {
				continue;
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
					}
				}
			}
		}

		// Scan CSS files
		$css_files = self::find_files( $build_root, array( 'css' ) );
		foreach ( $css_files as $file ) {
			$content = file_get_contents( $file );
			if ( ! is_string( $content ) ) {
				continue;
			}

			// Look for CSS selectors with common class names
			foreach ( $common_classes as $class_name ) {
				// Pattern: .prefix-main, .prefix-hero, etc.
				if ( preg_match( '/\.([a-z0-9-]+-' . preg_quote( $class_name, '/' ) . ')\b/i', $content, $matches ) ) {
					$full_class = $matches[1];
					$prefix = self::extract_prefix( $full_class, $class_name );
					if ( $prefix !== '' ) {
						$prefixes[ $prefix ] = ( $prefixes[ $prefix ] ?? 0 ) + 1;
					}
				}
			}
		}

		// Return the most common prefix (with highest count)
		if ( empty( $prefixes ) ) {
			return '';
		}

		arsort( $prefixes, SORT_NUMERIC );
		$detected_prefix = (string) array_key_first( $prefixes );

		// Ensure prefix ends with dash
		if ( $detected_prefix !== '' && ! str_ends_with( $detected_prefix, '-' ) ) {
			$detected_prefix .= '-';
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
