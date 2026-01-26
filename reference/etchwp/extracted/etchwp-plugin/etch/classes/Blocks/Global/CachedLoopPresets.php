<?php
/**
 * Cached Loop Presets
 *
 * Handles caching of resolved loop data to avoid redundant queries
 * when the same loop is used multiple times with the same parameters.
 *
 * @package Etch
 */

namespace Etch\Blocks\Global;

/**
 * CachedLoopPresets class
 */
class CachedLoopPresets {
	/**
	 * Cache of loop data
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $cache = array();

	/**
	 * Generate a unique cache key for a loop ID and its parameters
	 *
	 * @param string               $loop_id     The loop ID.
	 * @param array<string, mixed> $loop_params Loop parameters.
	 * @return string The unique cache key.
	 */
	private static function generate_key( string $loop_id, array $loop_params ): string {
		// Sort params to ensure same params in different order generate same key
		ksort( $loop_params );
		return md5( $loop_id . serialize( $loop_params ) );
	}

	/**
	 * Get cached loop data
	 *
	 * @param string               $loop_id     The loop ID.
	 * @param array<string, mixed> $loop_params Loop parameters.
	 * @return array<int, array<string, mixed>>|null The cached loop data or null if not found.
	 */
	public static function get_cached_loop_data( string $loop_id, array $loop_params ) {
		$key = self::generate_key( $loop_id, $loop_params );

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		return null;
	}

	/**
	 * Set cached loop data
	 *
	 * @param string                           $loop_id     The loop ID.
	 * @param array<string, mixed>             $loop_params Loop parameters.
	 * @param array<int, array<string, mixed>> $data        The data to cache.
	 * @return void
	 */
	public static function set_cached_loop_data( string $loop_id, array $loop_params, array $data ): void {
		$key = self::generate_key( $loop_id, $loop_params );
		self::$cache[ $key ] = $data;
	}

	/**
	 * Clear the loop cache
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
