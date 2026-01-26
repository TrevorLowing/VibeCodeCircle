<?php
/**
 * EtchBlock Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\BlocksRegistry;

use Etch\Helpers\Logger;

/**
 * EtchBlock class.
 *
 * Handles the modification of core/etch-block blocks in Etch.
 */
class EtchBlock extends EtchBlockCache {

	/**
	 * Constructor for the EtchBlock class.
	 *
	 * Initializes the class by adding action hooks.
	 */
	public function __construct() {
		add_filter( 'render_block_etch/block', array( $this, 'frontend_options' ), 99999999, 2 );
	}

	/**
	 * Adds custom classes to the button block that is an etch block
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block The block details.
	 * @return string The updated block content.
	 */
	public function frontend_options( string $block_content, array $block ): string {
		if ( 'etch/block' === ( $block['blockName'] ?? '' ) ) {

			// Check if we have a cached version of this block
			$cached_content = $this->get_cached_block( $block );
			if ( null !== $cached_content ) {
				return $cached_content;
			}

			// Logger::log( 'ETCH BLOCK' );

			// Store the processed content in cache before returning
			$this->cache_block( $block, $block_content );

			return $block_content;
		}

		return $block_content;
	}
}
