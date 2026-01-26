<?php
/**
 * HtmlBlock Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Helpers\SvgLoader;
use Etch\Preprocessor\Data\EtchData;
use Etch\Preprocessor\Utilities\EtchParser;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;
use Etch\Preprocessor\Registry\StylesRegister;

/**
 * HtmlBlock class for processing HTML blocks with EtchData.
 */
class HtmlBlock extends BaseBlock {

	/**
	 * The HTML tag for this block.
	 *
	 * @var string
	 */
	private $tag = '';

	/**
	 * The attributes for this block.
	 *
	 * @var array<string, string>
	 */
	private $attributes = array();


	/**
	 * The resolved attributes after processing dynamic content.
	 *
	 * @var array<string, string>
	 */
	private $resolved_attributes = array();

	/**
	 * The resolved attribute string for inclusion in HTML output.
	 *
	 * @var string
	 */
	private $resolved_attribute_string = '';

	/**
	 * Constructor for the HtmlBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData                  $data Etch data instance.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, EtchData $data, $context = null, $parent = null ) {
		parent::__construct( $block, $data, $context, $parent );

		// Fix for SVG tags: remove xmlns attribute if tag is svg
		if ( null !== $this->etch_data && 'svg' === $this->etch_data->tag && isset( $this->etch_data->attributes['xmlns'] ) ) {
			unset( $this->etch_data->attributes['xmlns'] );
		}

		$this->tag = $this->etch_data->tag ?? '';
		$this->attributes = $this->etch_data->attributes ?? array();

		$resolved_attribute_data = $this->resolve_and_build_attributes( $this->attributes );
		$this->resolved_attributes = $resolved_attribute_data['resolved'];
		$this->resolved_attribute_string = $resolved_attribute_data['string'];
	}

	/**
	 * Resolve attributes with context and build the attribute string.
	 *
	 * @param array<string, string> $attributes The attributes to resolve.
	 * @return array{resolved: array<string, string>, string: string} The resolved attributes and attribute string.
	 */
	private function resolve_and_build_attributes( array $attributes ): array {
		$resolved = array();
		$attribute_string = '';
		$context = $this->get_context();
		$has_dynamic = false;

		foreach ( $attributes as $key => $value ) {
			// Resolve value with context
			[$string_value, $has_templates] = EtchParser::replace_string( (string) $value, $context, true );
			$resolved[ $key ] = $string_value;

			if ( $has_templates ) {
				$has_dynamic = true;
			}

			// Build the attribute string
			// ! It's annoying that php treats this as empty even if it's "0"
			if ( empty( $string_value ) && '0' !== $string_value ) {
				// Boolean-style attribute (e.g., "disabled", "checked")
				$attribute_string .= ' ' . esc_attr( $key );
			} else {
				$attribute_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $string_value ) . '"';
			}
		}

		if ( $has_dynamic ) {
			// Register dynamic styles based on resolved attributes
			$this->register_dynamic_styles( $attribute_string );
		}

