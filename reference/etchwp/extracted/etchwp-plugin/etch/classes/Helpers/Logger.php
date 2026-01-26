<?php
/**
 * Etch Logger file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

use Etch\Helpers\Logger\LoggerConfig;

/**
 * Class Logger
 */
class Logger {

	const LOG_LEVEL_BASE = 0;
	const LOG_LEVEL_ERROR = 1;
	const LOG_LEVEL_WARNING = 2;
	const LOG_LEVEL_NOTICE = 3;
	const LOG_LEVEL_INFO = 4;

	/**
	 * The logger configuration.
	 *
	 * @var LoggerConfig|null
	 */
	private static $config = null;

	/**
	 * Set the logger configuration.
	 *
	 * @param LoggerConfig $config The logger configuration.
	 * @return void
	 */
	public static function set_config( LoggerConfig $config ): void {
		self::$config = $config;
	}

	/**
	 * Log a message to the debug file.
	 *
	 * @param mixed $what The message to log.
	 * @param int   $log_level The log level.
	 * @return bool
	 */
	public static function log( $what, int $log_level = self::LOG_LEVEL_BASE ): bool {

		// STEP: check if we should log the message.

		if ( ! self::$config || ! self::$config->enabled || $log_level > self::$config->logLevel ) {
			return false;
		}

		// STEP: setup.
		$message = print_r( $what, true );
		$debug_dir = self::$config->plugin->get_dynamic_uploads_dir();
		$debug_file = $debug_dir . '/debug.log';
		// STEP: check if the directory exists. Create it if it doesn't.
		if ( ! file_exists( $debug_dir ) && ! wp_mkdir_p( $debug_dir ) ) {
			return false;
		}
		// STEP: check if the debug file is writable.
		if ( ! is_writable( $debug_dir ) ) {
			return false;
		}
		// STEP: check if the debug file exists and is writable.
		if ( file_exists( $debug_file ) && ! is_writable( $debug_file ) ) {
			return false;
		}
		// STEP: write the message to the debug file.
		$message .= "\n";
		$ret = file_put_contents( $debug_file, $message, FILE_APPEND );
		return false !== $ret;
	}

	/**
	 * Log the header.
	 *
	 * @return void
	 */
	public static function log_header(): void {
		if ( ! self::$config ) {
			return;
		}
		self::log(
			sprintf(
				"[%s]\n%s - Plugin version %s - requested by %s",
				gmdate( 'd-M-Y H:i:s' ),
				__METHOD__,
				self::$config->plugin->get_plugin_version(),
				self::get_redacted_uri()
			)
		);
	}

	/**
	 * Get the redacted URI.
	 *
	 * @return string
	 */
	public static function get_redacted_uri(): string {
		// STEP: ensure $_SERVER['REQUEST_URI'] is a valid string.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL ) : '';
		if ( ! is_string( $uri ) ) {
			return '';
		}
		// STEP: define parameters to redact and create the regex pattern.
		$params_to_redact = array( 'username', 'user', 'password', 'pass', 'nonce' );
		$params_to_redact_regex = implode( '|', $params_to_redact );
		// STEP: perform the regex replacement.
		$redacted_uri = preg_replace(
			'/(\?|&)(' . $params_to_redact_regex . ')=([^&]+)/i',
			'$1$2=[redacted]',
			$uri
		);
		// STEP: Return the result, ensuring it's a string.
		return is_string( $redacted_uri ) ? $redacted_uri : '';
	}
}
