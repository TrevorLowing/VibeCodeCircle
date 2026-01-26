<?php
/**
 * LoopHandlerManager utility class for Etch plugin
 *
 * This file contains the LoopHandlerManager class which provides
 * centralized loop handler management functionality.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities;

use Etch\Preprocessor\Data\EtchDataGlobalLoop;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;
use Etch\Preprocessor\Utilities\LoopHandlers\LoopHandlerInterface;
use Etch\Preprocessor\Utilities\LoopHandlers\WpQueryLoopHandler;
use Etch\Preprocessor\Utilities\LoopHandlers\WpMainQueryLoopHandler;
use Etch\Preprocessor\Utilities\LoopHandlers\JsonLoopHandler;
use Etch\Preprocessor\Utilities\LoopHandlers\WpTermsLoopHandler;
use Etch\Preprocessor\Utilities\LoopHandlers\WpUsersLoopHandler;
use Etch\Blocks\Global\CachedLoopPresets;

/**
 * LoopHandlerManager utility class for centralized loop handler management.
 */
class LoopHandlerManager {

	/**
	 * Array of registered loop handlers.
	 *
	 * @var array<string, LoopHandlerInterface>
	 */
	private static $loop_handlers = array();

	/**
	 * Loop presets loaded from database.
	 *
	 * @var array<string, EtchDataGlobalLoop>
	 */
	private static $loop_presets = array();

	/**
	 * Whether handlers have been initialized.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize and register all available loop handlers.
	 *
	 * @return void
	 */
	public static function initialize_handlers(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$loop_handlers = array(
			'wp-query'    => new WpQueryLoopHandler(),
			'main-query'  => new WpMainQueryLoopHandler(),
			'json'        => new JsonLoopHandler(),
			'wp-terms'    => new WpTermsLoopHandler(),
			'wp-users'    => new WpUsersLoopHandler(),
		);

		self::$initialized = true;
	}

	/**
	 * Get a loop handler by type.
	 *
	 * @param string $type The loop type identifier.
	 * @return LoopHandlerInterface|null
	 */
	public static function get_loop_handler( string $type ): ?LoopHandlerInterface {
		self::initialize_handlers();
		return self::$loop_handlers[ $type ] ?? null;
	}

	/**
	 * Load loop presets from database.
	 *
	 * @return array<string, EtchDataGlobalLoop>
	 */
	public static function load_loop_presets(): array {
		if ( ! empty( self::$loop_presets ) ) {
			return self::$loop_presets;
		}

		$raw_loops = (array) get_option( 'etch_loops', array() );
		$processed_loops = array();

		foreach ( $raw_loops as $loop_id => $loop_data ) {
			$loop_data_array = EtchTypeAsserter::to_array( $loop_data );
			if ( ! empty( $loop_data_array ) ) {
				$global_loop = EtchDataGlobalLoop::from_array( $loop_data_array );
				if ( null !== $global_loop ) {
					$processed_loops[ $loop_id ] = $global_loop;
				}
			}
		}

		self::$loop_presets = $processed_loops;
		return $processed_loops;
	}

	/**
	 * Get loop presets (loads if not already loaded).
	 *
	 * @return array<string, EtchDataGlobalLoop>
	 */
	public static function get_loop_presets(): array {
		return self::load_loop_presets();
	}

	/**
	 * Normalize a loop identifier to its database key.
	 *
	 * This resolves user-friendly keys (like "posts") to their actual database keys.
	 * If the input is already a database key, it returns it unchanged.
	 *
	 * @param string $loop_id The loop ID (either database key or user-friendly key).
	 * @return string|null The normalized database key, or null if not found.
	 */
	public static function normalize_loop_id( string $loop_id ): ?string {
		$loop_presets = self::get_loop_presets();

		// Check if it's already a database key
		if ( isset( $loop_presets[ $loop_id ] ) ) {
			return $loop_id;
		}

		// Try to find by user-friendly key property
		return self::find_loop_by_key( $loop_id );
	}

