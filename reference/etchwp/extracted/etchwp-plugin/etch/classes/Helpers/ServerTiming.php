<?php
/**
 * ServerTiming class for Etch plugin
 *
 * This file contains the ServerTiming class which is responsible for
 * measuring performance of PHP code and exposing it via the Server-Timing HTTP header.
 *
 * @package Etch\Helpers
 */

namespace Etch\Helpers;

use Etch\Helpers\Flag;

/**
 * A static helper class to handle Server-Timing metrics.
 */
class ServerTiming {

	/**
	 * Holds the start time and description of pending metrics.
	 *
	 * @var array<string, array{start: float, desc: string}>
	 */
	private static $pending_metrics = array();

	/**
	 * Holds the completed metrics ready to be sent in the header.
	 *
	 * @var array<string, array{dur: float, desc: string}>
	 */
	private static $completed_metrics = array();

	/**
	 * Starts a performance timer.
	 *
	 * @param string $handle A unique identifier for the metric.
	 * @param string $description An optional description.
	 * @return void
	 */
	public static function start( string $handle, string $description = '' ) {
		if ( ! Flag::is_on( 'ENABLE_SERVER_TIMING' ) ) {
			return;
		}

		self::$pending_metrics[ $handle ] = array(
			'start' => microtime( true ),
			'desc'  => $description,
		);
	}

	/**
	 * Stops a performance timer.
	 *
	 * @param string $handle The handle of the timer to stop.
	 * @return void
	 */
	public static function stop( string $handle ) {
		if ( ! Flag::is_on( 'ENABLE_SERVER_TIMING' ) ) {
			return;
		}

		if ( ! isset( self::$pending_metrics[ $handle ] ) ) {
			return;
		}

		$start_time = self::$pending_metrics[ $handle ]['start'];
		$duration   = ( microtime( true ) - $start_time ) * 1000; // Convert to milliseconds.

		// If this metric handle already exists, accumulate the duration.
		if ( isset( self::$completed_metrics[ $handle ] ) ) {
			self::$completed_metrics[ $handle ]['dur'] += $duration;
		} else {
			// Otherwise, create the new entry.
			self::$completed_metrics[ $handle ] = array(
				'dur'  => $duration,
				'desc' => self::$pending_metrics[ $handle ]['desc'],
			);
		}

		unset( self::$pending_metrics[ $handle ] );
	}

	/**
	 * Measures the execution time of a callable.
	 *
	 * @template T
	 * @param string        $handle      A unique identifier for the metric.
	 * @param callable(): T $callback    The code to measure.
	 * @param string        $description An optional description.
	 * @return T The value returned by the callback.
	 */
	public static function measure( string $handle, callable $callback, string $description = '' ) {
		if ( ! Flag::is_on( 'ENABLE_SERVER_TIMING' ) ) {
			return $callback();
		}

		self::start( $handle, $description );
		$result = $callback();
		self::stop( $handle );
		return $result;
	}

	/**
	 * Starts output buffering with our callback.
	 *
	 * @return void
	 */
	public static function start_buffering() {
		if ( ! Flag::is_on( 'ENABLE_SERVER_TIMING' ) ) {
			return;
		}

		ob_start( array( self::class, 'ob_callback' ) );
	}

	/**
	 * The output buffer callback.
	 * This is called by PHP just before the buffer is flushed.
	 *
	 * @param string $buffer The output buffer.
	 * @return string The (potentially modified) output buffer.
	 */
	public static function ob_callback( string $buffer ): string {
		if ( empty( self::$completed_metrics ) ) {
			return $buffer;
		}

		$file = '';
		$line = 0;
		if ( headers_sent( $file, $line ) ) {
			// This shouldn't happen with ob_callback, but we log it just in case.
			Logger::log( sprintf( 'ServerTiming Error: Headers already sent in %s on line %d', $file, $line ) );
			return $buffer;
		}

		$header_parts = array();
		foreach ( self::$completed_metrics as $handle => $metric ) {
			$part = $handle;
			if ( ! empty( $metric['desc'] ) ) {
				// Descriptions must be quoted.
				$part .= ';desc="' . esc_attr( $metric['desc'] ) . '"';
			}
			$part .= ';dur=' . $metric['dur'];
			$header_parts[] = $part;
		}

		Logger::log( 'ServerTiming::send_header: ' . implode( ', ', $header_parts ) );
		header( 'Server-Timing: ' . implode( ', ', $header_parts ) );

		return $buffer;
	}
}
