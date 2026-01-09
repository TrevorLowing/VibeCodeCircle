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

	/**
	 * Log a step in a multi-step process.
	 *
	 * @param string $step_name Step name (e.g., "Extracting ZIP", "Validating files").
	 * @param int    $step_number Step number (1-based).
	 * @param int    $total_steps Total number of steps.
	 * @param array  $context Additional context.
	 * @param string $project_slug Project slug.
	 * @return void
	 */
	public static function log_step( string $step_name, int $step_number, int $total_steps, array $context = array(), string $project_slug = '' ): void {
		$message = sprintf( '[Step %d/%d] %s', $step_number, $total_steps, $step_name );
		self::info( $message, $context, $project_slug );
	}

	/**
	 * Log an error with context and suggested fix.
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context (file, line, function, etc.).
	 * @param string $suggested_fix Suggested fix or solution.
	 * @param string $project_slug Project slug.
	 * @return void
	 */
	public static function error_with_context( string $message, array $context = array(), string $suggested_fix = '', string $project_slug = '' ): void {
		if ( $suggested_fix !== '' ) {
			$context['suggested_fix'] = $suggested_fix;
		}
		self::error( $message, $context, $project_slug );
	}

	/**
	 * Log a warning with context.
	 *
	 * @param string $message Warning message.
	 * @param array  $context Warning context.
	 * @param string $project_slug Project slug.
	 * @return void
	 */
	public static function warning( string $message, array $context = array(), string $project_slug = '' ): void {
		self::log( 'WARNING', $message, $context, $project_slug );
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
