<?php
/**
 * SVG Block
 *
 * Renders SVG elements with dynamic attributes and context support.
 * Specialized version of ElementBlock for SVG rendering with tag fixed to 'svg'.
 * Resolves dynamic expressions in SVG attributes.
 *
 * @package Etch
 */

namespace Etch\Blocks\SvgBlock;

use Etch\Blocks\Types\ElementAttributes;
use Etch\Blocks\Global\StylesRegister;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Utilities\ShortcodeProcessor;
use Etch\Helpers\SvgLoader;

/**
 * SvgBlock class
 *
 * Handles rendering of etch/svg blocks with SVG-specific functionality.
 * Supports dynamic expression resolution in SVG attributes (e.g., {this.title}, {props.value}).
 */
class SvgBlock {

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
			'etch/svg',
			array(
				'api_version' => '3',
				'attributes' => array(
					'tag' => array(
						'type' => 'string',
						'default' => 'svg', // Tag is always 'svg' for SVG blocks
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
	 * @param string               $content Block content (not used for SVG blocks).
	 * @param \WP_Block|null       $block WP_Block instance (contains context).
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content, $block = null ) {
		$attrs = ElementAttributes::from_array( $attributes );

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
			$resolved_attributes[ $name ] = ShortcodeProcessor::process( $string_value, 'etch/svg' );
		}

		// Extract src from resolved attributes
		$src = $resolved_attributes['src'] ?? '';
		// Ensure src is a string
		$src = is_string( $src ) ? $src : '';

		// Fetch SVG content from the URL
		$svg_content = SvgLoader::fetch_svg_cached( $src );

		if ( empty( $svg_content ) ) {
			return '';
		}

		// Check for stripColors option
		$strip_colors = false;
		if ( isset( $resolved_attributes['stripColors'] ) ) {
			$strip_colors = in_array( $resolved_attributes['stripColors'], array( 'true', '1', 'yes', 'on' ), true );
		}

		// Remove src and stripColors from attributes as they're not svg HTML attributes but more like Etch props.
		$svg_attributes = $resolved_attributes;
		unset( $svg_attributes['src'] );
		unset( $svg_attributes['stripColors'] );

		// Ensure we remove the note data attribute
		if ( isset( $svg_attributes['data-etch-note'] ) && is_string( $svg_attributes['data-etch-note'] ) ) {
			unset( $svg_attributes['data-etch-note'] );
		}

		// Build attribute string for merging with fetched SVG
		$attribute_string = '';
		foreach ( $svg_attributes as $name => $value ) {
			$attribute_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( EtchTypeAsserter::to_string( $value ) ) );
		}

		// Prepare SVG with merged attributes and color stripping
		$options = array(
			'strip_colors' => $strip_colors,
			'attributes'   => $attribute_string,
		);

		$prepared_svg = SvgLoader::prepare_svg_for_output( $svg_content, $options );

		return $prepared_svg;
	}
}
