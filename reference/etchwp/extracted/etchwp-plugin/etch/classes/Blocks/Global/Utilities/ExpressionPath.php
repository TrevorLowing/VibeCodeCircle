<?php
/**
 * ExpressionPath
 *
 * Utility for splitting dynamic expression paths into parts while respecting
 * parentheses, brackets, and quotes.
 *
 * This mirrors the behavior currently implemented in the legacy
 * legacy path splitting behavior for dynamic expressions.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

/**
 * ExpressionPath class.
 */
class ExpressionPath {
	/**
	 * Split a path string into parts, respecting parentheses and quotes.
	 *
	 * Examples:
	 * - `item.title.toUpperCase()` -> [`item`, `title`, `toUpperCase()`]
	 * - `item["kebab-key"].value` -> [`item`, `kebab-key`, `value`]
	 *
	 * @param string $path The path to split.
	 * @return array<int, string> Array of parts.
	 */
	public static function split( string $path ): array {
		if ( '' === $path ) {
			return array();
		}

		static $cache = array();
		if ( isset( $cache[ $path ] ) ) {
			return $cache[ $path ];
		}

		$parts = array();
		$current = '';
		$paren_depth = 0;
		$bracket_depth = 0;
		$in_single_quote = false;
		$in_double_quote = false;
		$length = strlen( $path );

		$push_part = static function ( string $part ) use ( &$parts ): void {
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		};

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $path[ $i ];
			$is_quoted = $in_single_quote || $in_double_quote;

			// Handle escape sequences.
			if ( '\\' === $char && $i + 1 < $length ) {
				$current .= $char . $path[ ++$i ];
				continue;
			}

			if ( '(' === $char && ! $is_quoted && 0 === $bracket_depth ) {
				$paren_depth++;
				$current .= $char;
				continue;
			}

			if ( ')' === $char && ! $is_quoted && 0 === $bracket_depth ) {
				$paren_depth = max( 0, $paren_depth - 1 );
				$current .= $char;
				continue;
			}

			// Track quotes inside bracket expressions.
			if ( 0 === $paren_depth && $bracket_depth > 0 ) {
				if ( "'" === $char && ! $in_double_quote ) {
					$in_single_quote = ! $in_single_quote;
					continue;
				}
				if ( '"' === $char && ! $in_single_quote ) {
					$in_double_quote = ! $in_double_quote;
					continue;
				}
			}

			if ( '[' === $char && ! $is_quoted && 0 === $paren_depth ) {
				if ( 0 === $bracket_depth && '' !== $current ) {
					$push_part( $current );
					$current = '';
				}
				$bracket_depth++;
				continue;
			}

			if ( ']' === $char && ! $is_quoted && 0 === $paren_depth ) {
				$bracket_depth = max( 0, $bracket_depth - 1 );

				$is_bracket_func_pattern = (
					0 === $bracket_depth &&
					$i + 1 < $length &&
					'(' === $path[ $i + 1 ]
				);

				if ( 0 === $bracket_depth && ! $is_bracket_func_pattern && '' !== $current ) {
					$parts[] = $current;
					$current = '';
				}
				continue;
			}

			if ( '.' === $char && 0 === $bracket_depth && 0 === $paren_depth && ! $is_quoted ) {
				$push_part( $current );
				$current = '';
				continue;
			}

			$current .= $char;
		}

		if ( '' !== $current ) {
			$push_part( $current );
		}

		if ( count( $cache ) > 1000 ) {
			$cache = array();
		}
		$cache[ $path ] = $parts;

		return $parts;
	}
}
