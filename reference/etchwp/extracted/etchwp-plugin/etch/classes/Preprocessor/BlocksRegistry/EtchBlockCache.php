<?php
/**
 * EtchBlockCache abstract class file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\BlocksRegistry;

/**
 * EtchBlockCache class.
 *
 * Handles caching for Etch blocks.
 */
abstract class EtchBlockCache {

	/**
	 * Global cache for processed blocks to avoid redundant processing
	 *
	 * @var array<string, string>
	 */
	protected static array $block_cache = array();

	/**
	 * Maximum number of cache entries to prevent memory issues
	 *
	 * @var int
	 */
	protected static int $max_cache_size = 500;

	/**
	 * Get a cached version of the processed block if available
	 *
	 * @param array<string, mixed> $block The block to check in cache.
	 * @return string|null The cached block content or null if not found.
	 */
	protected function get_cached_block( array $block ): ?string {
		$cache_key = $this->generate_cache_key( $block );

		if ( isset( self::$block_cache[ $cache_key ] ) ) {
			return self::$block_cache[ $cache_key ];
		}

		return null;
	}

	/**
	 * Store processed block content in cache
	 *
	 * @param array<string, mixed> $block The original block.
	 * @param string               $processed_content The processed block content.
	 * @return void
	 */
	protected function cache_block( array $block, string $processed_content ): void {
		// Manage cache size to prevent memory issues
		if ( count( self::$block_cache ) >= self::$max_cache_size ) {
			// Remove oldest entry (first element)
			array_shift( self::$block_cache );
		}

		$cache_key = $this->generate_cache_key( $block );
		self::$block_cache[ $cache_key ] = $processed_content;
	}

	/**
	 * Generate a cache key for a block
	 *
	 * @param array<string, mixed> $block The block to generate a key for.
	 * @return string The cache key.
	 */
	protected function generate_cache_key( array $block ): string {
		// Create a copy of the block for cache key generation
		$key_data = $block;

		// Replace innerBlocks with a hash to avoid deep nesting issues
		// but still capture changes to inner content
		if ( isset( $key_data['innerBlocks'] ) ) {
			$encoded_inner_blocks = json_encode( $key_data['innerBlocks'] );
			$key_data['innerBlocksHash'] = md5( false !== $encoded_inner_blocks ? $encoded_inner_blocks : '' );
			unset( $key_data['innerBlocks'] );
		}

		$encoded_key_data = json_encode( $key_data );
		return md5( false !== $encoded_key_data ? $encoded_key_data : '' );
	}
}
