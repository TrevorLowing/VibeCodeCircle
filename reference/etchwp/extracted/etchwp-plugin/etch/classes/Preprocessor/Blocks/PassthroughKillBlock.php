<?php
/**
 * PassthroughKillBlock class for Etch plugin
 *
 * This file contains the PassthroughKillBlock class which handles blocks
 * that should be completely preserved without any etch processing.
 * Used for component, condition, and loop blocks where the original
 * WordPress block structure must be maintained.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Preprocessor\Data\EtchData;

/**
 * PassthroughKillBlock class for preserving blocks without any etch processing.
 * This block type returns the original WordPress block data unchanged,
 * without processing inner blocks or applying any etch transformations.
 */
class PassthroughKillBlock extends BaseBlock {

	/**
	 * Original WordPress block data.
	 *
	 * @var array<string, mixed>
	 */
	private array $original_block_data;

	/**
	 * Constructor for the PassthroughKillBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData|null             $data Etch data instance.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, ?EtchData $data = null, $context = null, $parent = null ) {
		parent::__construct( $block, $data, $context, $parent );

		// Store the original block data to return unchanged
		$this->original_block_data = array(
			'blockName' => $block->blockName,
			'attrs' => $block->attrs,
			'innerBlocks' => $block->innerBlocks,
			'innerHTML' => $block->innerHTML,
			'innerContent' => $block->innerContent,
		);
	}

	/**
	 * Process the passthrough kill block.
	 * This returns the original WordPress block data completely unchanged,
	 * without any etch processing or inner block transformation.
	 *
	 * @return array<string, mixed> The original block data unchanged.
	 */
	public function process(): array {
		return $this->original_block_data;
	}
}
