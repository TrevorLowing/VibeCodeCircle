<?php
/**
 * BaseBlock abstract class for Etch plugin
 *
 * This file contains the BaseBlock abstract class which serves as the foundation
 * for all block processing classes in the Etch plugin.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Helpers\Flag;
use Etch\Helpers\ServerTiming;
use Etch\Preprocessor\Data\EtchData;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;
use Etch\Preprocessor\Registry\ScriptRegister;
use Etch\Preprocessor\Registry\StylesRegister;
use Etch\Traits\DynamicData;
use WP_Term;

/**
 * BaseBlock abstract class - represents an enhanced WordPress block.
 */
abstract class BaseBlock {
	use DynamicData;

	/**
	 * Block name.
	 *
	 * @var string
	 */
	protected string $blockName;

	/**
	 * Block attributes.
	 *
	 * @var array<string, mixed>
	 */
	protected array $attrs;

	/**
	 * Inner blocks.
	 *
	 * @var array<int, BaseBlock>
	 */
	protected array $innerBlocks;

	/**
	 * Inner HTML.
	 *
	 * @var string
	 */
	protected string $innerHTML;

	/**
	 * Inner content.
	 *
	 * @var list<?string>
	 */
	protected array $innerContent;

	/**
	 * Etch data instance.
	 *
	 * @var EtchData|null
	 */
	protected ?EtchData $etch_data;

	/**
	 * Dynamic context for a block.
	 *
	 * @var array<string, mixed>
	 */
	protected array $context = array();

	/**
	 * Parent block, if any.
	 *
	 * @var BaseBlock|null
	 */
	protected ?BaseBlock $parent;

	/**
	 * Constructor for the BaseBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData|null             $data Etch data instance, null for passthrough blocks.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, ?EtchData $data = null, $context = null, $parent = null ) {
		$this->blockName = $block->blockName;
		$this->attrs = $block->attrs;
		$this->innerHTML = $block->innerHTML;
		$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $block->innerContent );
		$this->etch_data = $data;
		$this->context = $context ?? array();
		$this->parent = $parent;

		$this->add_this_post_context();

		// Register scripts if etch data contains script information
		$this->register_scripts();

		// Register styles if etch data contains style information
		$this->register_styles();

		// Convert raw inner blocks to BaseBlock instances
		$this->innerBlocks = array();
		$innerBlocksArray = EtchTypeAsserter::to_array( $block->innerBlocks );
		foreach ( $innerBlocksArray as $rawBlock ) {
			if ( is_array( $rawBlock ) ) {
				$this->innerBlocks[] = self::create_from_block( $rawBlock, $this->context, $this );
			}
		}
	}

	/**
	 * Add the current post context to the block context.
	 *
	 * @return void
	 */
	private function add_this_post_context() {
		ServerTiming::start( 'add_this_post_context', 'Add the current post context to the block context.' );

			// Only add the context if it is not already set
		if ( ! isset( $this->context['this'] ) || ! is_array( $this->context['this'] ) ) {
			$post = get_post();
			if ( null !== $post ) {
				$this->context['this'] = $this->get_dynamic_data( $post );
			}
		}

		if ( ! isset( $this->context['user'] ) || ! is_array( $this->context['user'] ) ) {
			$current_user = wp_get_current_user();
			if ( $current_user->exists() ) {
				$this->context['user'] = $this->get_dynamic_user_data( $current_user );
			}
		}

		if ( ! isset( $this->context['site'] ) || ! is_array( $this->context['site'] ) ) {
			// Get site data
			$this->context['site'] = $this->get_dynamic_site_data();
		}

		if ( ! isset( $this->context['url'] ) || ! is_array( $this->context['url'] ) ) {
			// Get dynamic URL data
			$this->context['url'] = $this->get_dynamic_url_data();
		}

		if ( ! isset( $this->context['options'] ) || ! is_array( $this->context['options'] ) ) {
			// Get dynamic options data
			$this->context['options'] = $this->get_dynamic_option_pages_data();
		}

		if ( ! isset( $this->context['term'] ) && ! isset( $this->context['taxonomy'] ) ) {
			// Get taxonomy and term context if we're on a taxonomy archive
			if ( is_tax() || is_category() || is_tag() ) {
				$queried_object = get_queried_object();
				if ( $queried_object instanceof WP_Term ) {
					$this->context['term'] = $this->get_dynamic_term_data( $queried_object );
					$this->context['taxonomy'] = $this->get_dynamic_tax_data( $queried_object->taxonomy );
				}
			}
		}

		if ( ! isset( $this->context['archive'] ) || ! is_array( $this->context['archive'] ) ) {
			$this->context['archive'] = $this->get_dynamic_archive_data();
		}

		ServerTiming::stop( 'add_this_post_context' );
	}

