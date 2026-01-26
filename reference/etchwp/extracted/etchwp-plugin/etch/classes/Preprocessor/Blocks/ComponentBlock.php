<?php
/**
 * ComponentBlock class for Etch plugin
 *
 * This file contains the ComponentBlock class which processes
 * component blocks with slot support and property handling.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Helpers\Logger;
use Etch\Preprocessor\Data\EtchData;
use Etch\Preprocessor\Data\EtchDataComponentProperty;
use Etch\Preprocessor\Data\EtchDataGlobalLoop;
use Etch\Preprocessor\Utilities\EtchParser;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;
use Etch\Preprocessor\Utilities\LoopHandlerManager;

/**
 * ComponentBlock class for processing component blocks with EtchData.
 */
class ComponentBlock extends BaseBlock {

	/**
	 * Constructor for the ComponentBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData|null             $data Etch data instance, null for passthrough blocks.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, ?EtchData $data = null, $context = null, $parent = null ) {
		// Here we need some special handling because the core/block actually does not support children.
		// So we need to hoist the innerBlocks out of the etchData and
		// store them in the block itself, so that we can process them later.
		$block->innerBlocks = $data?->innerBlocks ?? $block->innerBlocks;
		$block->innerContent = $data?->innerBlocks ? array_fill( 0, count( $data->innerBlocks ), null ) : $block->innerContent;

		parent::__construct( $block, $data, $context, $parent );
	}


	/**
	 * Component definition data.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $component_data = null;

	/**
	 * Processed component properties.
	 *
	 * @var array<string, mixed>
	 */
	private array $component_props = array();

	/**
	 * Slot contents from the component instance.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $slot_contents = array();

	/**
	 * Parent context before component processing.
	 *
	 * @var array<string, mixed>
	 */
	private array $parent_context = array();

	/**
	 * Process the component block and return the transformed block data.
	 *
	 * @return array<int, array<string, mixed>>|null Array of transformed blocks or null if component not found.
	 */
	public function process(): ?array {
		$etch_data = $this->get_etch_data();

		if ( null === $etch_data || ! $etch_data->is_component_block() || null === $etch_data->component ) {
			return null;
		}

		// Store parent context before component processing
		$this->parent_context = $this->get_context();

		// Load component definition
		if ( ! $this->load_component_data( $etch_data->component ) ) {
			return null;
		}

		// Extract slot contents from instance inner blocks
		$this->extract_slot_contents();

		// Prepare component properties
		$this->prepare_component_properties();

		// Get component definition blocks
		$component_blocks = EtchTypeAsserter::to_indexed_array( $this->component_data['blocks'] ?? array() );
		if ( empty( $component_blocks ) ) {
			return null;
		}

		// Create component context with 'props' key
		// Also preserve parent context and add props - this allows both component props and parent data access
		$component_context = array_merge(
			$this->parent_context, // Start with parent context
			array( 'props' => $this->component_props ) // Add/override with component props
		);

		// Process component blocks with slot replacement and context
		$processed_blocks = $this->process_component_blocks( $component_blocks, $component_context );

		// Return null if no blocks were produced after processing
		return empty( $processed_blocks ) ? null : $processed_blocks;
	}

	/**
	 * Load component data from post.
	 *
	 * @param int $component_id Component post ID.
	 * @return bool True if component data loaded successfully.
	 */
	private function load_component_data( int $component_id ): bool {
		$source_post = get_post( $component_id );

		if ( ! $source_post || 'wp_block' !== $source_post->post_type ) {
			return false;
		}

		$blocks = parse_blocks( $source_post->post_content );
		$properties = get_post_meta( $component_id, 'etch_component_properties', true );

		$this->component_data = array(
			'id'          => $source_post->ID,
			'name'        => $source_post->post_title,
			'key'         => get_post_meta( $component_id, 'etch_component_html_key', true ) ? get_post_meta( $component_id, 'etch_component_html_key', true ) : '',
			'blocks'      => $blocks,
			'properties'  => EtchTypeAsserter::to_array( $properties ),
			'description' => $source_post->post_excerpt,
		);

		return true;
	}

