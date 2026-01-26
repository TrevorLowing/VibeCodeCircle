<?php
/**
 * WpBlock class for Etch plugin
 *
 * This file contains the WpBlock class which represents a WordPress block
 * structure as a proper PHP object instead of using PHPStan type annotations.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

/**
 * WpBlock class - represents a WordPress block structure.
 */
class WpBlock {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	public string $blockName;

	/**
	 * Block attributes.
	 *
	 * @var array<string, mixed>
	 */
	public array $attrs;

	/**
	 * Inner blocks.
	 *
	 * @var array<int|string, array<mixed>>
	 */
	public array $innerBlocks;

	/**
	 * Inner HTML.
	 *
	 * @var string
	 */
	public string $innerHTML;

	/**
	 * Inner content.
	 *
	 * @var array<int, string|null>
	 */
	public array $innerContent;

	/**
	 * Constructor for WpBlock.
	 *
	 * @param string                          $blockName Block name.
	 * @param array<string, mixed>            $attrs Block attributes.
	 * @param array<int|string, array<mixed>> $innerBlocks Inner blocks.
	 * @param string                          $innerHTML Inner HTML.
	 * @param array<int, string|null>         $innerContent Inner content.
	 */
	public function __construct(
		string $blockName = 'core/group',
		array $attrs = array(),
		array $innerBlocks = array(),
		string $innerHTML = '',
		array $innerContent = array()
	) {
		$this->blockName = $blockName;
		$this->attrs = $attrs;
		$this->innerBlocks = $innerBlocks;
		$this->innerHTML = $innerHTML;
		$this->innerContent = $innerContent;
	}

	/**
	 * Create WpBlock from WordPress block array.
	 *
	 * @param array<string, mixed> $block WordPress block data.
	 * @return self WpBlock instance.
	 */
	public static function from_array( array $block ): self {
		return new self(
			is_string( $block['blockName'] ?? null ) ? $block['blockName'] : 'core/group',
			is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(),
			is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array(),
			is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '',
			is_array( $block['innerContent'] ?? null ) ? $block['innerContent'] : array()
		);
	}

	/**
	 * Convert WpBlock to WordPress block array.
	 *
	 * @return array<string, mixed> WordPress block data array.
	 */
	public function to_array(): array {
		return array(
			'blockName' => $this->blockName,
			'attrs' => $this->attrs,
			'innerBlocks' => $this->innerBlocks,
			'innerHTML' => $this->innerHTML,
			'innerContent' => $this->innerContent,
		);
	}
}
