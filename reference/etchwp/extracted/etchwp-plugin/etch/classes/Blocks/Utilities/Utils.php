<?php
/**
 * Global Utilities for Blocks
 *
 * Generic utility methods for block processing that don't fit into specific domain classes.
 *
 * @package Etch\Blocks\Utilities
 */

namespace Etch\Blocks\Utilities;

use Etch\Blocks\Global\Utilities\DynamicContentProcessor;

/**
 * Utils class
 */
class Utils {

	/**
	 * Parse keyword arguments from an array of arg strings.
	 * Skips empty string values so loop config defaults ($param ?? default) can work.
	 *
	 * @param array<string>                                 $arg_parts Array of argument strings.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for resolving dynamic values.
	 * @return array<string, mixed>
	 */
	public static function parse_keyword_args( array $arg_parts, array $sources ): array {
		$params = array();

		foreach ( $arg_parts as $arg ) {
			$arg = trim( $arg );
			if ( '' === $arg ) {
				continue;
			}

			// Check if it's a keyword argument (contains ':')
			$colon_pos = strpos( $arg, ':' );
			if ( false === $colon_pos ) {
				// Skip non-keyword arguments (positional args not supported)
				continue;
			}

			// Split on first ':' only
			$key = trim( substr( $arg, 0, $colon_pos ) );
			$value_str = trim( substr( $arg, $colon_pos + 1 ) );

			// Ensure key starts with '$'
			if ( '' !== $key && '$' !== $key[0] ) {
				$key = '$' . $key;
			}

			// Skip if key is empty or just '$'
			if ( '' === $key || '$' === $key ) {
				continue;
			}

			// Process the value to get proper type
			$value = DynamicContentProcessor::process_expression(
				$value_str,
				array(
					'sources' => $sources,
				)
			);

			if ( null === $value ) {
				$value = $value_str;
			}

			if ( '' === $value ) {
				continue;
			}

			$params[ $key ] = $value;
		}

		return $params;
	}
}