	/**
	 * Extract slot contents from component instance inner blocks.
	 * Only extracts direct slot children to avoid scope bleeding between nested components.
	 *
	 * @return void
	 */
	private function extract_slot_contents(): void {
		$inner_blocks = $this->get_inner_blocks();

		foreach ( $inner_blocks as $inner_block ) {
			$inner_etch_data = $inner_block->get_etch_data();

			if ( null === $inner_etch_data || ! $inner_etch_data->is_slot_block() ) {
				continue;
			}

			$slot_name = $inner_etch_data->slot;
			if ( empty( $slot_name ) ) {
				continue;
			}

			// Store the raw block data of the slot's inner blocks as its content
			$slot_inner_blocks = $inner_block->get_inner_blocks();
			$slot_raw_blocks = array();
			foreach ( $slot_inner_blocks as $slot_inner_block ) {
				$slot_raw_blocks[] = $slot_inner_block->get_raw_block();
			}
			$this->slot_contents[ $slot_name ] = $slot_raw_blocks;
		}
	}

	/**
	 * Prepare component properties by merging defaults with instance attributes.
	 *
	 * @return void
	 */
	private function prepare_component_properties(): void {
		$property_definitions = EtchTypeAsserter::to_array( $this->component_data['properties'] ?? array() );
		$instance_attributes = $this->etch_data->attributes ?? array();

		// First, process property definitions to get defaults and types
		$properties = array();
		foreach ( $property_definitions as $prop_data ) {
			if ( ! is_array( $prop_data ) ) {
				continue;
			}

			$property = EtchDataComponentProperty::from_array( $prop_data );
			if ( null !== $property && $property->is_valid() ) {
				$properties[ $property->key ] = $property;
			}
		}

		// Build final props array
		$this->component_props = array();

		// Start with defaults
		foreach ( $properties as $key => $property ) {
			$default_value = $property->get_typed_default();

			// Handle specialized array type - resolve global loops if needed
			if ( 'array' === $property->specialized ) {
				$default_value = $this->resolve_array_property_value( $default_value, $property );
			}

			$this->component_props[ $key ] = EtchParser::type_safe_replacement( $default_value, $this->get_context() );
		}

		// Override with instance attributes, applying type casting and context parsing
		foreach ( $instance_attributes as $key => $value ) {
			// Only process attributes that have property definitions
			if ( ! isset( $properties[ $key ] ) ) {
				continue;
			}

			$parsed_value = EtchParser::type_safe_replacement( $value, $this->get_context() );

			// Handle specialized array type - resolve global loops if needed
			if ( 'array' === $properties[ $key ]->specialized ) {
				$parsed_value = $this->resolve_array_property_value( $parsed_value, $properties[ $key ] );
			}

			$properties[ $key ]->set_value( $parsed_value );
			$this->component_props[ $key ] = $properties[ $key ]->get_value();
		}
	}

	/**
	 * Resolve array property value from global loops or context expressions.
	 *
	 * @param mixed                     $value The property value to resolve.
	 * @param EtchDataComponentProperty $property The property definition.
	 * @return array<mixed> The resolved array data.
	 */
	private function resolve_array_property_value( $value, EtchDataComponentProperty $property ): array {
		// If already an array, return as is
		if ( is_array( $value ) ) {
			return $value;
		}

		// Convert to string for processing
		$string_value = EtchTypeAsserter::to_string( $value );

		// If empty, return empty array
		if ( empty( $string_value ) ) {
			return array();
		}

		// Check if it's a global loop key (by database key first)
		$loop_presets = LoopHandlerManager::get_loop_presets();
		if ( isset( $loop_presets[ $string_value ] ) ) {
			return LoopHandlerManager::get_loop_preset_data( $string_value, array() );
		}

		// Check if it matches any loop by key property
		$found_loop_id = LoopHandlerManager::find_loop_by_key( $string_value );
		if ( $found_loop_id ) {
			return LoopHandlerManager::get_loop_preset_data( $found_loop_id, array() );
		}

		// Try to parse as context expression using EtchParser
		$parsed = EtchParser::type_safe_replacement( $string_value, $this->get_context() );
		if ( is_array( $parsed ) ) {
			return $parsed;
		}

		// Fall back to attempting JSON decode or comma-separated parsing
		// (handled by the property's cast_to_array method)
		$property->set_value( $string_value );
		$result = $property->get_value();
		return is_array( $result ) ? $result : array();
	}



