<?php
/**
 * LoopBlock Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Helpers\Logger;
use Etch\Preprocessor\Data\EtchData;
use Etch\Preprocessor\Utilities\EtchParser;
use Etch\Preprocessor\Utilities\LoopHandlerManager;

/**
 * LoopBlock class for processing Loop blocks with EtchData.
 */
class LoopBlock extends BaseBlock {

	/**
	 * Item ID.
	 *
	 * @var string|null
	 */
	private $itemId;

	/**
	 * Index ID.
	 *
	 * @var string|null
	 */
	private $indexId;

	/**
	 * Target.
	 *
	 * @var string|null
	 */
	private $target;

	/**
	 * Target parts.
	 *
	 * @var array<string>
	 */
	private $target_parts;

	/**
	 * Loop params given on the block.
	 *
	 * @var array<string, mixed>|null
	 */
	private $loop_params;

	/**
	 * Version.
	 *
	 * @var int|null
	 */
	private $version;

	/**
	 * Constructor for the LoopBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData                  $data Etch data instance.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, EtchData $data, $context = null, $parent = null ) {
		parent::__construct( $block, $data, $context, $parent );

		$this->itemId = isset( $this->etch_data->loop->itemId ) ? $this->etch_data->loop->itemId : null;
		$this->indexId = isset( $this->etch_data->loop->indexId ) ? $this->etch_data->loop->indexId : null;
		$this->target = isset( $this->etch_data->loop->target ) ? $this->etch_data->loop->target : null;
		$this->loop_params = $this->get_resolved_loop_params( $context );

		// ! Legacy handling, can be removed in future versions.
		$this->version = isset( $this->etch_data->loop->version ) ? $this->etch_data->loop->version : null;

		$this->target_parts = $this->target ? EtchParser::split_path( $this->target ) : array();
	}

	/**
	 * Process the loop block and return the transformed block data.
	 *
	 * @return array<int, array<string, mixed>> Array of transformed blocks (multiple blocks replacing the loop).
	 */
	public function process(): array {
		if ( $this->target ) {
			return $this->process_loop();
		}

		return $this->get_inner_blocks_as_raw_blocks();
	}

	/**
	 * Process the loop.
	 *
	 * @return array<int, array<string, mixed>> Array of transformed blocks.
	 */
	private function process_loop(): array {
		$context = $this->get_context();

		$resolveTarget = $this->target ?? '';

		$potentialLoopId = $this->target_parts[0] ?? null;
		if ( 2 > $this->version && null !== $potentialLoopId && ! LoopHandlerManager::is_valid_loop_id( $potentialLoopId ) ) {
			// In versions prior to 2, we used the key instead of the loop id
			$potentialLoopId = LoopHandlerManager::find_loop_by_key( $potentialLoopId );
		}

		if ( null !== $potentialLoopId && LoopHandlerManager::is_valid_loop_id( $potentialLoopId ) ) {

			$loop = LoopHandlerManager::strip_loop_params_from_string( $potentialLoopId );

			$loop_data = LoopHandlerManager::get_loop_preset_data( $loop, $this->loop_params ?? array() );

			// Add the loop data to the context under the loop key for later processing
			$context[ $loop ] = $loop_data;

			// When we have a loop id we need to ensure the target does not include args
			$remaining = array_slice( $this->target_parts, 1 );
			$resolveTarget = $loop . ( ! empty( $remaining ) ? '.' . implode( '.', $remaining ) : '' );
		}

		$context_data = EtchParser::process_expression( $resolveTarget, $context );
		$inner_blocks = $this->get_inner_blocks();

		$final_block_tree = array();

		if ( ! is_array( $context_data ) ) {
			return array();
		}

		foreach ( $context_data as $index => $item_data ) {
			$item_block_tree = $this->process_inner_blocks_with_context( $inner_blocks, $item_data, $index );
			$final_block_tree = array_merge( $final_block_tree, $item_block_tree );
		}

		return $final_block_tree;
	}

	/**
	 * Process inner blocks with a specific loop item context.
	 *
	 * @param array<int, BaseBlock> $inner_blocks The inner blocks to process.
	 * @param mixed                 $loop_item The current loop item data (can be array, string, int, etc.).
	 * @param int                   $index The current index in the loop.
	 * @return array<int, array<string, mixed>> Array of processed block data.
	 */
	private function process_inner_blocks_with_context( array $inner_blocks, $loop_item, $index ): array {
		$processed_blocks = array();

		foreach ( $inner_blocks as $inner_block ) {
			// Create a new context that includes the current loop item
			$loop_context = array_merge( $this->get_context(), array( $this->itemId => $loop_item ) );

			if ( $this->indexId ) {
				$loop_context[ $this->indexId ] = $index;
			}

			// Create a new block instance with the loop context
			$block_data = $inner_block->get_raw_block();
			$block = self::create_from_block( $block_data, $loop_context, $this );

			// Process the block and handle both single blocks and multiple block expansions
			$processed_result = $block->process();
			$this->add_processed_result_to_raw_blocks( $processed_result, $processed_blocks );
		}

		return $processed_blocks;
	}


	/**
	 * Resolve loop parameters by processing expressions in the context.
	 *
	 * @param array<string, mixed>|null $context The context array to use for resolving expressions.
	 * @return array<string, mixed>|null The resolved loop parameters or null if not set.
	 */
	private function get_resolved_loop_params( $context ) {
		if ( ! isset( $this->etch_data->loop->loopParams ) ) {
			return null;
		}

		if ( null === $context || ! is_array( $context ) ) {
			return $this->etch_data->loop->loopParams;
		}

		return array_map(
			fn( $value ) => is_string( $value )
			? EtchParser::process_expression( $value, $context )
			: $value,
			$this->etch_data->loop->loopParams
		);
	}
}
