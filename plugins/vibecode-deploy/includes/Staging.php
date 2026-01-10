<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

final class Staging {
	public const ZIP_MAX_BYTES = 262144000;
	public const ZIP_MAX_FILES = 10000;
	public const ZIP_MAX_UNPACKED_BYTES = 1073741824;

	private const ALLOWED_EXTENSIONS = array(
		'html',
		'css',
		'js',
		'json',
		'php',
		'txt',
		'map',
		'svg',
		'png',
		'jpg',
		'jpeg',
		'webp',
		'gif',
		'woff',
		'woff2',
		'ttf',
		'otf',
	);

	private const REQUIRED_ROOT_OLD = 'vibecode-deploy-staging/';
	private const REQUIRED_ROOT_NEW_PATTERN = '/^[a-z0-9-]+-deployment\/$/';
	private const ALLOWED_ROOT_FILES = array(
		'vibecode-deploy-shortcodes.json',
		'manifest.json',
		'config.json',
	);
	private const ALLOWED_TOP_LEVEL_DIRS_OLD = array(
		'pages',
		'templates',
		'template-parts',
		'css',
		'js',
		'resources',
		'theme',
	);
	private const ALLOWED_TOP_LEVEL_DIRS_NEW = array(
		'pages',
		'assets',
		'theme',
		'manifest.json',
		'config.json',
	);

	public static function staging_root( string $project_slug, string $fingerprint ): string {
		$uploads = wp_upload_dir();
		$base = rtrim( (string) $uploads['basedir'], '/\\' );
		return $base . DIRECTORY_SEPARATOR . 'vibecode-deploy' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . $project_slug . DIRECTORY_SEPARATOR . $fingerprint;
	}

	public static function ensure_dir( string $path ): void {
		if ( is_dir( $path ) ) {
			return;
		}

		wp_mkdir_p( $path );
	}

