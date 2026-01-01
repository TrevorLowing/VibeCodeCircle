<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

final class Cli {
	public static function init(): void {
		if ( ! defined( '\\WP_CLI' ) || ! \WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'vibecode-deploy settings', array( __CLASS__, 'settings_command' ) );
		\WP_CLI::add_command( 'vibecode-deploy import', array( __CLASS__, 'import_command' ) );
	}

	private static function resolve_settings( array $assoc_args ): array {
		$settings = Settings::get_all();

		if ( isset( $assoc_args['project'] ) ) {
			$settings['project_slug'] = sanitize_key( (string) $assoc_args['project'] );
		}

		if ( isset( $assoc_args['prefix'] ) ) {
			$prefix = strtolower( trim( (string) $assoc_args['prefix'] ) );
			$settings['class_prefix'] = $prefix;
		}

		if ( isset( $assoc_args['staging-dir'] ) ) {
			$dir = trim( (string) $assoc_args['staging-dir'] );
			$dir = preg_replace( '/[^a-zA-Z0-9._-]/', '', $dir );
			if ( $dir !== '' ) {
				$settings['staging_dir'] = $dir;
			}
		}

		return $settings;
	}

	private static function validate_settings( array $settings ): void {
		if ( $settings['project_slug'] === '' ) {
			\WP_CLI::error( 'Missing project slug. Set it in Settings -> Vibe Code Deploy or pass --project=<slug>.' );
		}

		if ( $settings['class_prefix'] === '' ) {
			\WP_CLI::error( 'Missing class prefix. Set it in Settings -> Vibe Code Deploy or pass --prefix=<prefix>.' );
		}

		if ( ! preg_match( '/^[a-z0-9-]+-$/', (string) $settings['class_prefix'] ) ) {
			\WP_CLI::error( 'Invalid class prefix. Must match ^[a-z0-9-]+-$ and include a trailing dash.' );
		}
	}

	private static function get_staging_root( array $settings, array $assoc_args ): string {
		if ( isset( $assoc_args['staging-path'] ) ) {
			return rtrim( (string) $assoc_args['staging-path'], '/\\' );
		}

		return rtrim( ABSPATH, '/\\' ) . DIRECTORY_SEPARATOR . (string) $settings['staging_dir'];
	}

	public static function settings_command( $args, $assoc_args ): void {
		$settings = self::resolve_settings( is_array( $assoc_args ) ? $assoc_args : array() );
		$staging  = self::get_staging_root( $settings, is_array( $assoc_args ) ? $assoc_args : array() );

		\WP_CLI::log( wp_json_encode( array_merge( $settings, array( 'staging_root' => $staging ) ) ) );
	}

	public static function import_command( $args, $assoc_args ): void {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$settings   = self::resolve_settings( $assoc_args );

		self::validate_settings( $settings );

		$staging_root = self::get_staging_root( $settings, $assoc_args );
		$pages_dir    = $staging_root . DIRECTORY_SEPARATOR . 'pages';

		if ( ! is_dir( $staging_root ) ) {
			\WP_CLI::error( 'Staging root not found: ' . $staging_root );
		}

		if ( ! is_dir( $pages_dir ) ) {
			\WP_CLI::error( 'Pages folder not found: ' . $pages_dir );
		}

		$html_files = glob( $pages_dir . DIRECTORY_SEPARATOR . '*.html' ) ?: array();
		$count      = is_array( $html_files ) ? count( $html_files ) : 0;

		\WP_CLI::log( 'Project: ' . $settings['project_slug'] );
		\WP_CLI::log( 'Class prefix: ' . $settings['class_prefix'] );
		\WP_CLI::log( 'Staging root: ' . $staging_root );
		\WP_CLI::log( 'Pages found: ' . (string) $count );
		\WP_CLI::log( 'Import is not implemented yet (skeleton command).' );
	}
}
