<?php
/**
 * Element Block
 *
 * Renders HTML elements with dynamic attributes and context support.
 * Resolves dynamic expressions in element attributes and provides element
 * attributes context to child blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\ElementBlock;

use Etch\Blocks\Types\ElementAttributes;
use Etch\Blocks\Global\StylesRegister;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Utilities\ShortcodeProcessor;

/**
 * ElementBlock class
 *
 * Handles rendering of etch/element blocks with customizable HTML tags and attributes.
 * Supports dynamic expression resolution in attributes (e.g., {this.title}, {props.value}).
 */
class ElementBlock {

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
			'etch/element',
			array(
				'api_version' => '3',
				'attributes' => array(
					'tag' => array(
						'type' => 'string',
						'default' => 'div',
					),
					'attributes' => array(
						'type' => 'object',
						'default' => array(),
					),
					'styles' => array(
						'type' => 'array',
						'default' => array(),
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
					// '__experimentalNoWrapper' => true,
					'innerBlocks' => true,
				),
				'render_callback' => array( $this, 'render_block' ),
				'skip_inner_blocks' => true,
			)
		);
	}

	/**
	 * Render the block
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content.
	 * @param \WP_Block|null       $block WP_Block instance (contains context).
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content, $block = null ) {
		$attrs = ElementAttributes::from_array( $attributes );
		$tag = $attrs->tag;

		ScriptRegister::register_script( $attrs );

		$resolved_attributes = $attrs->attributes;
		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );
		if ( ! empty( $sources ) ) {
			foreach ( $resolved_attributes as $name => $value ) {
				$resolved_attributes[ $name ] = DynamicContentProcessor::apply(
					$value,
					array(
						'sources' => $sources,
					)
				);
			}
		}

		// Register styles (original + dynamic) after dynamic expression processing
		StylesRegister::register_block_styles( $attrs->styles ?? array(), $attrs->attributes, $resolved_attributes );

		// Process shortcodes in attribute values after dynamic data resolution
		foreach ( $resolved_attributes as $name => $value ) {
			$string_value = EtchTypeAsserter::to_string( $value );
			$resolved_attributes[ $name ] = ShortcodeProcessor::process( $string_value, 'etch/element' );
		}

		// Ensure we remove the note data attribute
		if ( isset( $resolved_attributes['data-etch-note'] ) && is_string( $resolved_attributes['data-etch-note'] ) ) {
			unset( $resolved_attributes['data-etch-note'] );
		}

		$attribute_string = '';
		foreach ( $resolved_attributes as $name => $value ) {
			$attribute_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( EtchTypeAsserter::to_string( $value ) ) );
		}

		// Avoid using $content directly because WP tends to add unwanted whitespace/newlines and we only care about the inner blocks anyway
		$inner_content = $this->render_inner_blocks( $block );

		return sprintf(
			'<%1$s%2$s>%3$s</%1$s>',
			esc_html( $tag ),
			$attribute_string,
			$inner_content
		);
	}

	/**
	 * Render inner blocks of the given block
	 *
	 * @param \WP_Block|null $block WP_Block instance.
	 * @return string Rendered inner blocks HTML.
	 */
	private function render_inner_blocks( $block ) {
		if ( $block instanceof \WP_Block && isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] ) ) {
			$inner_blocks = $block->parsed_block['innerBlocks'];
		} else {
			$inner_blocks = array();
		}

		$rendered = '';
		foreach ( $inner_blocks as $child ) {
			$rendered .= render_block( $child );
		}
		return $rendered;
	}
}
