<?php
/**
 * ShortcodeProcessor utility class for Blocks system.
 *
 * Processes WordPress shortcodes in Etch block output.
 * Ensures shortcodes are resolved consistently in both regular post content
 * and FSE block theme templates.
 *
 * @package Etch\Blocks\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities;

/**
 * Utility class for processing shortcodes in Etch block output.
 *
 * WordPress automatically processes shortcodes in post content via the_content filter,
 * but does not automatically process shortcodes in block theme templates or block attributes.
 * This class provides a consistent way to process shortcodes after dynamic data resolution.
 */
class ShortcodeProcessor {

	/**
	 * Process shortcodes in the given HTML content.
	 *
	 * Applies WordPress do_shortcode() to the content, with optional shortcode_unautop()
	 * to handle auto-paragraph conversion. The processing can be filtered or disabled
	 * via the 'etch_process_shortcodes' filter.
	 *
	 * @param string $html The HTML content that may contain shortcodes.
	 * @param string $block_name Optional. The name of the block being processed (e.g., 'etch/element').
	 * @return string The HTML content with shortcodes processed.
	 */
	public static function process( string $html, string $block_name = '' ): string {
		// Allow filtering or disabling shortcode processing
		$processed = apply_filters( 'etch_process_shortcodes', $html, $block_name );

		// If the filter returned the same value, process shortcodes
		if ( $processed === $html ) {
			// Process shortcodes
			$processed = do_shortcode( $html );

			// Handle auto-paragraph conversion that might interfere with shortcodes
			// shortcode_unautop removes <p> tags around shortcodes
			$processed = shortcode_unautop( $processed );
		}

		return $processed;
	}
}