	/**
	 * Determine the loop type based on the loop ID and stored presets.
	 *
	 * @param string $loop_id The loop ID/preset identifier.
	 * @return string The loop type identifier.
	 */
	public static function determine_loop_type( string $loop_id ): string {
		$loop_presets = self::get_loop_presets();

		// Normalize the loop ID to database key
		$normalized_id = self::normalize_loop_id( $loop_id );

		if ( null !== $normalized_id && isset( $loop_presets[ $normalized_id ] ) ) {
			return $loop_presets[ $normalized_id ]->get_type();
		}

		// Default to wp-query for backward compatibility
		return 'wp-query';
	}

	/**
	 * Get loop data using the appropriate handler.
	 *
	 * @param string               $loop_id The loop ID/preset identifier.
	 * @param array<string, mixed> $loop_params The parameters for the loop.
	 * @return array<int, array<string, mixed>> Array of loop data items.
	 */
	public static function get_loop_preset_data( string $loop_id, array $loop_params = array() ): array {
		// Normalize the loop ID to database key first
		$normalized_id = self::normalize_loop_id( $loop_id );

		if ( null === $normalized_id ) {
			return array();
		}

		// Use normalized ID for caching and handler calls
		$cache_key = $normalized_id;

		// Check cache first
		$cached_data = CachedLoopPresets::get_cached_loop_data( $cache_key, $loop_params );
		if ( is_array( $cached_data ) ) {
			return $cached_data;
		}

		$loop_type = self::determine_loop_type( $normalized_id );
		$handler = self::get_loop_handler( $loop_type );

		if ( ! $handler ) {
			// Cache empty result to avoid re-processing invalid loops
			CachedLoopPresets::set_cached_loop_data( $cache_key, $loop_params, array() );
			return array();
		}

		$data = $handler->get_loop_data( $normalized_id, $loop_params );

		// Cache the result
		CachedLoopPresets::set_cached_loop_data( $cache_key, $loop_params, $data );

		return $data;
	}

	/**
	 * Strip loop parameters from a string (e.g. "my-loop(param1, param2)" becomes "my-loop").
	 *
	 * @param string $input The input string potentially containing loop parameters.
	 *
	 * @return string The stripped string without loop parameters.
	 */
	public static function strip_loop_params_from_string( string $input ): string {
		if ( strpos( $input, '(' ) === false ) {
			return $input;
		}

		 // match an identifier (letters/underscore/digits after first char) followed by '('
		if ( preg_match( '/^\s*(.*?)\s*\(/', $input, $m ) ) {
			return $m[1];
		}

		return $input;
	}

	/**
	 * Find a loop preset by its key property.
	 *
	 * @param string $search_value The key to search for.
	 * @return string|null The database key of the found loop, or null if not found.
	 */
	public static function find_loop_by_key( string $search_value ): ?string {
		// Remove the loop params from the string, so we can use the key directly
		$search_value = self::strip_loop_params_from_string( $search_value );

		$loop_presets = self::get_loop_presets();

		foreach ( $loop_presets as $loop_id => $loop_preset ) {
			// Check if the search value matches the loop's key
			if ( $loop_preset->get_key() === $search_value ) {
				return $loop_id;
			}
		}

		return null;
	}

	/**
	 * Find a loop preset by its key property.
	 *
	 * @param string $loop_id The key to search for.
	 * @return boolean True if the loop ID is valid, false if not found.
	 */
	public static function is_valid_loop_id( string $loop_id ): bool {
		// Remove the loop params from the string, so we can use the id directly
		$search_value = self::strip_loop_params_from_string( $loop_id );

		$loop_presets = self::get_loop_presets();

		// Check by database key first
		if ( ! empty( $loop_presets ) && isset( $loop_presets[ $search_value ] ) ) {
			return true;
		}

		// Also check by the user-friendly 'key' property
		foreach ( $loop_presets as $loop_preset ) {
			if ( $loop_preset->get_key() === $search_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reset the manager state (useful for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$loop_handlers = array();
		self::$loop_presets = array();
		self::$initialized = false;
	}
}
