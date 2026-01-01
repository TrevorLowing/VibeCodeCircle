<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

final class Logger {
	private const MAX_TAIL_BYTES = 524288;

	private static function normalize_project_slug( string $project_slug ): string {
		$project_slug = sanitize_key( $project_slug );
		return $project_slug !== '' ? $project_slug : 'default';
	}

	private static function logs_dir( string $project_slug ): string {
		$uploads = wp_upload_dir();
		$base = rtrim( (string) $uploads['basedir'], '/\\' );
		$project_slug = self::normalize_project_slug( $project_slug );
		return $base . DIRECTORY_SEPARATOR . 'vibecode-deploy' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $project_slug;
	}

	private static function log_file_path( string $project_slug ): string {
		return self::logs_dir( $project_slug ) . DIRECTORY_SEPARATOR . 'vibecode-deploy.log';
	}

	private static function ensure_logs_dir( string $project_slug ): void {
		$dir = self::logs_dir( $project_slug );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	public static function log( string $level, string $message, array $context = array(), string $project_slug = '' ): void {
		self::ensure_logs_dir( $project_slug );

		$level = strtoupper( trim( $level ) );
		$message = trim( $message );
		if ( $message === '' ) {
			return;
		}

		$time = gmdate( 'Y-m-d H:i:s' );
		$line = '[' . $time . ' UTC] [' . $level . '] ' . $message;
		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}
		$line .= "\n";

		$path = self::log_file_path( $project_slug );
		@file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	public static function info( string $message, array $context = array(), string $project_slug = '' ): void {
		self::log( 'INFO', $message, $context, $project_slug );
	}

	public static function error( string $message, array $context = array(), string $project_slug = '' ): void {
		self::log( 'ERROR', $message, $context, $project_slug );
	}

	public static function tail( string $project_slug, int $max_bytes = self::MAX_TAIL_BYTES ): string {
		$path = self::log_file_path( $project_slug );
		if ( ! file_exists( $path ) ) {
			return '';
		}

		$size = (int) filesize( $path );
		if ( $size <= 0 ) {
			return '';
		}

		$max_bytes = max( 1024, $max_bytes );
		$start = max( 0, $size - $max_bytes );

		$fh = fopen( $path, 'rb' );
		if ( ! is_resource( $fh ) ) {
			return '';
		}

		fseek( $fh, $start );
		$data = (string) stream_get_contents( $fh );
		fclose( $fh );

		return $data;
	}

	public static function clear( string $project_slug ): bool {
		$path = self::log_file_path( $project_slug );
		if ( ! file_exists( $path ) ) {
			return true;
		}
		return (bool) @file_put_contents( $path, '' );
	}
}
