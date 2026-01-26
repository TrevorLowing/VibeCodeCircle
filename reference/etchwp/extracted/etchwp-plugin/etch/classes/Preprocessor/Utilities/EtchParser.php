<?php
/**
 * EtchParser class for Etch plugin
 *
 * This file contains the EtchParser class which handles
 * parsing and replacing dynamic placeholders in block content
 * with support for multi-key context arrays.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities;

/**
 * EtchParser class for handling dynamic placeholder replacement.
 *
 * Supports multi-key context arrays for nested loops and complex data structures.
 * All methods are static for easy access across the preprocessor system.
 */
class EtchParser {

	/**
	 * Check if a value is a standalone dynamic expression.
	 *
	 * @param string $value The value to check.
	 * @return bool True if the value is a dynamic expression, false otherwise.
	 */
	public static function is_dynamic_expression( string $value ): bool {
		if ( empty( $value ) || strlen( $value ) < 2 || '{' !== $value[0] || '}' !== $value[-1] ) {
			return false;
		}

		$brace_depth = 0;
		$in_string = false;
		$string_char = '';

		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			// Handle string boundaries to avoid misinterpreting braces within strings
			if ( $brace_depth > 0 && ( '"' === $char || "'" === $char || '`' === $char ) ) {
				$is_escaped = $i > 0 && '\\' === $value[ $i - 1 ];
				if ( ! $is_escaped ) {
					if ( ! $in_string ) {
						// Starting a string
						$in_string = true;
						$string_char = $char;
					} else if ( $char === $string_char ) {
						// Ending the current string
						$in_string = false;
					}
				}
			}

			if ( ! $in_string ) {
				if ( '{' === $char ) {
					$brace_depth++;
					continue; // Continue to avoid adding the opening brace to result
				} else if ( '}' === $char ) {
					$brace_depth--;

					if ( 0 === $brace_depth && $i < $length - 1 ) {
						return false; // Found closing brace before end of string
					}

					if ( $brace_depth < 0 ) {
						return false; // More closing braces than opening
					}
				}
			}
		}

