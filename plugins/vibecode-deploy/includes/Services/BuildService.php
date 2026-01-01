<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

final class BuildService {
	private static function normalize_project_slug( string $project_slug ): string {
		$project_slug = sanitize_key( $project_slug );
		return $project_slug !== '' ? $project_slug : 'default';
	}

	public static function get_project_staging_dir( string $project_slug ): string {
		$uploads = wp_upload_dir();
		$base = rtrim( (string) $uploads['basedir'], '/\\' );
		$project_slug = self::normalize_project_slug( $project_slug );
		return $base . DIRECTORY_SEPARATOR . 'vibecode-deploy' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . $project_slug;
	}

	public static function build_root_path( string $project_slug, string $fingerprint ): string {
		$fingerprint = sanitize_text_field( $fingerprint );
		return self::get_project_staging_dir( $project_slug ) . DIRECTORY_SEPARATOR . $fingerprint;
	}

	public static function list_build_fingerprints( string $project_slug ): array {
		$dir = self::get_project_staging_dir( $project_slug );
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$entries = scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$out = array();
		foreach ( $entries as $entry ) {
			if ( $entry === '.' || $entry === '..' ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			if ( is_dir( $path ) ) {
				$out[] = $entry;
			}
		}

		rsort( $out );
		return $out;
	}

	public static function get_build_stats( string $project_slug, string $fingerprint ): array {
		$fingerprint = sanitize_text_field( $fingerprint );
		if ( $fingerprint === '' ) {
			return array( 'pages' => 0, 'files' => 0, 'bytes' => 0 );
		}

		$root = self::build_root_path( $project_slug, $fingerprint );
		if ( ! is_dir( $root ) ) {
			return array( 'pages' => 0, 'files' => 0, 'bytes' => 0 );
		}

		$pages_dir = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'pages';
		$page_files = glob( $pages_dir . DIRECTORY_SEPARATOR . '*.html' ) ?: array();
		$page_files = is_array( $page_files ) ? $page_files : array();
		$pages = count( $page_files );

		$files = 0;
		$bytes = 0;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) {
				continue;
			}
			$files++;
			$bytes += (int) $item->getSize();
		}

		return array(
			'pages' => $pages,
			'files' => $files,
			'bytes' => $bytes,
		);
	}

	private static function active_build_option_key( string $project_slug ): string {
		return 'vibecode_deploy_active_build_' . self::normalize_project_slug( $project_slug );
	}

	public static function get_active_fingerprint( string $project_slug ): string {
		$val = get_option( self::active_build_option_key( $project_slug ), '' );
		return is_string( $val ) ? $val : '';
	}

	public static function set_active_fingerprint( string $project_slug, string $fingerprint ): bool {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		if ( $project_slug === '' || $fingerprint === '' ) {
			return false;
		}
		return (bool) update_option( self::active_build_option_key( $project_slug ), $fingerprint, false );
	}

	public static function clear_active_fingerprint( string $project_slug ): bool {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return false;
		}
		return (bool) delete_option( self::active_build_option_key( $project_slug ) );
	}
}