		/**
		 * Process component blocks with slot replacement and context.
		 *
		 * @param array<int, array<string, mixed>> $blocks Component definition blocks.
		 * @param array<string, mixed>             $component_context Component-specific context.
		 * @return array<int, array<string, mixed>> Processed blocks.
		 */
	private function process_component_blocks( array $blocks, array $component_context ): array {
		$processed_blocks = array();

		$raw_blocks = $this->replace_slot_placeholders( $blocks, $component_context );

		foreach ( $raw_blocks as $block_data ) {
			if ( ! is_array( $block_data ) ) {
				continue;
			}

			// Determine context based on whether this is slot content
			$block_context = $component_context;
			if ( isset( $block_data['etch_slot_content_context_flag'] ) ) {
				// Slot content gets parent context (from before component processing)
				$block_context = $this->parent_context;

				// Clean up the slot content context flag
				unset( $block_data['etch_slot_content_context_flag'] );
			}

			// This is where we reset the context escaping the previous context scope.
			$block = self::create_from_block( $block_data, $block_context );

			$processed_result = $block->process();
			$this->add_processed_result_to_raw_blocks( $processed_result, $processed_blocks );
		}

		return $processed_blocks;
	}

	/**
	 * Recursively replace slot placeholders in a block and its inner blocks.
	 *
	 * @param array<array<string, mixed>> $blocks The blocks array to process.
	 * @param array<string, mixed>        $component_context Component context.
	 * @return array<array<string, mixed>> Processed block data with slot placeholders replaced.
	 */
	private function replace_slot_placeholders( array $blocks, array $component_context ) {
		$processed_blocks = array();

		foreach ( $blocks as $block_data ) {
			// Check if current block is slot placeholder itself
			$etch_data = EtchData::from_block( $block_data );

			if ( null !== $etch_data && $etch_data->is_slot_placeholder() ) {
				$replaced_blocks = $this->process_slot_placeholder( $etch_data, $component_context );
				$processed_blocks = array_merge( $processed_blocks, $replaced_blocks );
			} else if ( isset( $block_data['innerBlocks'] ) && is_array( $block_data['innerBlocks'] ) ) {
				$original_inner_count = count( $block_data['innerBlocks'] );

				// Recursively process inner blocks
				$block_data['innerBlocks'] = $this->replace_slot_placeholders( $block_data['innerBlocks'], $component_context );

				// Update innerContent to match the new innerBlocks count
				$new_inner_count = count( $block_data['innerBlocks'] );
				if ( $original_inner_count !== $new_inner_count ) {
					$inner_content = $block_data['innerContent'] ?? array();
					// Ensure $inner_content is array<int, string|null>
					if ( ! is_array( $inner_content ) ) {
						$inner_content = array();
					}
					$block_data['innerContent'] = self::adjust_inner_content_array(
						$inner_content,
						$new_inner_count
					);
				}

				$processed_blocks[] = $block_data;
			} else {
				$processed_blocks[] = $block_data;
			}
		}

		return $processed_blocks;
	}

	/**
	 * Process a slot placeholder and add its content to the processed blocks.
	 *
	 * @param EtchData             $inner_etch_data Etch data of the slot placeholder.
	 * @param array<string, mixed> $component_context Component context.
	 * @return array<int, array<string, mixed>> Processed blocks to insert in place of the slot placeholder.
	 */
	private function process_slot_placeholder( EtchData $inner_etch_data, array $component_context ) {
		$slot_name = $inner_etch_data->slot;
		$new_blocks = array();

		// Early return if slot name is empty or slot content doesn't exist
		// Just remove it completely
		if ( empty( $slot_name ) || ! isset( $this->slot_contents[ $slot_name ] ) ) {
			return array();
		}

		// Add raw block data from slot content - these will go through BaseBlock flow later
		foreach ( $this->slot_contents[ $slot_name ] as $raw_block_data ) {
			// Recursively mark this block and all its inner blocks as slot content
			$raw_block_data = $this->mark_as_slot_content_recursively( $raw_block_data );

			$new_blocks[] = $raw_block_data;
		}

		return $new_blocks;
	}

	/**
	 * Recursively mark a block and all its inner blocks as slot content.
	 * This ensures the etch_slot_content_context_flag is set throughout the entire block hierarchy.
	 *
	 * @param array<string, mixed> $block_data Block data to mark.
	 * @return array<string, mixed> Block data with slot content flag set.
	 */
	private function mark_as_slot_content_recursively( array $block_data ): array {
		// Set the slot content flag on this block
		$block_data['etch_slot_content_context_flag'] = true;

		// Recursively mark all inner blocks if they exist
		if ( isset( $block_data['innerBlocks'] ) && is_array( $block_data['innerBlocks'] ) ) {
			foreach ( $block_data['innerBlocks'] as $index => $inner_block ) {
				if ( is_array( $inner_block ) ) {
					$block_data['innerBlocks'][ $index ] = $this->mark_as_slot_content_recursively( $inner_block );
				}
			}
		}

		return $block_data;
	}
}
