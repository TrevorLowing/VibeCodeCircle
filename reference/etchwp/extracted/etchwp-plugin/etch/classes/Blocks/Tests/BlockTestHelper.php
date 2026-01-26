<?php
/**
 * Block Test Helper Trait
 *
 * Provides common helper methods for testing Etch blocks.
 * Includes utilities for context management, mock block creation, and block rendering.
 *
 * @package Etch\Blocks\Tests
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\ComponentSlotContextProvider;
use WP_Block;

/**
 * Trait BlockTestHelper
 *
 * Common helper methods for Etch block tests.
 */
trait BlockTestHelper {

	/**
	 * Clear the cached global context.
	 *
	 * This should be called in setUp() to ensure each test starts with a fresh context.
	 * Uses reflection to access the private static property.
	 *
	 * @return void
	 */
	protected function clear_cached_context(): void {
		$reflection = new \ReflectionClass( DynamicContextProvider::class );
		$property = $reflection->getProperty( 'cached_global_context' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Clear the loop context stack.
	 *
	 * This should be called in setUp() and tearDown() for tests involving loops
	 * to ensure no loop context leaks between tests.
	 *
	 * @return void
	 */
	protected function clear_loop_context_stack(): void {
		DynamicContextProvider::clear();

		$reflection = new \ReflectionClass( ComponentSlotContextProvider::class );
		$context_stack_property = $reflection->getProperty( 'context_stack' );
		$context_stack_property->setAccessible( true );
		$context_stack_property->setValue( null, array() );
	}

	/**
	 * Create a mock WP_Block instance for testing.
	 *
	 * @param string               $block_name   The block name (e.g., 'etch/text').
	 * @param array<string, mixed> $attributes   Block attributes.
	 * @param array<int, array>    $inner_blocks Optional array of inner block data.
	 * @return WP_Block Mock block instance.
	 */
	protected function create_mock_block( string $block_name, array $attributes, array $inner_blocks = array() ): WP_Block {
		$registry = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );
		if ( ! $block_type ) {
			$block_type = new \WP_Block_Type( $block_name, array() );
		}

		$parsed_block = array(
			'blockName'   => $block_name,
			'attrs'       => $attributes,
			'innerBlocks' => $inner_blocks,
		);

		$block = new WP_Block(
			$parsed_block,
			array()
		);

		// Set parsed_block property via reflection for inner blocks access
		if ( ! empty( $inner_blocks ) ) {
			$reflection = new \ReflectionClass( $block );
			$property = $reflection->getProperty( 'parsed_block' );
			$property->setAccessible( true );
			$property->setValue( $block, $parsed_block );
		}

		return $block;
	}

	/**
	 * Render content through WordPress the_content filter pipeline.
	 *
	 * This simulates the full WordPress rendering pipeline including do_shortcode.
	 *
	 * @param string $content Block content to render.
	 * @return string Rendered content with shortcodes processed.
	 */
	protected function render_through_content_filter( string $content ): string {
		return apply_filters( 'the_content', $content );
	}

	/**
	 * Create a post with block content and render through the_content filter.
	 *
	 * Also explicitly processes shortcodes to ensure they work in attributes.
	 * Creates the container post as a draft to avoid polluting published-post queries.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Array of block data.
	 * @return string Rendered HTML content.
	 */
	protected function render_blocks_through_content_filter( array $blocks ): string {
		$serialized = serialize_blocks( $blocks );
		$post_id = $this->factory()->post->create(
			array(
				'post_content' => $serialized,
				'post_status'  => 'draft',
			)
		);

		$post = get_post( $post_id );
		$rendered = apply_filters( 'the_content', $post->post_content );

		// Decode Unicode entities that might have been encoded during JSON serialization
		// This handles cases where quotes become u0022, etc.
		$rendered = preg_replace_callback(
			'/\\\\u([0-9a-fA-F]{4})/',
			function ( $matches ) {
				return mb_convert_encoding( pack( 'H*', $matches[1] ), 'UTF-8', 'UCS-2BE' );
			},
			$rendered
		);

		// Explicitly process shortcodes to ensure they work in HTML attributes
		// the_content filter should handle this, but we ensure it's done
		$rendered = do_shortcode( $rendered );

		// Also process shortcodes in HTML attributes (do_shortcode doesn't process attributes by default)
		// Match attributes with shortcodes: attr="[shortcode]" or attr='[shortcode]'
		$rendered = preg_replace_callback(
			'/(\w+)=["\']([^"\']*\[[^\]]+\][^"\']*)["\']/',
			function ( $matches ) {
				$attr_name = $matches[1];
				$attr_value = $matches[2];
				$processed_value = do_shortcode( $attr_value );
				return $attr_name . '="' . esc_attr( $processed_value ) . '"';
			},
			$rendered
		);

		return $rendered;
	}

	/**
	 * Ensure block is registered, triggering init action if needed.
	 *
	 * @param string $block_name The block name to check (e.g., 'etch/text').
	 * @return void
	 */
	protected function ensure_block_registered( string $block_name ): void {
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
			do_action( 'init' );
		}
	}
}
