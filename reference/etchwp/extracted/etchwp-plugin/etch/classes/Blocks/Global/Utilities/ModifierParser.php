<?php
/**
 * ModifierParser
 *
 * Blocks-side modifier parsing utilities.
 *
 * Mirrors the TS `parseModifier` + `splitArgs` behavior:
 * - recognizes modifier segments like `method(...)`
 * - splits args on commas only at top-level depth and outside quoted strings
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

/**
 * ModifierParser class.
 */
class ModifierParser {
	/**
	 * Check if a string is a modifier call.
	 *
	 * @param string $part Segment to check (e.g. `format("y-m-d")`, `toUpperCase()`).
	 * @return bool
	 */
	public static function is_modifier( string $part ): bool {
		return preg_match( '/^\w+\(.*\)$/s', $part ) === 1;
	}

	/**
	 * Parse a modifier call into method + raw arg string.
	 *
	 * @param string $modifier Modifier string like `method(a, b)`.
	 * @return array{method: string, args: string}
	 */
	public static function parse( string $modifier ): array {
		$modifier = trim( $modifier );
		if ( ! self::is_modifier( $modifier ) ) {
			return array(
				'method' => '',
				'args'   => '',
			);
		}

		static $cache = array();
		if ( isset( $cache[ $modifier ] ) ) {
			return $cache[ $modifier ];
		}

		if ( str_ends_with( $modifier, ')' ) ) {
			$modifier = substr( $modifier, 0, -1 );
		}
		$parts = explode( '(', $modifier, 2 );
		$method = $parts[0] ?? '';
		$args = $parts[1] ?? '';

		$result = array(
			'method' => $method,
			'args'   => $args,
		);

		if ( count( $cache ) > 1000 ) {
			$cache = array();
		}
		$cache[ $modifier ] = $result;

		return $result;
	}

	/**
	 * Split a modifier argument list.
	 *
	 * Commas only split arguments at depth 0 and outside quoted strings.
	 *
	 * @param string $arg_string The string to split.
	 * @return array<int, string>
	 */
	public static function split_args( string $arg_string ): array {
		if ( '' === trim( $arg_string ) ) {
			return array();
		}

		static $cache = array();
		if ( isset( $cache[ $arg_string ] ) ) {
			return $cache[ $arg_string ];
		}

		$args = array();
		$current = '';
		$depth = 0;
		$in_quotes = false;
		$quote_char = '';
		$length = strlen( $arg_string );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $arg_string[ $i ];
			$prev_char = $i > 0 ? $arg_string[ $i - 1 ] : '';

			if ( ( '"' === $char || "'" === $char ) && '\\' !== $prev_char ) {
				if ( ! $in_quotes ) {
					$in_quotes = true;
					$quote_char = $char;
				} elseif ( $char === $quote_char ) {
					$in_quotes = false;
					$quote_char = '';
				}
			}

			if ( ! $in_quotes ) {
				if ( '(' === $char || '[' === $char || '{' === $char ) {
					$depth++;
				} elseif ( ')' === $char || ']' === $char || '}' === $char ) {
					$depth = max( 0, $depth - 1 );
				}

				if ( ',' === $char && 0 === $depth ) {
					$trimmed = trim( $current );
					if ( '' !== $trimmed ) {
						$args[] = $trimmed;
					}
					$current = '';
					continue;
				}
			}

			$current .= $char;
		}

		$trimmed = trim( $current );
		if ( '' !== $trimmed ) {
			$args[] = $trimmed;
		}

		if ( count( $cache ) > 1000 ) {
			$cache = array();
		}
		$cache[ $arg_string ] = $args;

		return $args;
	}
}
