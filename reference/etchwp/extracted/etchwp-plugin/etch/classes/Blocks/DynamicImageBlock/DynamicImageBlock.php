<?php
/**
 * Dynamic Image Block
 *
 * Renders image elements with dynamic attributes and context support.
 * Specialized version of ElementBlock for image rendering with tag fixed to 'img'.
 * Resolves dynamic expressions in image attributes.
 *
 * @package Etch
 */

namespace Etch\Blocks\DynamicImageBlock;

use Etch\Blocks\Types\ElementAttributes;
use Etch\Blocks\Global\StylesRegister;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Utilities\ShortcodeProcessor;
use Etch\Helpers\SvgLoader;

/**
 * DynamicImageBlock class
 *
 * Handles rendering of etch/dynamic-image blocks with image-specific functionality.
 * Supports dynamic expression resolution in image attributes (e.g., {this.title}, {props.value}).
 */
class DynamicImageBlock {

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
			'etch/dynamic-image',
			array(
				'api_version' => '3',
				'attributes' => array(
					'tag' => array(
						'type' => 'string',
						'default' => 'img', // Tag is always 'img' for DynamicImage blocks
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

		// Register styles (original + dynamic) after EtchParser processing
		StylesRegister::register_block_styles( $attrs->styles ?? array(), $attrs->attributes, $resolved_attributes );

		// Process shortcodes in attribute values after dynamic data resolution
		foreach ( $resolved_attributes as $name => $value ) {
			$string_value = EtchTypeAsserter::to_string( $value );
			$resolved_attributes[ $name ] = ShortcodeProcessor::process( $string_value, 'etch/dynamic-image' );
		}

		// Extract mediaId from resolved attributes
		$media_id = $resolved_attributes['mediaId'] ?? '';
		// Ensure mediaId is a string
		$media_id = is_string( $media_id ) ? $media_id : '';

		// Get image from media library
		$attachment = wp_get_attachment_metadata( intval( $media_id ) );

		if ( empty( $attachment ) ) {
			return '';
		}

		// Check for useSrcSet option
		$use_source_set = true;
		if ( ! empty( $resolved_attributes['useSrcSet'] ) ) {
			$use_source_set = in_array( $resolved_attributes['useSrcSet'], array( 'true', '1', 'yes', 'on' ), true );
		}

		// Check for maximumSize option and use 'full' as default
		$maximum_size = 'full';
		if ( isset( $resolved_attributes['maximumSize'] ) && is_string( $resolved_attributes['maximumSize'] ) ) {
			$maximum_size = $resolved_attributes['maximumSize'];
		}

		$maximum_image_src = '';
		if ( false !== wp_get_attachment_image_src( intval( $media_id ), $maximum_size ) ) {
			$maximum_image_src = wp_get_attachment_image_src( intval( $media_id ), $maximum_size )[0];
		}

		// Remove mediaId and useSrcSet and maximumSize from attributes as they're not svg HTML attributes but more like Etch props.
		$image_attributes = $resolved_attributes;
		unset( $image_attributes['mediaId'] );
		unset( $image_attributes['useSrcSet'] );
		unset( $image_attributes['maximumSize'] );

		// Build attribute string for merging with fetched image
		$attribute_string = '';
		foreach ( $image_attributes as $name => $value ) {
			// Skip alt attribute if it is empty to avoid possible duplication with fetched alt text
			if ( 'alt' === $name && empty( $value ) ) {
				continue;
			}
			$attribute_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( EtchTypeAsserter::to_string( $value ) ) );
		}

		$special_attribute_string = '';
		if ( $use_source_set ) {
			$srcset = '';
			$image_sizes = $attachment['sizes'];
			$full_image_src = '';
			if ( false !== wp_get_attachment_image_src( intval( $media_id ), 'full' ) ) {
				$full_image_src = wp_get_attachment_image_src( intval( $media_id ), 'full' )[0];
			}

			// build srcset from available sizes - "full" is not in there like in the API response so we need to handle it separately
			foreach ( $image_sizes as $size_name => $size_data ) {

				// only add images smaller than or equal to maximumSize
				if ( isset( $attachment['sizes'][ $maximum_size ] ) ) {
					$max_width = $attachment['sizes'][ $maximum_size ]['width'];
					if ( $size_data['width'] > $max_width ) {
						continue;
					}
				}

				if ( false === wp_get_attachment_image_src( intval( $media_id ), $size_name ) ) {
					continue;
				}

				$srcset .= wp_get_attachment_image_src( intval( $media_id ), $size_name )[0] . ' ' . $size_data['width'] . 'w, ';
			}
			// also add the full image  to the srcset if it's smaller than or equal to maximumSize including the sizes attribute
			if ( isset( $attachment['sizes'][ $maximum_size ] ) ) {
				$max_width = $attachment['sizes'][ $maximum_size ]['width'];
				if ( $attachment['width'] <= $max_width ) {

					$srcset .= $full_image_src . ' ' . $attachment['width'] . 'w, ';

				}

				$sizes = sprintf( '(max-width: %dpx) 100vw, %dpx', esc_attr( (string) $max_width ), esc_attr( (string) $max_width ) );
				$special_attribute_string .= sprintf( ' sizes="%s"', esc_attr( $sizes ) );
			} else {
				$srcset .= $full_image_src . ' ' . $attachment['width'] . 'w, ';
				$sizes = sprintf( '(max-width: %dpx) 100vw, %dpx', esc_attr( (string) $attachment['width'] ), esc_attr( (string) $attachment['width'] ) );
				$special_attribute_string .= sprintf( ' sizes="%s"', esc_attr( $sizes ) );
			}
			$srcset = rtrim( $srcset, ', ' );

			$special_attribute_string .= sprintf( ' srcset="%s"', esc_attr( $srcset ) );

		}

		// Always set src to the selected maximum_size
		$src = $maximum_image_src;

		$special_attribute_string .= sprintf( ' src="%s"', esc_attr( $src ) );

		// Add alt attribute if not already set
		if ( ! array_key_exists( 'alt', $image_attributes ) || empty( $image_attributes['alt'] ) ) {

			$alt_text = get_post_meta( intval( $media_id ), '_wp_attachment_image_alt', true );

			// Only add alt attribute if alt text is available
			if ( ! empty( $alt_text ) && is_string( $alt_text ) ) {
				$special_attribute_string .= sprintf( ' alt="%s"', esc_attr( $alt_text ) );
			}
		}

		// Add width and height attributes if not already set from the maximum size
		if ( ! array_key_exists( 'width', $image_attributes ) ) {
			$width = 0;
			if ( false !== wp_get_attachment_image_src( intval( $media_id ), $maximum_size ) ) {
				$width = wp_get_attachment_image_src( intval( $media_id ), $maximum_size )[1];
			}
			$special_attribute_string .= sprintf( ' width="%d"', esc_attr( (string) $width ) );
		}
		if ( ! array_key_exists( 'height', $image_attributes ) ) {
			$height = 0;
			if ( false !== wp_get_attachment_image_src( intval( $media_id ), $maximum_size ) ) {
				$height = wp_get_attachment_image_src( intval( $media_id ), $maximum_size )[2];
			}
			$special_attribute_string .= sprintf( ' height="%d"', esc_attr( (string) $height ) );
		}

		$img_html = '<img ' . $special_attribute_string . $attribute_string . ' />';

		return $img_html;
	}
}
