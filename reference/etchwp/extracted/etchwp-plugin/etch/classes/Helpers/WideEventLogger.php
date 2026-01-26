<?php
/**
 * WideEventLogger - Static facade for wide event logging.
 *
 * @package Etch\Helpers
 */

namespace Etch\Helpers;

/**
 * WideEventLogger class.
 *
 * Thin static facade that delegates to a WideEventLoggerInstance.
 * Provides ergonomic static access while keeping state in an injectable instance.
 */
class WideEventLogger {

	/**
	 * The logger instance.
	 *
	 * @var WideEventLoggerInstance|null
	 */
	private static ?WideEventLoggerInstance $instance = null;

	/**
	 * Set the logger instance.
	 *
	 * @param WideEventLoggerInstance $instance The instance to use.
	 * @return void
	 */
	public static function set_instance( WideEventLoggerInstance $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * Set a value in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value.
	 * @return void
	 */
	public static function set( string $key, $value ): void {
		if ( null !== self::$instance ) {
			self::$instance->set( $key, $value );
		}
	}

	/**
	 * Append a value to an array in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value to append.
	 * @return void
	 */
	public static function append( string $key, $value ): void {
		if ( null !== self::$instance ) {
			self::$instance->append( $key, $value );
		}
	}

	/**
	 * Record a failure in the current event.
	 *
	 * Marks the event as failed (always emitted, not sampled).
	 * Records the failure key, message, and optional context data.
	 *
	 * @param string              $key     Identifier for the failure source (e.g., 'api', 'blocks', 'migration').
	 * @param string              $message The human-readable failure message.
	 * @param array<string,mixed> $context Optional additional context data.
	 * @return void
	 */
	public static function failure( string $key, string $message, array $context = array() ): void {
		if ( null !== self::$instance ) {
			self::$instance->failure( $key, $message, $context );
		}
	}

	/**
	 * Emit the current event.
	 *
	 * Called automatically on shutdown. Decides whether to emit based on sampling.
	 *
	 * @return void
	 */
	public static function emit(): void {
		if ( null !== self::$instance ) {
			self::$instance->emit();
		}
	}

	/**
	 * Reset the logger state (useful for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
