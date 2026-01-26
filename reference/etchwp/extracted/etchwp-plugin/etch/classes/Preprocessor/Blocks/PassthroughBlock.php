<?php
/**
 * PassthroughBlock class for Etch plugin
 *
 * This file contains the PassthroughBlock class which handles blocks
 * that don't have EtchData or can't be processed by other block types.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Preprocessor\Data\EtchData;

/**
 * PassthroughBlock class for handling blocks without EtchData or unprocessable blocks.
 */
class PassthroughBlock extends BaseBlock {

	/**
	 * Constructor for the PassthroughBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData|null             $data Etch data instance (usually null for passthrough).
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, ?EtchData $data = null, $context = null, $parent = null ) {
		parent::__construct( $block, $data, $context, $parent );
	}

	/**
	 * Process the passthrough block.
	 * For passthrough blocks, we just process inner blocks recursively
	 * without modifying the current block structure.
	 *
	 * @return array<string, mixed> The block data with processed inner blocks.
	 */
	public function process(): array {
		// Process inner blocks recursively if they exist
		$inner_blocks = $this->get_inner_blocks();
		if ( ! empty( $inner_blocks ) ) {
			$processed_inner_blocks_raw_blocks = $this->process_inner_blocks_to_raw_blocks( $inner_blocks );

			// Set the processed inner blocks directly as arrays in the current block
			$this->set_inner_blocks_from_raw_blocks( $processed_inner_blocks_raw_blocks );

			// We need this for loops/components to work properly
			$this->update_inner_content_slots_count();
		}

		return $this->get_raw_block();
	}
}
