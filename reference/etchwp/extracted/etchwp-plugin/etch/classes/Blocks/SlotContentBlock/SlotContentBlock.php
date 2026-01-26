<?php
/**
 * Slot Content Block
 *
 * Renders slot content blocks. These blocks are used within component instances
 * to provide content that will replace slot placeholders in the component definition.
 * The block itself simply passes through its inner content.
 *
 * @package Etch
 */

namespace Etch\Blocks\SlotContentBlock;

use Etch\Blocks\Types\SlotContentAttributes;
use Etch\Blocks\Global\ScriptRegister;

/**
 * SlotContentBlock class
 *
 * Handles registration of etch/slot-content blocks.
 * The actual slot replacement logic is handled by ComponentBlock.
 */
class SlotContentBlock {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'etch/slot-content',
			array(
				'api_version' => '3',
				'attributes' => array(
					'name' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
					'innerBlocks' => true,
				),
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Render the block
	 *
	 * Simply renders the inner blocks content. The actual slot replacement
	 * is handled by ComponentBlock when processing component definitions.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content (inner blocks).
	 * @param \WP_Block|null       $block WP_Block instance.
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content, $block = null ) {
		$attrs = SlotContentAttributes::from_array( $attributes );

		ScriptRegister::register_script( $attrs );

		// Simply return the inner content - slot replacement is handled by ComponentBlock and SlotPlaceholderBlock
		return $content;
	}
}