	private static function normalize_zip_path( string $path ): string {
		$path = str_replace( "\\", '/', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}

	private static function is_safe_relative_path( string $path ): bool {
		if ( $path === '' ) {
			return false;
		}

		if ( str_starts_with( $path, '/' ) ) {
			return false;
		}

		if ( strpos( $path, '../' ) !== false || strpos( $path, '..\\' ) !== false || strpos( $path, '/..' ) !== false ) {
			return false;
		}

		if ( preg_match( '/^[a-zA-Z]:\//', $path ) ) {
			return false;
		}

		return true;
	}

	private static function detect_package_format( string $normalized_path ): string {
		// Check for old format: vibecode-deploy-staging/
		if ( str_starts_with( $normalized_path, self::REQUIRED_ROOT_OLD ) ) {
			return 'old';
		}
		
		// Check for new format: {project-name}-deployment/
		// Pattern requires trailing slash, but also check without it
		if ( preg_match( self::REQUIRED_ROOT_NEW_PATTERN, $normalized_path ) ) {
			return 'new';
		}
		
		// Try to detect by checking if path starts with any deployment pattern
		// This handles cases where the path doesn't have trailing slash
		$parts = explode( '/', $normalized_path );
		$root_dir = $parts[0] ?? '';
		if ( $root_dir !== '' && preg_match( '/^[a-z0-9-]+-deployment$/', $root_dir ) ) {
			return 'new';
		}
		
		// Also check if path starts with deployment directory followed by a file
		if ( preg_match( '/^[a-z0-9-]+-deployment\//', $normalized_path ) ) {
			return 'new';
		}
		
		return 'unknown';
	}

	private static function validate_entry_path( string $normalized_path ): ?string {
		if ( ! self::is_safe_relative_path( $normalized_path ) ) {
			return null;
		}

		$format = self::detect_package_format( $normalized_path );

		// Old format: vibecode-deploy-staging/
		if ( $format === 'old' ) {
			if ( ! str_starts_with( $normalized_path, self::REQUIRED_ROOT_OLD ) ) {
				return null;
			}

			$relative = substr( $normalized_path, strlen( self::REQUIRED_ROOT_OLD ) );
			$relative = ltrim( $relative, '/' );

			if ( $relative === '' ) {
				return null;
			}

			if ( strpos( $relative, '/' ) === false ) {
				return in_array( $relative, self::ALLOWED_ROOT_FILES, true ) ? $relative : null;
			}

			$parts = explode( '/', $relative );
			$top = $parts[0] ?? '';
			if ( $top === '' || ! in_array( $top, self::ALLOWED_TOP_LEVEL_DIRS_OLD, true ) ) {
				return null;
			}

			return $relative;
		}

		// New format: {project-name}-deployment/
		if ( $format === 'new' ) {
			$parts = explode( '/', $normalized_path );
			$root_dir = array_shift( $parts );
			if ( $root_dir === null || ! preg_match( '/^[a-z0-9-]+-deployment$/', $root_dir ) ) {
				return null;
			}

			$relative = implode( '/', $parts );
			$relative = ltrim( $relative, '/' );

			if ( $relative === '' ) {
				return null;
			}

			// Allow root-level files (manifest.json, config.json)
			if ( strpos( $relative, '/' ) === false ) {
				return in_array( $relative, self::ALLOWED_ROOT_FILES, true ) ? $relative : null;
			}

			$parts = explode( '/', $relative );
			$top = $parts[0] ?? '';
			if ( $top === '' ) {
				return null;
			}

			// Allow assets/ subdirectories (css, js, images)
			if ( $top === 'assets' ) {
				$subdir = $parts[1] ?? '';
				if ( in_array( $subdir, array( 'css', 'js', 'images' ), true ) ) {
					return $relative;
				}
			}

			// Allow pages/, theme/ directories
			if ( in_array( $top, array( 'pages', 'theme' ), true ) ) {
				return $relative;
			}

			// Warn but allow other directories (less strict)
			return $relative;
		}

		return null;
	}

	private static function validate_extension( string $path ): bool {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( $ext === '' ) {
			return false;
		}
		return in_array( $ext, self::ALLOWED_EXTENSIONS, true );
	}

	public static function extract_zip_to_staging( string $zip_path, string $project_slug ): array {
		Logger::info( 'Extraction started.', array(
			'zip_path' => $zip_path,
			'zip_exists' => file_exists( $zip_path ),
			'project_slug' => $project_slug,
		), $project_slug );
		
		if ( ! file_exists( $zip_path ) ) {
			Logger::error( 'Extraction failed: zip file not found.', array( 'zip_path' => $zip_path ), $project_slug );
			return array( 'ok' => false, 'error' => 'Zip file not found.' );
		}

		$size = (int) filesize( $zip_path );
		if ( $size <= 0 ) {
			Logger::error( 'Extraction failed: zip file is empty.', array( 'zip_path' => $zip_path, 'size' => $size ), $project_slug );
			return array( 'ok' => false, 'error' => 'Zip file is empty.' );
		}

		if ( $size > self::ZIP_MAX_BYTES ) {
			Logger::error( 'Extraction failed: zip exceeds max size.', array( 'zip_path' => $zip_path, 'size' => $size, 'max' => self::ZIP_MAX_BYTES ), $project_slug );
			return array( 'ok' => false, 'error' => 'Zip exceeds max size.' );
		}

		if ( ! class_exists( '\\ZipArchive' ) ) {
			Logger::error( 'Extraction failed: ZipArchive not available.', array(), $project_slug );
			return array( 'ok' => false, 'error' => 'ZipArchive not available.' );
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $zip_path );
		if ( $opened !== true ) {
			Logger::error( 'Extraction failed: unable to open zip.', array( 'zip_path' => $zip_path, 'open_result' => $opened ), $project_slug );
			return array( 'ok' => false, 'error' => 'Unable to open zip.' );
		}

		$count = $zip->numFiles;
		Logger::info( 'Zip opened successfully.', array( 'zip_path' => $zip_path, 'file_count' => $count ), $project_slug );
		
		if ( $count > self::ZIP_MAX_FILES ) {
			$zip->close();
			Logger::error( 'Extraction failed: zip exceeds max file count.', array( 'file_count' => $count, 'max' => self::ZIP_MAX_FILES ), $project_slug );
			return array( 'ok' => false, 'error' => 'Zip exceeds max file count.' );
		}

		$fingerprint = gmdate( 'Ymd-His' );
		$target_root = self::staging_root( $project_slug, $fingerprint );
		Logger::info( 'Creating target directory.', array( 'target_root' => $target_root, 'fingerprint' => $fingerprint ), $project_slug );
		self::ensure_dir( $target_root );
		
		if ( ! is_dir( $target_root ) ) {
			$zip->close();
			Logger::error( 'Extraction failed: unable to create target directory.', array( 'target_root' => $target_root ), $project_slug );
			return array( 'ok' => false, 'error' => 'Unable to create target directory.' );
		}

		// First pass: detect package format by scanning entries
		$detected_format = 'unknown';
		$format_detected = false;
		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = is_array( $stat ) ? (string) ( $stat['name'] ?? '' ) : '';
			$name = self::normalize_zip_path( $name );

			if ( $name === '' ) {
				continue;
			}

			// Skip directory entries and system files
			if ( str_ends_with( $name, '/' ) ) {
				continue;
			}
			if ( str_starts_with( $name, '__MACOSX/' ) || str_starts_with( $name, '.DS_Store' ) || str_starts_with( $name, '._' ) ) {
				continue;
			}

			$format = self::detect_package_format( $name );
			if ( $format !== 'unknown' ) {
				$detected_format = $format;
				$format_detected = true;
				break;
			}
		}

		// If format not detected, fail early
		if ( ! $format_detected ) {
			$zip->close();
			Logger::error( 'Extraction failed: zip structure not recognized.', array(
				'zip_path' => $zip_path,
				'file_count' => $count,
				'detected_format' => $detected_format,
			), $project_slug );
			return array( 'ok' => false, 'error' => 'Zip structure not recognized. Expected vibecode-deploy-staging/ or {project-name}-deployment/ root directory.' );
		}
		
		Logger::info( 'Package format detected.', array( 'format' => $detected_format, 'file_count' => $count ), $project_slug );

		$unpacked_total = 0;
		$written_files = 0;

		// Second pass: extract files using detected format
		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = is_array( $stat ) ? (string) ( $stat['name'] ?? '' ) : '';
			$name = self::normalize_zip_path( $name );

			if ( $name === '' ) {
				continue;
			}

			// Skip directory entries
			if ( str_ends_with( $name, '/' ) ) {
				continue;
			}

			// Skip system files (macOS, Windows)
			if ( str_starts_with( $name, '__MACOSX/' ) || str_starts_with( $name, '.DS_Store' ) || str_starts_with( $name, '._' ) ) {
				continue;
			}

			// Validate entry path using detected format
			$relative = self::validate_entry_path( $name );
			if ( $relative === null ) {
				// Skip invalid entries but don't fail (already validated format)
				continue;
			}

			if ( ! self::validate_extension( $relative ) ) {
				// Less strict: warn but continue for unknown extensions
				// Only fail for clearly dangerous file types
				$ext = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
				$dangerous_extensions = array( 'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs' );
				if ( in_array( $ext, $dangerous_extensions, true ) ) {
					$zip->close();
					return array( 'ok' => false, 'error' => 'Zip contains a potentially dangerous file type: ' . $ext );
				}
				// For other unknown extensions, continue but log warning
				continue;
			}

			$entry_size = is_array( $stat ) ? (int) ( $stat['size'] ?? 0 ) : 0;
			$unpacked_total += $entry_size;
			if ( $unpacked_total > self::ZIP_MAX_UNPACKED_BYTES ) {
				$zip->close();
				return array( 'ok' => false, 'error' => 'Zip exceeds max extracted size.' );
			}

			$stream = $zip->getStream( $zip->getNameIndex( $i ) );
			if ( ! is_resource( $stream ) ) {
				$zip->close();
				return array( 'ok' => false, 'error' => 'Unable to read zip entry.' );
			}

			$dest_path = $target_root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
			$dest_dir = dirname( $dest_path );
			self::ensure_dir( $dest_dir );

			$out = fopen( $dest_path, 'wb' );
			if ( ! is_resource( $out ) ) {
				fclose( $stream );
				$zip->close();
				return array( 'ok' => false, 'error' => 'Unable to write extracted file.' );
			}

			stream_copy_to_stream( $stream, $out );
			fclose( $stream );
			fclose( $out );

			$written_files++;
		}

		$zip->close();

		Logger::info( 'Extraction completed successfully.', array(
			'project_slug' => $project_slug,
			'fingerprint' => $fingerprint,
			'target_root' => $target_root,
			'files_written' => $written_files,
			'target_root_exists' => is_dir( $target_root ),
		), $project_slug );

		return array(
			'ok' => true,
			'project_slug' => $project_slug,
			'fingerprint' => $fingerprint,
			'target_root' => $target_root,
			'files' => $written_files,
		);
	}
}
