<?php
/**
 * Content Wrapper Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\BlocksRegistry;

/**
 * ContentWrapper class.
 */
class ContentWrapper {

	/**
	 * Constructor for the ContentWrapper class.
	 *
	 * Initializes the class by adding action hooks.
	 */
	public function __construct() {
		add_filter( 'render_block_core/post-content', array( $this, 'remove_content_wrapper' ), 10, 2 );
	}

	/**
	 * Removes the outermost div wrapper and renders only its children.
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block The block details.
	 * @return string The updated block content.
	 */
	public function remove_content_wrapper( string $block_content, array $block ): string {

		// The only assumption is that the content in a single wrapper tag.
		if ( false === strpos( $block_content, '>' ) ) {
			return $block_content;
		}

		$start = strpos( $block_content, '>' ) + 1;
		$end = strrpos( $block_content, '<' );

		$block_content = substr( $block_content, $start, $end - $start );

		return $block_content;
	}
}
