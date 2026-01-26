<?php
/**
 * Raw Html Block
 *
 * Renders raw html content with dynamic expression support.
 * Resolves dynamic expressions content (e.g., {this.title}, {props.myHtml}).
 *
 * @package Etch
 */

namespace Etch\Blocks\RawHtmlBlock;

use Etch\Blocks\Types\RawHtmlAttributes;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\ShortcodeProcessor;

/**
 * RawHtmlBlock class
 *
 * Handles rendering of etch/Raw Html Blocks with dynamic content resolution.
 * Supports all context types: global (this, site, user), component props, and element attributes.
 */
class RawHtmlBlock {

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
			'etch/raw-html',
			array(
				'api_version' => '3',
				'attributes' => array(
					'content' => array(
						'type'     => 'string',
						'default'  => '',
					),
					'unsafe' => array(
						'type'     => 'string',
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
		$attrs = RawHtmlAttributes::from_array( $attributes );
		$html_content = $attrs->content;

		ScriptRegister::register_script( $attrs );

		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );

		$unsafe = false;

		if ( ! empty( $sources ) ) {
			$html_content = DynamicContentProcessor::replace_templates(
				$html_content,
				array(
					'sources' => $sources,
				)
			);
			$resolvedUnsafe = DynamicContentProcessor::replace_templates(
				$attrs->unsafe,
				array(
					'sources' => $sources,
				)
			);
			if ( ! empty( $resolvedUnsafe ) ) {
				$unsafe = in_array( $resolvedUnsafe, array( 'true', '1', 'yes', 'on' ), true );
			}
		}

		// Process shortcodes after dynamic data resolution
		$html_content = ShortcodeProcessor::process( $html_content, 'etch/raw-html' );

		// Check if content contains Gutenberg blocks
		if ( has_blocks( $html_content ) ) {
			return $this->render_blocks( $html_content );
		}

		// On unsafe, return raw content
		if ( $unsafe ) {
			return $html_content;
		}

		return $this->sanitize_html( $html_content );
	}

	/**
	 * Render Gutenberg blocks from content
	 *
	 * @param string $content Content containing Gutenberg block markup.
	 * @return string Rendered block output.
	 */
	private function render_blocks( string $content ): string {
		$blocks = parse_blocks( $content );
		$rendered = '';
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				// Render registered Gutenberg blocks
				$rendered .= render_block( $block );
			} elseif ( ! empty( $block['innerHTML'] ) ) {
				// Preserve and sanitize plain HTML content between blocks
				$rendered .= $this->sanitize_html( $block['innerHTML'] );
			}
		}
		return $rendered;
	}

	/**
	 * Sanitize HTML content to prevent XSS
	 *
	 * @param string $html_content HTML content to sanitize.
	 * @return string Sanitized HTML content.
	 */
	public function sanitize_html( string $html_content ): string {
		$allowed_html = wp_kses_allowed_html( 'post' );
		$allowed_html['*']['data-*'] = true;
		$allowed_html['*']['style']  = true;
		return wp_kses( $html_content, $allowed_html );
	}
}
