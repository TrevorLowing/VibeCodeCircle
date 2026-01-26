<?php
/**
 * Text Block
 *
 * Renders text content with dynamic expression support.
 * Resolves dynamic expressions in text content (e.g., {this.title}, {props.value}).
 *
 * @package Etch
 */

namespace Etch\Blocks\TextBlock;

use Etch\Blocks\Types\TextAttributes;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\ShortcodeProcessor;

/**
 * TextBlock class
 *
 * Handles rendering of etch/text blocks with dynamic content resolution.
 * Supports all context types: global (this, site, user), component props, and element attributes.
 */
class TextBlock {

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
			'etch/text',
			array(
				'api_version' => '3',
				'attributes' => array(
					'content' => array(
						'type'     => 'string',
						'source'   => 'html',
						'selector' => 'span',
						'default'  => '',
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
				),
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Render callback for the block
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block content.
	 * @param \WP_Block|null       $block      WP_Block instance (contains context).
	 * @return string
	 */
	public function render_callback( array $attributes, string $content = '', $block = null ): string {
		$attrs = TextAttributes::from_array( $attributes );
		$text_content = $attrs->content;

		ScriptRegister::register_script( $attrs );

		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );
		if ( ! empty( $sources ) ) {
			$text_content = DynamicContentProcessor::replace_templates(
				$text_content,
				array(
					'sources' => $sources,
				)
			);
		}

		$text_content = $this->sanitize_content( $text_content );

		// Process shortcodes after dynamic data resolution
		$text_content = ShortcodeProcessor::process( $text_content, 'etch/text' );

		return $text_content;
	}

	/**
	 * Sanitize content by escaping special characters while preserving shortcodes.
	 *
	 * This method escapes special HTML characters in the content while ensuring that
	 * any shortcodes present remain intact and functional. It uses placeholders to
	 * temporarily replace shortcodes during the escaping process.
	 *
	 * @param string $content The text content that may contain shortcodes.
	 * @return string The sanitized content with special characters escaped and shortcodes preserved.
	 */
	public function sanitize_content( string $content ): string {
		// Fast-path: if there is no opening bracket, there can't be any shortcodes.
		// just escape the whole content.
		if ( strpos( $content, '[' ) === false || strpos( $content, ']' ) === false ) {
			return htmlspecialchars( $content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}

		// Use WordPress's built-in shortcode regex (returns the pattern without delimiters).
		$pattern = '/' . get_shortcode_regex() . '/';
		$placeholders = array();
		$counter = 0;

		// Replace shortcodes with placeholders
		$content_with_placeholders = preg_replace_callback(
			$pattern,
			static function ( array $matches ) use ( &$placeholders, &$counter ): string {
				$placeholder = "\x00SC{$counter}\x00";
				// * Right now we don't escape shortcode at all to preserve quotes in attributes and & in urls etc.
				// * If needed in the future we can just apply some escaping here.
				$placeholders[ $placeholder ] = $matches[0]; // store original shortcode

				$counter++;
				return $placeholder;
			},
			$content
		) ?? '';

		// Escape the entire content (now with placeholders)
		$escaped = htmlspecialchars( $content_with_placeholders, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

		return strtr( $escaped, $placeholders ); // Restore original shortcodes
	}
}
