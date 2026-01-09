<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

final class ManifestService {
	private static function normalize_project_slug( string $project_slug ): string {
		$project_slug = sanitize_key( $project_slug );
		return $project_slug !== '' ? $project_slug : 'default';
	}

	private static function normalize_fingerprint( string $fingerprint ): string {
		$fingerprint = sanitize_text_field( $fingerprint );
		$fingerprint = (string) preg_replace( '/[^a-zA-Z0-9._-]/', '', $fingerprint );
		return $fingerprint;
	}

	public static function manifests_dir( string $project_slug ): string {
		$uploads = wp_upload_dir();
		$base = rtrim( (string) $uploads['basedir'], '/\\' );
		$project_slug = self::normalize_project_slug( $project_slug );
		return $base . DIRECTORY_SEPARATOR . 'vibecode-deploy' . DIRECTORY_SEPARATOR . 'manifests' . DIRECTORY_SEPARATOR . $project_slug;
	}

	public static function manifest_path( string $project_slug, string $fingerprint ): string {
		$fingerprint = self::normalize_fingerprint( $fingerprint );
		return self::manifests_dir( $project_slug ) . DIRECTORY_SEPARATOR . $fingerprint . '.json';
	}

	public static function write_manifest( string $project_slug, string $fingerprint, array $manifest ): bool {
		$dir = self::manifests_dir( $project_slug );
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$path = self::manifest_path( $project_slug, $fingerprint );
		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) || $json === '' ) {
			return false;
		}

		return file_put_contents( $path, $json ) !== false;
	}

	public static function read_manifest( string $project_slug, string $fingerprint ): ?array {
		$path = self::manifest_path( $project_slug, $fingerprint );
		if ( ! is_file( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	public static function has_manifest( string $project_slug, string $fingerprint ): bool {
		$path = self::manifest_path( $project_slug, $fingerprint );
		return is_file( $path );
	}

	public static function last_deploy_option_key( string $project_slug ): string {
		return 'vibecode_deploy_last_deploy_' . self::normalize_project_slug( $project_slug );
	}

	public static function set_last_deploy_fingerprint( string $project_slug, string $fingerprint ): bool {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		if ( $project_slug === '' || $fingerprint === '' ) {
			return false;
		}

		return (bool) update_option( self::last_deploy_option_key( $project_slug ), $fingerprint, false );
	}

	public static function get_last_deploy_fingerprint( string $project_slug ): string {
		$val = get_option( self::last_deploy_option_key( $project_slug ), '' );
		return is_string( $val ) ? $val : '';
	}

	/**
	 * Get theme file content from manifest.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint.
	 * @param string $file_name Theme file name (e.g., 'functions.php', 'acf-json/group_123.json').
	 * @return string|null File content or null if not found.
	 */
	public static function get_theme_file_snapshot( string $project_slug, string $fingerprint, string $file_name ): ?string {
		$manifest = self::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return null;
		}

		$theme_files = isset( $manifest['theme_files'] ) && is_array( $manifest['theme_files'] ) ? $manifest['theme_files'] : array();
		$file_name = sanitize_file_name( (string) $file_name );

		if ( isset( $theme_files[ $file_name ] ) && is_string( $theme_files[ $file_name ] ) ) {
			return $theme_files[ $file_name ];
		}

		// Check for ACF JSON files in nested structure
		if ( strpos( $file_name, 'acf-json/' ) === 0 ) {
			$acf_files = isset( $theme_files['acf-json'] ) && is_array( $theme_files['acf-json'] ) ? $theme_files['acf-json'] : array();
			$json_file = str_replace( 'acf-json/', '', $file_name );
			if ( isset( $acf_files[ $json_file ] ) && is_string( $acf_files[ $json_file ] ) ) {
				return $acf_files[ $json_file ];
			}
		}

		return null;
	}

	/**
	 * Get CSS/JS asset information from manifest.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint.
	 * @return array Asset information with 'css' and 'js' keys containing file paths.
	 */
	public static function get_asset_snapshot( string $project_slug, string $fingerprint ): array {
		$manifest = self::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return array( 'css' => array(), 'js' => array() );
		}

		$assets = isset( $manifest['assets'] ) && is_array( $manifest['assets'] ) ? $manifest['assets'] : array();
		return array(
			'css' => isset( $assets['css'] ) && is_array( $assets['css'] ) ? $assets['css'] : array(),
			'js' => isset( $assets['js'] ) && is_array( $assets['js'] ) ? $assets['js'] : array(),
		);
	}
}