		return array(
			'resolved' => $resolved,
			'string'   => $attribute_string,
		);
	}

	/**
	 * Process the HTML block and return the transformed block data.
	 *
	 * @return array<string, mixed>|array<int, array<string, mixed>> Transformed block data, or array of blocks for etch-content.
	 */
	public function process(): array {
		// On no etch data, return the raw block (render as is unchanged)
		if ( null === $this->etch_data ) {
			return $this->get_raw_block();
		}

		// If it's a dynamic element set the tag accordingly
		if ( $this->is_dynamic_element() ) {
			$this->tag = $this->sanitize_tag( $this->resolved_attributes['tag'] );

			// Remove the tag attribute as it's not a real HTML attribute
			unset( $this->resolved_attributes['tag'] );
			// remove the tag from the attributes string as well
			$this->resolved_attribute_string = EtchTypeAsserter::to_string(
				preg_replace(
					'/\s*tag="[^"]*"/',
					'',
					$this->resolved_attribute_string
				)
			);
		}

		$this->process_inner_content();
		$this->process_inner_blocks();

		$this->set_block_name( 'etch/block' );
		return $this->get_raw_block();
	}

	/**
	 * Sanitize the HTML tag to ensure it's a valid tag name.
	 *
	 * @param string|null $tag The tag to sanitize.
	 * @return string The sanitized tag, or 'div' if invalid.
	 */
	private function sanitize_tag( ?string $tag ): string {
		if ( null === $tag ) {
			return 'div';
		}

		// always lowercase and trim
		$sanitizedTag = strtolower( trim( $tag ) );

		// replace spaces with hyphens
		$sanitizedTag = preg_replace( '/\s+/', '-', $sanitizedTag ) ?? '';
		// remove invalid characters
		$sanitizedTag = preg_replace( '/[^a-z0-9-_]/', '', $sanitizedTag ) ?? '';
		// collapse multiple hyphens
		$sanitizedTag = preg_replace( '/-+/', '-', $sanitizedTag ) ?? '';
		// trim leading/trailing hyphens or underscores
		$sanitizedTag = preg_replace( '/^[-_]+|[-_]+$/', '', $sanitizedTag ) ?? '';

		$sanitizedTag = EtchTypeAsserter::to_string( $sanitizedTag, 'div' );

		if ( '' === $sanitizedTag || is_numeric( $sanitizedTag[0] ) ) {
			$sanitizedTag = 'div';
		}

		return $sanitizedTag;
	}

	/**
	 * Get the opening HTML tag with resolved attributes.
	 *
	 * @return string The opening HTML tag.
	 */
	private function get_opening_tag(): string {
		// If removeWrapper is true, return empty string
		if ( $this->etch_data && $this->etch_data->removeWrapper ) {
			return '';
		}

		return '<' . $this->tag . $this->resolved_attribute_string . '>';
	}

	/**
	 * Get the closing HTML tag.
	 *
	 * @return string The closing HTML tag.
	 */
	private function get_closing_tag(): string {
		// If removeWrapper is true, return empty string
		if ( $this->etch_data && $this->etch_data->removeWrapper ) {
			return '';
		}

		return '</' . $this->tag . '>';
	}

	/**
	 * Strips the outer wrapper tags from the given HTML string.
	 *
	 * @param string $html_string The HTML string to process.
	 * @return string The stripped HTML string.
	 */
	private function strip_outer_wrapper( string $html_string ): string {
		if ( false === strpos( $html_string, '>' ) ) {
			return $html_string;
		}

		$start = strpos( $html_string, '>' ) + 1;
		$end = strrpos( $html_string, '<' );

		$inner_html = substr( $html_string, $start, $end - $start );
		return $inner_html;
	}

	/**
	 * Process the inner content of the block, resolving dynamic expressions and handling nested data.
	 *
	 * @return void
	 */
	private function process_inner_content(): void {
		if ( $this->is_etch_svg() ) {
			$this->load_and_set_svg_content();
			return;
		}

		$skip_replacement = false;
		// Skip replacement for style tags to avoid breaking CSS
		if ( isset( $this->parent ) ) {
			$parent_etch_data = $this->parent->etch_data;
			$skip_replacement = null !== $parent_etch_data && 'style' === $parent_etch_data->tag;
		}

		// resolve all the dynamic data in the inner content first
		if ( ! $skip_replacement ) {
			foreach ( $this->innerContent as $index => $content ) {
				if ( empty( $content ) ) {
					continue;
				}

				$this->innerContent[ $index ] = EtchParser::replace_string( $content, $this->get_context() );
			}
		}

		$inner_content_count = count( $this->innerContent );
		if ( $inner_content_count > 1 ) {
			// Ensure we have a proper opening and closing tag
			$this->innerContent[0] = $this->get_opening_tag();
			$this->innerContent[ count( $this->innerContent ) - 1 ] = $this->get_closing_tag();
		} else if ( 1 === $inner_content_count ) {
			// On a single line we need to manually replace the opening and closing tag with out resolved ones
			$this->innerContent[0] =
				$this->get_opening_tag() .
				$this->strip_outer_wrapper( $this->innerContent[0] ?? '' ) .
				$this->get_closing_tag();
		}

		if ( $this->etch_data && $this->etch_data->has_nested_data() ) {
			$inner_content = $this->process_nested_data( implode( '', $this->innerContent ) );
			$this->innerContent = array( $inner_content );
		}
	}

	/**
	 * Check if the current block is an etch SVG block.
	 *
	 * @return bool True if SVG block, false otherwise.
	 */
	private function is_etch_svg(): bool {
		return null !== $this->etch_data && 'svg' === $this->etch_data->specialized;
	}

	/**
	 * Check if the current block is a dynamic element.
	 *
	 * @return bool True if dynamic element, false otherwise.
	 */
	private function is_dynamic_element(): bool {
		return null !== $this->etch_data && 'dynamic-element' === $this->etch_data->specialized;
	}


	/**
	 * Load SVG content from URL and set as inner content.
	 *
	 * @return void
	 */
	private function load_and_set_svg_content(): void {
		$src = $this->resolved_attributes['src'] ?? '';

		// Load SVG content from the URL
		$svg_content = SvgLoader::fetch_svg_cached( $src );

		if ( empty( $svg_content ) ) {
			return;
		}

		if ( isset( $this->resolved_attributes['stripColors'] ) ) {
			$strip_colors = in_array( $this->resolved_attributes['stripColors'], array( 'true', '1', 'yes', 'on' ), true );
		} else {
			$strip_colors = false;
		}

		$options = array(
			'strip_colors' => $strip_colors,
			'attributes'   => $this->resolved_attribute_string,
		);

		$svg_content = SvgLoader::prepare_svg_for_output(
			$svg_content,
			$options
		);

		$this->set_inner_content( array( $svg_content ) );
		$this->set_inner_html( $svg_content );
	}

	/**
	 * Process nested data within the inner HTML content.
	 *
	 * @param string $inner_html The inner HTML content to process.
	 * @return string The processed inner HTML content.
	 */
	private function process_nested_data( string $inner_html ): string {
		if ( ! $this->etch_data || ! $this->etch_data->has_nested_data() || empty( $inner_html ) ) {
			return $inner_html;
		}

		$final_inner_html = $inner_html;
		foreach ( $this->etch_data->nestedData as $id => $nestedData ) {
			if ( ! $nestedData instanceof EtchData ) {
				continue;
			}

			// Remove the 'xmlns' attribute for SVG tags to avoid issues when rendering.
			if ( 'svg' === $nestedData->tag ) {
				$nestedData->remove_attribute( 'xmlns' );
				$final_inner_html = EtchTypeAsserter::to_string(
					preg_replace(
						'/xmlns="[^"]*"/',
						'',
						EtchTypeAsserter::to_string( $final_inner_html )
					),
					''
				);
			}

			$attributes_data = $this->resolve_and_build_attributes( $nestedData->attributes );

			if ( $this->is_tag_nested_data( $id, $final_inner_html ) ) {
				$final_inner_html = $this->replace_tag_nested_data( $final_inner_html, $id, $attributes_data['string'] );
			} else {
				$final_inner_html = $this->replace_ref_nested_data( $final_inner_html, $id, $attributes_data['string'] );
			}
		}

		return EtchTypeAsserter::to_string( $final_inner_html );
	}

	/**
	 * Check the given nested data id is an data-etch-ref or a tag attribute.
	 *
	 * @param string $id The ID to check.
	 * @param string $content_string The content string.
	 * @return bool True if tag, false if common data-etch-ref.
	 */
	private function is_tag_nested_data( string $id, string $content_string ): bool {
		return ! strpos( $content_string, 'data-etch-ref="' . $id . '"' );
	}

	/**
	 * Replace tag nested data in content string.
	 *
	 * @param string $content_string Content string.
	 * @param string $id Nested data ID.
	 * @param string $mapped_attributes Mapped attributes.
	 * @return string Updated content string.
	 */
	private function replace_tag_nested_data( string $content_string, string $id, string $mapped_attributes ): string {
		return str_replace( '<' . $id, '<' . $id . $mapped_attributes, $content_string );
	}

	/**
	 * Replace ref nested data in content string.
	 *
	 * @param string $content_string Content string.
	 * @param string $id Nested data ID.
	 * @param string $mapped_attributes Mapped attributes.
	 * @return string Updated content string.
	 */
	private function replace_ref_nested_data( string $content_string, string $id, string $mapped_attributes ): string {
		// Use regex to replace data-etch-ref and normalize spacing
		$pattern = '/\s*data-etch-ref="' . preg_quote( esc_attr( (string) $id ), '/' ) . '"\s*/';
		return EtchTypeAsserter::to_string(
			preg_replace(
				$pattern,
				$mapped_attributes,
				EtchTypeAsserter::to_string( $content_string )
			)
		);
	}

	/**
	 * Process inner blocks recursively and set them as raw blocks.
	 *
	 * @return void
	 */
	private function process_inner_blocks(): void {
		if ( empty( $this->innerBlocks ) ) {
			return;
		}

		$processed_inner_blocks_raw_blocks = $this->process_inner_blocks_to_raw_blocks( $this->innerBlocks );

		// Set the processed inner blocks directly as arrays in the current block
		$this->set_inner_blocks_from_raw_blocks( $processed_inner_blocks_raw_blocks );

		// We need this for loops/components to work properly
		$this->update_inner_content_slots_count();
	}

	/**
	 * Register dynamic styles based on parsed attributes.
	 * This method finds styles that match the parsed attribute values and registers them.
	 *
	 * @param string $parsed_attributes The parsed HTML attributes string.
	 * @return void
	 */
	private function register_dynamic_styles( string $parsed_attributes ): void {
		if ( empty( $parsed_attributes ) ) {
			return;
		}

		// Find styles that match the parsed attributes
		$matching_style_ids = StylesRegister::find_matching_styles( $parsed_attributes );

		// Register the matching styles
		if ( ! empty( $matching_style_ids ) ) {
			StylesRegister::register_styles( $matching_style_ids );
		}
	}
}
