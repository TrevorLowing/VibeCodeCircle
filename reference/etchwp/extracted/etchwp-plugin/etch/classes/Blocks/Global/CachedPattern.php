<?php
/**
 * Cached Pattern
 *
 * Handles caching of parsed pattern blocks to avoid redundant parsing
 * when the same component pattern is used multiple times.
 *
 * @package Etch
 */

namespace Etch\Blocks\Global;

/**
 * CachedPattern class
 */
class CachedPattern {

	/**
	 * Cache of parsed patterns
	 *
	 * @var array<int, array<string, mixed>|null>
	 */
	private static $cache = array();

	/**
	 * Load pattern data for a given reference ID
	 *
	 * Checks the static cache first. If not found, fetches the post,
	 * validates it's a wp_block, parses blocks, loads properties, and caches everything.
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<string, mixed>|null Pattern data array with 'post', 'parsed_blocks', 'properties', or null if invalid.
	 */
	private static function load_pattern( $ref ) {
		if ( empty( $ref ) ) {
			return null;
		}

		// Check cache first
		if ( isset( self::$cache[ $ref ] ) ) {
			return self::$cache[ $ref ];
		}

		$pattern_post = get_post( $ref );

		if ( ! $pattern_post || 'wp_block' !== $pattern_post->post_type ) {
			// Cache null to avoid re-fetching invalid refs
			self::$cache[ $ref ] = null;
			return null;
		}

		$pattern_blocks = parse_blocks( $pattern_post->post_content );
		$property_definitions = get_post_meta( $pattern_post->ID, 'etch_component_properties', true );
		if ( ! is_array( $property_definitions ) ) {
			$property_definitions = array();
		}

		// Store all pattern data in cache
		$pattern_data = array(
			'post'          => $pattern_post,
			'parsed_blocks' => $pattern_blocks,
			'properties'    => $property_definitions,
		);

		self::$cache[ $ref ] = $pattern_data;

		return $pattern_data;
	}

	/**
	 * Get parsed pattern blocks for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<mixed> The parsed blocks array, or empty array if not found/invalid.
	 */
	public static function get_pattern_parsed_blocks( $ref ) {
		$pattern_data = self::load_pattern( $ref );
		if ( null === $pattern_data ) {
			return array();
		}
		$parsed_blocks = $pattern_data['parsed_blocks'];
		if ( ! is_array( $parsed_blocks ) ) {
			return array();
		}
		return $parsed_blocks;
	}

	/**
	 * Get full pattern data for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<string, mixed>|null Pattern data array with 'post', 'parsed_blocks', 'properties', or null if invalid.
	 */
	public static function get_pattern( $ref ) {
		return self::load_pattern( $ref );
	}

	/**
	 * Get pattern properties for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<mixed> The property definitions array, or empty array if not found/invalid.
	 */
	public static function get_pattern_properties( $ref ) {
		$pattern_data = self::load_pattern( $ref );
		if ( null === $pattern_data ) {
			return array();
		}
		$properties = $pattern_data['properties'];
		if ( ! is_array( $properties ) ) {
			return array();
		}
		return $properties;
	}

	/**
	 * Get pattern post object for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return \WP_Post|null The post object, or null if not found/invalid.
	 */
	public static function get_pattern_post( $ref ) {
		$pattern_data = self::load_pattern( $ref );
		if ( null === $pattern_data ) {
			return null;
		}
		$post = $pattern_data['post'];
		if ( ! ( $post instanceof \WP_Post ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Clear the pattern cache
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
