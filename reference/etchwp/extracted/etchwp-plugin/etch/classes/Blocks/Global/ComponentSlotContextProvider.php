<?php
/**
 * Component Slot Context Provider
 *
 * Manages slot content maps and parent context for component rendering.
 * Uses a stack-based approach to handle nested components with slots.
 *
 * @package Etch\Blocks\Global
 */

namespace Etch\Blocks\Global;

use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Types\SlotContentAttributes;

/**
 * ComponentSlotContextProvider class
 *
 * Handles slot content mapping and parent context for component blocks.
 * This is separate from DynamicContextProvider to keep responsibilities clear:
 * - DynamicContextProvider: dynamic/global context sources (props, site, user, etc.)
 * - ComponentSlotContextProvider: slot content maps and parent context for slots
 */
class ComponentSlotContextProvider {

	/**
	 * Stack of component slot contexts.
	 * Each entry contains: ['slots' => array, 'component_block' => WP_Block, 'parent_component_block' => WP_Block|null, 'parent_dynamic_entries' => DynamicContentEntry[], 'parent_sources' => array]
	 *
	 * @var array<int, array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>}>
	 */
	private static array $context_stack = array();

	/**
	 * Push a new component slot context onto the stack.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $slots_map Slot name => array of parsed block data.
	 * @param \WP_Block                                       $component_block The component block instance.
	 * @param \WP_Block|null                                  $parent_component_block The parent component block instance (if any).
	 * @param array<int, DynamicContentEntry>                 $parent_dynamic_entries The parent dynamic entries (non-global).
	 * @param array<int, array{key: string, source: mixed}>   $parent_sources The parent sources stack (preserves order).
	 * @return void
	 */
	public static function push( array $slots_map, \WP_Block $component_block, ?\WP_Block $parent_component_block = null, array $parent_dynamic_entries = array(), array $parent_sources = array() ): void {
		self::$context_stack[] = array(
			'slots'                 => $slots_map,
			'component_block'        => $component_block,
			'parent_component_block' => $parent_component_block,
			'parent_dynamic_entries' => $parent_dynamic_entries,
			'parent_sources'         => $parent_sources,
		);
	}

	/**
	 * Pop the most recent component slot context from the stack.
	 *
	 * @return void
	 */
	public static function pop(): void {
		array_pop( self::$context_stack );
	}

	/**
	 * Get the current slots map (from the top of the stack).
	 *
	 * @return array<string, array<int, array<string, mixed>>> Slot name => array of parsed block data.
	 */
	public static function current_slots(): array {
		if ( empty( self::$context_stack ) ) {
			return array();
		}

		$top_index = count( self::$context_stack ) - 1;
		$top = self::$context_stack[ $top_index ];

		/**
		 * Type assertion for stack top element.
		 *
		 * @var array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>} $top
		 */
		return $top['slots'];
	}

	/**
	 * Get the captured parent dynamic entries (non-global) from the top of the stack.
	 *
	 * @return array<int, DynamicContentEntry>
	 */
	public static function current_parent_dynamic_entries(): array {
		if ( empty( self::$context_stack ) ) {
			return array();
		}

		$top_index = count( self::$context_stack ) - 1;
		$top = self::$context_stack[ $top_index ];

		/**
		 * Type assertion for stack top element.
		 *
		 * @var array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>} $top
		 */
		return $top['parent_dynamic_entries'];
	}

	/**
	 * Get the captured parent sources stack from the top of the stack.
	 *
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public static function current_parent_sources(): array {
		if ( empty( self::$context_stack ) ) {
			return array();
		}

		$top_index = count( self::$context_stack ) - 1;
		$top = self::$context_stack[ $top_index ];

		/**
		 * Type assertion for stack top element.
		 *
		 * @var array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>} $top
		 */
		return $top['parent_sources'];
	}

	/**
	 * Get the current component block (from the top of the stack).
	 *
	 * @return \WP_Block|null The component block instance, or null if stack is empty.
	 */
	public static function current_component_block(): ?\WP_Block {
		if ( empty( self::$context_stack ) ) {
			return null;
		}

		$top_index = count( self::$context_stack ) - 1;
		$top = self::$context_stack[ $top_index ];

		/**
		 * Type assertion for stack top element.
		 *
		 * @var array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>} $top
		 */
		return $top['component_block'];
	}

	/**
	 * Get the current parent component block (from the top of the stack).
	 *
	 * @return \WP_Block|null The parent component block instance, or null if stack is empty or no parent exists.
	 */
	public static function current_parent_component_block(): ?\WP_Block {
		if ( empty( self::$context_stack ) ) {
			return null;
		}

		$top_index = count( self::$context_stack ) - 1;
		$top = self::$context_stack[ $top_index ];

		/**
		 * Type assertion for stack top element.
		 *
		 * @var array{slots: array<string, array<int, array<string, mixed>>>, component_block: \WP_Block, parent_component_block: \WP_Block|null, parent_dynamic_entries: array<int, DynamicContentEntry>, parent_sources: array<int, array{key: string, source: mixed}>} $top
		 */
		return $top['parent_component_block'];
	}

	/**
	 * Extract slot contents from component instance inner blocks.
	 * Only extracts direct slot children to avoid scope bleeding between nested components.
	 * Uses parsed_block from WP_Block instances to avoid manual conversion.
	 *
	 * @param \WP_Block $block The component block instance.
	 * @return array<string, array<int, array<string, mixed>>> Array of slot name => array of parsed block data.
	 */
	public static function extract_slot_contents( \WP_Block $block ): array {
		$slots_map = array();

		// Get inner blocks from the component instance
		$inner_blocks = $block->inner_blocks;
		foreach ( $inner_blocks as $inner_block ) {
			if ( ! ( $inner_block instanceof \WP_Block ) ) {
				continue;
			}

			// Check if this is a slot-content block
			if ( 'etch/slot-content' !== $inner_block->name ) {
				continue;
			}

			$slot_attrs = SlotContentAttributes::from_array( $inner_block->attributes ?? array() );
			$slot_name = $slot_attrs->name;

			if ( empty( $slot_name ) ) {
				continue;
			}

			// Only use the first slot-content block for each slot name
			// If a slot name already exists in the map, skip this one
			if ( isset( $slots_map[ $slot_name ] ) ) {
				continue;
			}

			$slot_inner_blocks = $inner_block->inner_blocks;
			$slot_parsed_blocks = array();
			foreach ( $slot_inner_blocks as $slot_inner_block ) {
				if ( $slot_inner_block instanceof \WP_Block ) {
					$parsed_block = $slot_inner_block->parsed_block;
					if ( is_array( $parsed_block ) ) {
						$slot_parsed_blocks[] = $parsed_block;
					}
				}
			}
			$slots_map[ $slot_name ] = $slot_parsed_blocks;
		}

		return $slots_map;
	}
}
