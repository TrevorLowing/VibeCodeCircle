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

	private const REQUIRED_ROOT = 'vibecode-deploy-staging/';
	private const ALLOWED_ROOT_FILES = array(
		'vibecode-deploy-shortcodes.json',
	);
	private const ALLOWED_TOP_LEVEL_DIRS = array(
		'pages',
		'templates',
		'template-parts',
		'css',
		'js',
		'resources',
		'theme',
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

	private static function validate_entry_path( string $normalized_path ): ?string {
		if ( ! self::is_safe_relative_path( $normalized_path ) ) {
			return null;
		}

		if ( ! str_starts_with( $normalized_path, self::REQUIRED_ROOT ) ) {
			return null;
		}

		$relative = substr( $normalized_path, strlen( self::REQUIRED_ROOT ) );
		$relative = ltrim( $relative, '/' );

		if ( $relative === '' ) {
			return null;
		}

		if ( strpos( $relative, '/' ) === false ) {
			return in_array( $relative, self::ALLOWED_ROOT_FILES, true ) ? $relative : null;
		}

		$parts = explode( '/', $relative );
		$top = $parts[0] ?? '';
		if ( $top === '' || ! in_array( $top, self::ALLOWED_TOP_LEVEL_DIRS, true ) ) {
			return null;
		}

		return $relative;
	}

	private static function validate_extension( string $path ): bool {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( $ext === '' ) {
			return false;
		}
		return in_array( $ext, self::ALLOWED_EXTENSIONS, true );
	}

	public static function extract_zip_to_staging( string $zip_path, string $project_slug ): array {
		if ( ! file_exists( $zip_path ) ) {
			return array( 'ok' => false, 'error' => 'Zip file not found.' );
		}

		$size = (int) filesize( $zip_path );
		if ( $size <= 0 ) {
			return array( 'ok' => false, 'error' => 'Zip file is empty.' );
		}

		if ( $size > self::ZIP_MAX_BYTES ) {
			return array( 'ok' => false, 'error' => 'Zip exceeds max size.' );
		}

		if ( ! class_exists( '\\ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'ZipArchive not available.' );
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $zip_path );
		if ( $opened !== true ) {
			return array( 'ok' => false, 'error' => 'Unable to open zip.' );
		}

		$count = $zip->numFiles;
		if ( $count > self::ZIP_MAX_FILES ) {
			$zip->close();
			return array( 'ok' => false, 'error' => 'Zip exceeds max file count.' );
		}

		$fingerprint = gmdate( 'Ymd-His' );
		$target_root = self::staging_root( $project_slug, $fingerprint );
		self::ensure_dir( $target_root );

		$unpacked_total = 0;
		$written_files = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = is_array( $stat ) ? (string) ( $stat['name'] ?? '' ) : '';
			$name = self::normalize_zip_path( $name );

			if ( $name === '' ) {
				$zip->close();
				return array( 'ok' => false, 'error' => 'Zip contains an invalid entry.' );
			}

			if ( str_ends_with( $name, '/' ) ) {
				continue;
			}

			$relative = self::validate_entry_path( $name );
			if ( $relative === null ) {
				$zip->close();
				return array( 'ok' => false, 'error' => 'Zip contains files outside required staging layout.' );
			}

			if ( ! self::validate_extension( $relative ) ) {
				$zip->close();
				return array( 'ok' => false, 'error' => 'Zip contains a disallowed file type.' );
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

		return array(
			'ok' => true,
			'project_slug' => $project_slug,
			'fingerprint' => $fingerprint,
			'target_root' => $target_root,
			'files' => $written_files,
		);
	}
}