		return 0 === $brace_depth;
	}

	/**
	 * Iterate over the given value and type safely replace the dynamic placeholders.
	 *
	 * @param mixed               $value The value to process (string, array, etc.).
	 * @param array<string,mixed> $context The context array with multiple keys.
	 * @return mixed The value with the data applied
	 */
	public static function type_safe_replacement( $value, array $context ) {
		if ( ! is_array( $context ) || empty( $context ) || empty( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			if ( self::is_dynamic_expression( $value ) ) {
				return self::process_expression( substr( $value, 1, -1 ), $context );
			}

			return self::replace_string( $value, $context );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = EtchTypeAsserter::to_array( $value );
			foreach ( $value as $key => $item ) {
				// Recursively type safe_replacement the context to each item in the array/object
				$value[ $key ] = self::type_safe_replacement( $item, $context );
			}
		}

		// Return other primitives as-is
		return $value;
	}

	/**
	 * Extract and replace dynamic expressions in a string using a callback.
	 *
	 * @param string   $value The string containing dynamic expressions.
	 * @param callable $replace_callback The callback to process each dynamic expression.
	 * @return string The string with dynamic expressions replaced.
	 */
	private static function find_and_replace_expressions( string $value, callable $replace_callback ): string {
		if ( empty( $value ) || strpos( $value, '{' ) === false ) {
			return $value;
		}

		// Avoid regex because it's difficult to handle nested braces
		$result = '';
		$brace_depth = 0;
		$current_expression = '';

		$in_string = false;
		$string_char = '';

		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			// Handle string boundaries to avoid misinterpreting braces within strings
			if ( $brace_depth > 0 && ( '"' === $char || "'" === $char || '`' === $char ) ) {
				$is_escaped = $i > 0 && '\\' === $value[ $i - 1 ];
				if ( ! $is_escaped ) {
					if ( ! $in_string ) {
						// Starting a string
						$in_string = true;
						$string_char = $char;
					} else if ( $char === $string_char ) {
						// Ending the current string
						$in_string = false;
					}
				}
			}

			if ( ! $in_string ) {
				if ( '{' === $char ) {
					if ( $brace_depth > 0 ) {
						$current_expression .= $char;
					}
					$brace_depth++;
					continue; // Continue to avoid adding the opening brace to result
				} else if ( '}' === $char ) {
					$brace_depth = max( 0, $brace_depth - 1 ); // Prevent going negative
					if ( 0 === $brace_depth ) {
						// End of a dynamic expression
						$result .= $replace_callback( $current_expression );
						$current_expression = '';
					} else {
						$current_expression .= $char;
					}
					continue;
				}
			}

			// Add character to appropriate location
			if ( $brace_depth > 0 ) {
				$current_expression .= $char;
			} else {
				$result .= $char;
			}
		}

		// If we end while still in a dynamic expression, append the unclosed part (mismatched braces)
		if ( ! empty( $current_expression ) && $brace_depth > 0 ) {
			$result .= '{' . $current_expression;
		}

		return $result;
	}

	/**
	 * Replace all placeholders in the string with context values.
	 *
	 * @param string              $value The string containing placeholders.
	 * @param array<string,mixed> $context The context array with values to replace.
	 * @param bool                $with_detection Whether to return detection flag along with result.
	 * @return ($with_detection is true ? array{0: string, 1: bool} : string)
	 */
	public static function replace_string( string $value, array $context, bool $with_detection = false ) {
		if ( ! is_array( $context ) || empty( $context ) || empty( $value ) || ! str_contains( $value, '{' ) ) {
			return $with_detection ? array( $value, false ) : $value;
		}

		$has_templates = false;

		// Replace all placeholders in the string with context values
		$result = self::find_and_replace_expressions(
			$value,
			function ( $expression ) use ( $context, &$has_templates ) {
				$has_templates = true; // Flag that we found dynamic templates
				$expression_result = self::process_expression( $expression, $context );

				// On no resolution return empty string
				if ( ! isset( $expression_result ) ) {
					return '';
				}

				if ( is_array( $expression_result ) && array_is_list( $expression_result ) ) {
					return implode(
						', ',
						array_map(
							function ( $item ) {
								return EtchTypeAsserter::to_string( $item );
							},
							$expression_result
						)
					);
				}

				return EtchTypeAsserter::to_string( $expression_result );
			}
		);

		return $with_detection ? array( $result, $has_templates ) : $result;
	}

	/**
	 * Check if a value is a loop prop structure.
	 *
	 * Loop prop structures are arrays with 'prop-type' => 'loop' that contain
	 * both a 'key' (string target) and 'data' (resolved array).
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if the value is a loop prop structure.
	 * @phpstan-assert-if-true array{prop-type: string, key: string, data: array<mixed>} $value
	 */
	public static function is_loop_prop_structure( $value ): bool {
		return is_array( $value )
			&& isset( $value['prop-type'] )
			&& 'loop' === $value['prop-type'];
	}

	/**
	 * Process a dynamic expression and resolve its value from the context.
	 *
	 * For loop prop structures (arrays with 'prop-type' => 'loop'):
	 * - By default (null): auto-unwraps to 'data' for backward compatibility
	 * - With 'loop': returns 'key' for LoopBlock's resolution pipeline
	 *
	 * @param string              $expression   The expression to process.
	 * @param array<string,mixed> $context      The context array with values to resolve.
	 * @param string|null         $special_type Optional. If 'loop', returns key instead of data for loop prop structures.
	 * @return mixed The resolved value or null if not found.
	 */
	public static function process_expression( string $expression, array $context, ?string $special_type = null ) {
		$process_result = self::process_expression_with_metadata( $expression, $context );
		$result = $process_result['result'];

		// Handle loop prop structures based on special_type
		if ( self::is_loop_prop_structure( $result ) ) {
			return 'loop' === $special_type ? $result['key'] : $result['data'];
		}

		return $result;
	}

	/**
	 * Process a dynamic expression and resolve its value from the context.
	 *
	 * @param string              $expression The expression to process.
	 * @param array<string,mixed> $context The context array with values to resolve.
	 * @return array{result: mixed, metadata?: array{}} The resolved value and metadata.
	 */
	public static function process_expression_with_metadata( string $expression, array $context ) {
		if ( '' === $expression ) {
			return array( 'result' => $expression );
		}

		// Handle string expressions
		if ( preg_match( '/^["\'](.*)["\']$/', $expression, $matches ) ) {
			return array(
				'result' => $matches[1],
			);
		}

		// Handle numeric values
		if ( is_numeric( $expression ) ) {
			return array(
				'result' => strpos( $expression, '.' ) !== false ? (float) $expression : (int) $expression,
			);
		}

		if ( 'true' === strtolower( $expression ) ) {
			return array(
				'result' => true,
			);
		}
		if ( 'false' === strtolower( $expression ) ) {
			return array(
				'result' => false,
			);
		}

		if (
		( str_starts_with( $expression, '[' ) && str_ends_with( $expression, ']' ) ) ||
		( str_starts_with( $expression, '{' ) && str_ends_with( $expression, '}' ) )
		) {
			$json_value = json_decode( $expression, true );
			if ( ! empty( $json_value ) ) {
				return array(
					'result' => $json_value,
				);
			}
			// If JSON decoding fails, go on to process as a path
		}

		if ( ! is_array( $context ) || empty( $context ) ) {
			return array(
				'result' => null,
			);
		}

		$resolved_expression = self::resolve( $expression, $context );

		return array(
			'result' => $resolved_expression,
		);
	}

	/**
	 * Resolve an expression from the provided context array.
	 *
	 * @param string              $expression The expression to resolve.
	 * @param array<string,mixed> $context The context array to resolve from.
	 * @return mixed The resolved value or null if not found.
	 */
	private static function resolve( string $expression, array $context ) {
		foreach ( $context as $key => $source ) {
			if ( is_object( $source ) ) {
				$source = (array) $source; // Convert object to array for consistency
			}

			if ( $expression === $key ) {
				// If the expression is just the key, return the source directly
				return $source;
			}

			if ( null === $source ||
			( ! str_starts_with( $expression, $key . '.' ) &&
			! str_starts_with( $expression, $key . '[' ) ) ) {
				continue; // Skip if source is not an array/object or does not match the expression
			}

			// Split the expression into parts
			$parts = self::split_path( $expression );

			$result = $source;
			foreach ( array_slice( $parts, 1 ) as $part ) {
				if ( self::is_loop_prop_structure( $result ) ) {
					$result = $result['data'];
				}

				if ( is_array( $result ) && isset( $result[ $part ] ) ) {
					$result = $result[ $part ];
				} elseif ( is_object( $result ) && property_exists( $result, $part ) ) {
					$result = $result->$part;
				} elseif ( ModifiersOld::is_modifier( $part ) ) {
					$result = ModifiersOld::apply_modifier( $result, $part, $context );
				} else {
					// If the key does not exist in the current data, return null
					$result = null;
				}

				if ( null === $result ) {
					break;
				}
			}

			// If the result is undefined (couldn't be resolved), continue to the next source
			if ( null === $result ) {
				continue;
			}

			return $result; // Return the resolved value
		}

		return null;
	}

	/**
	 * Split a path string into parts, respecting parentheses and quotes.
	 *
	 * @param string $path The path to split (e.g., "post.meta.field.toUpperCase()").
	 * @return array<string> Array of path parts
	 */
	public static function split_path( string $path ): array {
		if ( empty( $path ) ) {
			return array();
		}

		$parts = array();
		$current = '';
		$paren_depth = 0;
		$bracket_depth = 0;
		$in_single_quote = false;
		$in_double_quote = false;
		$length = strlen( $path );

		$push_part = function ( $part ) use ( &$parts ) {
			if ( ! empty( $part ) ) {
				$parts[] = $part;
			}
		};

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $path[ $i ];
			$is_quoted = $in_single_quote || $in_double_quote;

			// Handle escape sequences
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

			// Track parentheses and quotes
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

				// Check if this is part of ["..."](...) pattern.
				// This ensures we keep the function call attached to the bracketed key.
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

		return $parts;
	}
}