	/**
	 * Add a context to the block context.
	 *
	 * @param string               $key The key to add.
	 * @param array<string, mixed> $value The value to add.
	 * @return void
	 */
	public function add_context( $key, $value ) {
		$this->context[ $key ] = $value;
	}

	/**
	 * Get the context.
	 *
	 * @return array<string, mixed> Context.
	 */
	public function get_context() {
		return $this->context;
	}

	/**
	 * Reset the context.
	 *
	 * @return void
	 */
	public function reset_context() {
		$this->context = array(
			'this' => $this->context['this'],
			'user' => $this->context['user'],
		);
	}

	/**
	 * Register scripts with the ScriptManager if etch data contains script information.
	 *
	 * @return void
	 */
	private function register_scripts(): void {
		// Only process if we have etch data
		if ( null === $this->etch_data ) {
			return;
		}

		// Check if etch data has script information
		if ( null === $this->etch_data->script ) {
			return;
		}

		// Convert script to array and extract script code using EtchTypeAsserter
		$script_array = EtchTypeAsserter::to_array( $this->etch_data->script );
		$script_code = EtchTypeAsserter::to_string_or_null( $script_array['code'] ?? null );

		// Register script with ScriptRegister if we have valid script code
		if ( null !== $script_code && '' !== $script_code ) {
			ScriptRegister::register_script( $script_code );
		}
	}

	/**
	 * Register styles with the StylesRegister if etch data contains style information.
	 *
	 * @return void
	 */
	private function register_styles(): void {
		// Only process if we have etch data
		if ( null === $this->etch_data ) {
			return;
		}

		$all_styles = array();

		// Extract styles from main etch data
		if ( null !== $this->etch_data->styles ) {
			$all_styles = array_merge( $all_styles, $this->etch_data->styles );
		}

		// Extract styles from nested data
		if ( $this->etch_data->has_nested_data() ) {
			foreach ( $this->etch_data->nestedData as $nested_item ) {
				if ( null !== $nested_item->styles ) {
					$all_styles = array_merge( $all_styles, $nested_item->styles );
				}
			}
		}

		// Register styles with StylesRegister if we have any styles
		if ( ! empty( $all_styles ) ) {
			StylesRegister::register_styles( array_unique( $all_styles ) );
		}
	}

	/**
	 * Get the WordPress block data as an array.
	 *
	 * @return array<string, mixed> WordPress block data.
	 */
	public function get_raw_block(): array {
		// Convert BaseBlock instances back to arrays for WordPress compatibility
		$innerBlocksArray = array();
		foreach ( $this->innerBlocks as $innerBlock ) {
			$innerBlocksArray[] = $innerBlock->get_raw_block();
		}

		return array(
			'blockName' => $this->blockName,
			'attrs' => $this->attrs,
			'innerBlocks' => $innerBlocksArray,
			'innerHTML' => $this->innerHTML,
			'innerContent' => $this->innerContent,
		);
	}

	/**
	 * Set the WordPress block data from a WpBlock.
	 *
	 * @param WpBlock $block WordPress block data.
	 * @return void
	 */
	public function set_block( WpBlock $block ): void {
		$this->blockName = $block->blockName;
		$this->attrs = $block->attrs;
		$this->innerHTML = $block->innerHTML;
		$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $block->innerContent );

		// Convert raw inner blocks to BaseBlock instances
		$this->innerBlocks = array();
		$innerBlocksArray = EtchTypeAsserter::to_array( $block->innerBlocks );
		foreach ( $innerBlocksArray as $rawBlock ) {
			if ( is_array( $rawBlock ) ) {
				$this->innerBlocks[] = self::create_from_block( $rawBlock, $this->context, $this );
			}
		}
	}

	/**
	 * Get the Etch data instance.
	 *
	 * @return EtchData|null Etch data instance or null.
	 */
	public function get_etch_data(): ?EtchData {
		return $this->etch_data;
	}

	/**
	 * Set the Etch data instance.
	 *
	 * @param EtchData|null $etch_data Etch data instance.
	 * @return void
	 */
	public function set_etch_data( ?EtchData $etch_data ): void {
		$this->etch_data = $etch_data;
	}

	/**
	 * Check if this block has Etch data.
	 *
	 * @return bool True if has Etch data.
	 */
	public function has_data(): bool {
		return null !== $this->etch_data;
	}

	/**
	 * Get the block name.
	 *
	 * @return string Block name.
	 */
	public function get_block_name(): string {
		return $this->blockName;
	}

	/**
	 * Set the block name.
	 *
	 * @param string $blockName Block name.
	 * @return void
	 */
	public function set_block_name( string $blockName ): void {
		$this->blockName = $blockName;
	}

	/**
	 * Get block attributes.
	 *
	 * @return array<string, mixed> Block attributes.
	 */
	public function get_attributes(): array {
		return $this->attrs;
	}

	/**
	 * Set block attributes.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return void
	 */
	public function set_attributes( array $attrs ): void {
		$this->attrs = $attrs;
	}

	/**
	 * Get inner blocks as BaseBlock instances.
	 *
	 * @return array<int, BaseBlock> Inner blocks.
	 */
	public function get_inner_blocks(): array {
		return $this->innerBlocks;
	}

	/**
	 * Get inner blocks as arrays.
	 *
	 * @return array<int, array<string, mixed>> Inner blocks.
	 */
	public function get_inner_blocks_as_raw_blocks(): array {
		return $this->process_inner_blocks_to_raw_blocks( $this->innerBlocks );
	}

	/**
	 * Set inner blocks from BaseBlock instances.
	 *
	 * @param array<int, BaseBlock> $innerBlocks Inner blocks as BaseBlock instances.
	 * @return void
	 */
	public function set_inner_blocks( array $innerBlocks ): void {
		$this->innerBlocks = $innerBlocks;
	}

	/**
	 * Set inner blocks from raw WordPress block arrays.
	 *
	 * @param array<int, array<string, mixed>> $rawInnerBlocks Raw inner blocks arrays.
	 * @return void
	 */
	public function set_inner_blocks_from_raw_blocks( array $rawInnerBlocks ): void {
		$this->innerBlocks = array();
		foreach ( $rawInnerBlocks as $rawBlock ) {
			if ( is_array( $rawBlock ) ) {
				$this->innerBlocks[] = self::create_from_block( $rawBlock, $this->context, $this );
			}
		}
	}

	/**
	 * Add an inner block.
	 *
	 * @param BaseBlock $block Inner block to add.
	 * @return void
	 */
	public function add_inner_block( BaseBlock $block ): void {
		$this->innerBlocks[] = $block;
	}

	/**
	 * Remove an inner block by index.
	 *
	 * @param int $index Index of the block to remove.
	 * @return void
	 */
	public function remove_inner_block( int $index ): void {
		if ( isset( $this->innerBlocks[ $index ] ) ) {
			unset( $this->innerBlocks[ $index ] );
			$this->innerBlocks = array_values( $this->innerBlocks ); // Re-index array
		}
	}

	/**
	 * Get the number of inner blocks.
	 *
	 * @return int Number of inner blocks.
	 */
	public function get_inner_blocks_count(): int {
		return count( $this->innerBlocks );
	}

	/**
	 * Check if this block has inner blocks.
	 *
	 * @return bool True if has inner blocks.
	 */
	public function has_inner_blocks(): bool {
		return ! empty( $this->innerBlocks );
	}

	/**
	 * Get inner HTML.
	 *
	 * @return string Inner HTML.
	 */
	public function get_inner_html(): string {
		return $this->innerHTML;
	}

	/**
	 * Set inner HTML.
	 *
	 * @param string $innerHTML Inner HTML.
	 * @return void
	 */
	public function set_inner_html( string $innerHTML ): void {
		$this->innerHTML = $innerHTML;
	}

	/**
	 * Get inner content.
	 *
	 * @return array<int, string|null> Inner content.
	 */
	public function get_inner_content(): array {
		return $this->innerContent;
	}

	/**
	 * Set inner content.
	 *
	 * @param array<int, string|null> $innerContent Inner content.
	 * @return void
	 */
	public function set_inner_content( array $innerContent ): void {
		$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $innerContent );
	}

	/**
	 * Update inner content array to match the number of inner blocks.
	 * This is crucial when inner blocks expand (e.g., loops).
	 *
	 * @return void
	 */
	protected function update_inner_content_slots_count(): void {
		$inner_blocks_count = count( $this->innerBlocks );
		$inner_content_nulls = 0;

		// For blocks with wrapper tags, we need inner_blocks_count + 2 slots (for opening and closing tags)
		// For blocks without wrapper tags, we need inner_blocks_count slots
		$expected_inner_content_length = $this->has_wrapper_tags() ? $inner_blocks_count + 2 : $inner_blocks_count;

		if ( count( $this->innerContent ) === $expected_inner_content_length ) {
			return;
		}

		// Count existing null placeholders
		foreach ( $this->innerContent as $content ) {
			if ( null === $content ) {
				$inner_content_nulls++;
			}
		}

		// If we have more inner blocks than null placeholders, we need to add more
		if ( $inner_blocks_count > $inner_content_nulls ) {

			// For blocks with wrapper tags, we need to insert nulls before the closing tag
			if ( $this->has_wrapper_tags() ) {
				// Find the last non-null item (should be closing tag)
				$last_content_index = count( $this->innerContent ) - 1;

				// Insert additional null placeholders before the closing tag
				$nulls_to_add = $inner_blocks_count - $inner_content_nulls;
				for ( $i = 0; $i < $nulls_to_add; $i++ ) {
					array_splice( $this->innerContent, $last_content_index, 0, array( null ) );
				}
				$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $this->innerContent );
			} else {
				// For blocks without wrapper tags, just add nulls
				$nulls_to_add = $inner_blocks_count - $inner_content_nulls;
				for ( $i = 0; $i < $nulls_to_add; $i++ ) {
					$this->innerContent[] = null;
				}
				$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $this->innerContent );
			}
		}
	}

	/**
	 * Check if the block has wrapper tags in innerContent.
	 *
	 * @return bool True if has wrapper tags.
	 */
	protected function has_wrapper_tags(): bool {
		if ( count( $this->innerContent ) < 2 ) {
			return false;
		}

		return is_string( $this->innerContent[0] ) && is_string( $this->innerContent[ count( $this->innerContent ) - 1 ] );
	}

	/**
	 * Static helper method to adjust innerContent array to match the number of inner blocks.
	 *
	 * @param array<int, string|null> $inner_content Original innerContent array.
	 * @param int                     $new_count New number of inner blocks.
	 * @return array<int, string|null> Adjusted innerContent array.
	 */
	public static function adjust_inner_content_array( array $inner_content, int $new_count ): array {
		// If empty innerContent or new_count is 0, return appropriate array
		if ( empty( $inner_content ) ) {
			return array_fill( 0, $new_count, null );
		}

		// Check if this block has wrapper tags (first and last elements are strings)
		$has_wrapper = count( $inner_content ) >= 2
			&& is_string( $inner_content[0] )
			&& is_string( $inner_content[ count( $inner_content ) - 1 ] );

		if ( $has_wrapper ) {
			// For blocks with wrapper tags: [opening_tag, null, null, ..., closing_tag]
			$opening_tag = $inner_content[0];
			$closing_tag = $inner_content[ count( $inner_content ) - 1 ];

			$result = array( $opening_tag );
			for ( $i = 0; $i < $new_count; $i++ ) {
				$result[] = null;
			}
			$result[] = $closing_tag;

			return $result;
		} else {
			// For blocks without wrapper tags: [null, null, ...]
			return array_fill( 0, $new_count, null );
		}
	}

	/**
	 * Process the block and return the transformed block data.
	 * This method must be implemented by all subclasses.
	 *
	 * @return array<string, mixed>|array<int, array<string, mixed>>|null Transformed block data, or null if block should be skipped.
	 */
	abstract public function process();

	/**
	 * Process inner blocks and return a flat array of processed block data.
	 * Handles both single blocks and blocks that expand into multiple blocks.
	 *
	 * @param array<int, BaseBlock> $inner_blocks The inner blocks to process.
	 * @return array<int, array<string, mixed>> Flat array of processed block data.
	 */
	protected function process_inner_blocks_to_raw_blocks( array $inner_blocks ): array {
		$processed_inner_blocks_raw_blocks = array();
		$original_count = count( $inner_blocks );

		foreach ( $inner_blocks as $inner_block ) {
			$processed_result = $inner_block->process();
			$this->add_processed_result_to_raw_blocks( $processed_result, $processed_inner_blocks_raw_blocks );
		}

		// If some blocks returned null, adjust innerContent to match the new block count
		$new_count = count( $processed_inner_blocks_raw_blocks );
		if ( $original_count !== $new_count ) {
			$this->adjust_inner_content_for_removed_blocks( $original_count, $new_count );
		}

		return $processed_inner_blocks_raw_blocks;
	}

	/**
	 * Add a processed result to an array, handling both single blocks and multiple block expansions.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>>|null $processed_result The result from processing a block.
	 * @param array<int, array<string, mixed>>                           $target_array The array to add the result(s) to.
	 * @return void
	 */
	protected function add_processed_result_to_raw_blocks( $processed_result, array &$target_array ): void {
		// Skip null results entirely
		if ( null === $processed_result ) {
			return;
		}

		// Skip empty arrays that do not represent a valid block
		if ( is_array( $processed_result ) && empty( $processed_result ) ) {
			return;
		}

		// Handle case where a single block expands into multiple blocks (e.g., components, loops)
		if ( is_array( $processed_result ) && isset( $processed_result[0] ) && is_array( $processed_result[0] ) ) {
			// This is an array of blocks, add them directly to the result
			foreach ( $processed_result as $expanded_block_data ) {
				$target_array[] = $expanded_block_data;
			}
		} else {
			// This is a single processed block, add it directly
			$target_array[] = $processed_result;
		}
	}

	/**
	 * Adjust innerContent array when blocks have been removed due to null results.
	 *
	 * @param int $original_count Original number of inner blocks.
	 * @param int $new_count New number of inner blocks after processing.
	 * @return void
	 */
	private function adjust_inner_content_for_removed_blocks( int $original_count, int $new_count ): void {
		if ( $original_count === $new_count ) {
			return; // No blocks were removed
		}

		$adjusted_content = self::adjust_inner_content_array( $this->innerContent, $new_count );
		$this->innerContent = EtchTypeAsserter::normalize_to_string_array( $adjusted_content );
	}



	/**
	 * Create a block instance from WordPress block data.
	 * This is a factory method that determines the appropriate block type.
	 *
	 * @param array<string, mixed>      $block WordPress block data.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 * @return BaseBlock Appropriate block instance.
	 */
	public static function create_from_block( array $block, $context = null, $parent = null ): BaseBlock {
		$wpBlock = WpBlock::from_array( $block );
		$etchData = EtchData::from_block( $block );

		// If no valid EtchData, create a passthrough block
		if ( null === $etchData || ! $etchData->is_etch_block() ) {
			return new PassthroughBlock( $wpBlock, null, $context, $parent );
		}

		// Create appropriate block type based on EtchData type
		switch ( $etchData->type ) {
			case 'html':
			case 'text':
				return new HtmlBlock( $wpBlock, $etchData, $context, $parent );
			case 'component':
				return new ComponentBlock( $wpBlock, $etchData, $context, $parent );
			case 'condition':
				return new ConditionBlock( $wpBlock, $etchData, $context, $parent );
			case 'loop':
				return new LoopBlock( $wpBlock, $etchData, $context, $parent );
			default:
				return new PassthroughKillBlock( $wpBlock, $etchData, $context, $parent );
		}
	}
}
