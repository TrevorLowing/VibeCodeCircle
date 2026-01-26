<?php
/**
 * Modifiers class for Blocks dynamic content.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * Modifiers class for handling modifier application in dynamic data.
 */
class Modifiers {
	/**
	 * Parses modifier arguments from a string.
	 *
	 * @param string $arg_string The string to parse.
	 * @return array<string> The parsed arguments.
	 */
	public static function parse_modifier_arguments( string $arg_string ): array {
		return ModifierParser::split_args( $arg_string );
	}

	/**
	 * Check if the string is a modifier pattern
	 *
	 * @param string $part  String to be checked (e.g., 'format("y-m-d")', 'toUpperCase()').
	 * @return bool
	 */
	public static function is_modifier( string $part ): bool {
		return ModifierParser::is_modifier( $part );
	}


	/**
	 * Rtrim a character once from the end of a string
	 *
	 * @param string $string The input string.
	 * @param string $char The character to trim.
	 * @return string The modified string.
	 */
	public static function rtrim_once( $string, $char ) {
		if ( substr( $string, -1 ) === $char ) {
			return substr( $string, 0, -1 );
		}
		return $string;
	}

	/**
	 * Apply the modifier
	 *
	 * @param mixed                                         $value  Value to be modified.
	 * @param string                                        $modifier  Modifier to be applied.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for resolving modifier arguments.
	 * @return mixed
	 */
	public static function apply_modifier( mixed $value, string $modifier, array $sources = array() ): mixed {
		if ( ! self::is_modifier( $modifier ) ) {
			return $value;
		}

		$parsed = ModifierParser::parse( $modifier );
		$method = $parsed['method'];
		$args = $parsed['args'];

		$parsed_args = self::parse_modifier_arguments( $args );

		$resolved_args = array_map(
			static function ( $arg ) use ( $sources ) {
				return DynamicContentProcessor::process_expression(
					$arg,
					array(
						'sources' => $sources,
					)
				);
			},
			$parsed_args
		);

		switch ( $method ) {
			// Date modifier.
			case 'format':  // Alias for dateFormat. Deprecated. TODO: Remove in future.
			case 'dateFormat':
				if ( ! isset( $resolved_args[0] ) || ! is_string( $resolved_args[0] ) ) {
					return $value; // Only apply to strings or numeric timestamps
				}
				$format = $resolved_args[0];
				try {
					if ( is_numeric( $value ) ) {
						$date = new \DateTime( '@' . $value );
					} else if ( is_string( $value ) ) {
						$date = new \DateTime( $value );
					} else {
						return $value;
					}

					// Wp is automatically localizing dates based on site language and timezone
					if ( function_exists( 'wp_date' ) ) {
						return wp_date( $format, (int) $date->format( 'U' ) );
					}

					return $date->format( $format );
				} catch ( \Exception $e ) {
					return $value;
				}

				// Numbers modifiers.
			case 'numberFormat':
				if ( ! is_numeric( $value ) ) {
					return $value;
				}

				$decimals = 0;
				$decimal_point = '.';
				$thousands_separator = ',';

				if ( isset( $resolved_args[0] ) && is_numeric( $resolved_args[0] ) && ! is_string( $resolved_args[0] ) ) {
					$decimals = (int) $resolved_args[0];
				}
				if ( isset( $resolved_args[1] ) && is_string( $resolved_args[1] ) ) {
					$decimal_point = $resolved_args[1];
				}
				if ( isset( $resolved_args[2] ) && is_string( $resolved_args[2] ) ) {
					$thousands_separator = $resolved_args[2];
				}

				return number_format( (float) $value, $decimals, $decimal_point, $thousands_separator );
			case 'toInt':
				if ( is_int( $value ) ) {
					return $value;
				}
				if ( is_numeric( $value ) ) {
					return (int) $value;
				}
				return $value;

			case 'ceil':
				if ( ! is_numeric( $value ) ) {
					return $value;
				}
				return (int) ceil( (float) $value );

			case 'round':
				if ( ! is_numeric( $value ) ) {
					return $value;
				}

				$precisionArgs = 0;
				if ( '' !== $args ) {
					$precisionArgs = (int) trim( $args );
				}
				return round( (float) $value, $precisionArgs );

			case 'floor':
				if ( ! is_numeric( $value ) ) {
					return $value;
				}
				return (int) floor( (float) $value );

			// String modifiers.
			case 'toUppercase':
			case 'toUpperCase':
				if ( ! is_string( $value ) ) {
					return $value; // Only apply to strings
				}

				return mb_strtoupper( $value );
			case 'toLowercase':
			case 'toLowerCase':
				if ( ! is_string( $value ) ) {
					return $value; // Only apply to strings
				}

				return mb_strtolower( $value );

			case 'toString':
				return EtchTypeAsserter::to_string( $value );
			case 'indexOf':
				if ( ! isset( $resolved_args[0] ) ) {
					return -1;
				}

				$search_value = $resolved_args[0];

				if ( is_string( $value ) ) {
					$pos = strpos( $value, EtchTypeAsserter::to_string( $search_value ) );
					return false === $pos ? -1 : $pos;
				}

				if ( is_array( $value ) ) {
					$index = array_search( $search_value, $value, true );
					return false === $index ? -1 : $index;
				}

				return -1;
			case 'split':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				$separator = ',';
				if ( isset( $resolved_args[0] ) && is_string( $resolved_args[0] ) ) {
					$separator = $resolved_args[0];
				}

				if ( '' === $separator ) {
					return str_split( $value );
				}

				return explode( $separator, $value );
			case 'join':
				if ( ! is_array( $value ) ) {
					return $value;
				}

				$separator = ',';
				if ( isset( $resolved_args[0] ) && is_string( $resolved_args[0] ) ) {
					$separator = $resolved_args[0];
				}

				return implode( $separator, $value );

			case 'toBool':
				if ( is_bool( $value ) ) {
					return $value;
				}

				if ( is_null( $value ) ) {
					return false;
				}

				if ( is_numeric( $value ) ) {
					return (bool) (int) $value;
				}

				if ( is_string( $value ) ) {
					$normalized = strtolower( trim( $value ) );
					$true_values = array( 'true', '1', 'yes', 'y', 'on' );
					$false_values = array( 'false', '0', 'no', 'n', 'off', '' );
					if ( in_array( $normalized, $true_values, true ) ) {
						return true;
					}
					if ( in_array( $normalized, $false_values, true ) ) {
						return false;
					}
				}
				return (bool) $value;

			// Trimming modifiers.
			case 'trim':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				return trim( $value );

			case 'ltrim':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				return ltrim( $value );

			case 'rtrim':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				return rtrim( $value );
			case 'toSlug':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				$slug = strtolower( trim( $value ) );
				$converted = iconv( 'UTF-8', 'ASCII//TRANSLIT', $slug );
				if ( false === $converted ) {
					$converted = $slug;
				}
				$replaced = preg_replace( '/[^a-z0-9]+/', '-', $converted );
				if ( null === $replaced ) {
					$replaced = $converted;
				}

				return trim( $replaced, '-' );
			case 'applyData':
				return DynamicContentProcessor::apply(
					$value,
					array(
						'sources' => $sources,
					)
				);
			case 'truncateChars':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				$char_count = 0; // Default char count
				$ellipses = '...'; // Default ending

				if ( isset( $resolved_args[0] ) && is_numeric( $resolved_args[0] ) ) {
					$char_count = (int) $resolved_args[0];
				}

				if ( isset( $resolved_args[1] ) && is_string( $resolved_args[1] ) ) {
					$ellipses = $resolved_args[1];
				}

				if ( mb_strlen( $value ) <= $char_count ) {
					return $value; // No truncation needed
				}

				return mb_substr( $value, 0, $char_count ) . $ellipses;
			case 'truncateWords':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				$word_count = 0; // Default word count
				$ellipses = '...'; // Default ending

				if ( isset( $resolved_args[0] ) && is_numeric( $resolved_args[0] ) ) {
					$word_count = (int) $resolved_args[0];
				}

				if ( isset( $resolved_args[1] ) && is_string( $resolved_args[1] ) ) {
					$ellipses = $resolved_args[1];
				}

				$words = explode( ' ', $value );
				if ( count( $words ) <= $word_count ) {
					return $value; // No truncation needed
				}

				return implode( ' ', array_slice( $words, 0, $word_count ) ) . $ellipses;
			case 'concat':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				$concat_str = '';
				$arg_count = count( $resolved_args );
				for ( $i = 0; $i < $arg_count; $i++ ) {
					$concat_str .= EtchTypeAsserter::to_string( $resolved_args[ $i ] );
				}

				return $value . $concat_str;

			case 'length':
				if ( is_string( $value ) ) {
					return mb_strlen( $value );
				}

				if ( is_array( $value ) && array_is_list( $value ) ) {
					return count( $value );
				}
				return $value;

			case 'reverse':
				if ( is_string( $value ) ) {
					return strrev( $value );
				}

				if ( is_array( $value ) ) {
					return array_reverse( $value );
				}
				return $value;

			case 'at':
				// Access by index in an array
				if ( ! is_array( $value ) || ! isset( $resolved_args[0] ) || ! array_is_list( $value ) ) {
					return $value;
				}

				$index = 0;
				if ( is_numeric( $resolved_args[0] ) && ! is_string( $resolved_args[0] ) ) {
					$index = (int) $resolved_args[0];
				}

				$array_length = count( $value );

				// Handle negative indexes (convert to positive equivalent)
				if ( $index < 0 ) {
					$index = $array_length + $index;
				}

				// Check if the final index is valid
				if ( isset( $value[ $index ] ) ) {
					return $value[ $index ];
				}

				return null;

			case 'slice':
				if ( ! is_array( $value ) || ! isset( $resolved_args[0] ) || ! array_is_list( $value ) ) {
					return $value;
				}

				$start = 0;
				$array_length = count( $value );
				$end = $array_length;

				if ( is_numeric( $resolved_args[0] ) && ! is_string( $resolved_args[0] ) ) {
					$start = (int) $resolved_args[0];
				}

				if ( isset( $resolved_args[1] ) && is_numeric( $resolved_args[1] ) && ! is_string( $resolved_args[1] ) ) {
					$end = (int) $resolved_args[1];
				}

				// Handle negative start index
				if ( $start < 0 ) {
					$start = max( 0, $array_length + $start );
				}

				// Handle negative end index
				if ( $end < 0 ) {
					$end = max( 0, $array_length + $end );
				}

				$start = min( $start, $array_length );
				$length = max( 0, $end - $start );

				return array_slice( $value, $start, $length );

			case 'urlEncode':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				$revert = array(
					'%21' => '!',
					'%2A' => '*',
					'%27' => "'",
					'%28' => '(',
					'%29' => ')',
				);
				return strtr( rawurlencode( $value ), $revert );

			case 'urlDecode':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				return rawurldecode( $value );
			case 'includes':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$search_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				if ( is_string( $value ) ) {
					return str_contains( $value, EtchTypeAsserter::to_string( $search_value ) ) ? $true_value : $false_value;
				}

				if ( is_array( $value ) ) {
					return in_array( $search_value, $value, true ) ? $true_value : $false_value;
				}

				return false;
			case 'startsWith':
				if ( ! is_string( $value ) || ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$search_value = EtchTypeAsserter::to_string( $resolved_args[0] );
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				return str_starts_with( $value, $search_value ) ? $true_value : $false_value;
			case 'endsWith':
				if ( ! is_string( $value ) || ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$search_value = EtchTypeAsserter::to_string( $resolved_args[0] );
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				return str_ends_with( $value, $search_value ) ? $true_value : $false_value;
			case 'less':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$compare_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				if ( ( is_numeric( $value ) || is_string( $value ) ) &&
				( is_numeric( $compare_value ) || is_string( $compare_value ) ) ) {
					return $value < $compare_value ? $true_value : $false_value;
				}

				return $false_value;

			case 'lessOrEqual':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$compare_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				if ( ( is_numeric( $value ) || is_string( $value ) ) &&
				( is_numeric( $compare_value ) || is_string( $compare_value ) ) ) {
					return $value <= $compare_value ? $true_value : $false_value;
				}

				return $false_value;

			case 'greater':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$compare_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				if ( ( is_numeric( $value ) || is_string( $value ) ) &&
				( is_numeric( $compare_value ) || is_string( $compare_value ) ) ) {
					return $value > $compare_value ? $true_value : $false_value;
				}

				return $false_value;

			case 'greaterOrEqual':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$compare_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				if ( ( is_numeric( $value ) || is_string( $value ) ) &&
				( is_numeric( $compare_value ) || is_string( $compare_value ) ) ) {
					return $value >= $compare_value ? $true_value : $false_value;
				}

				return $false_value;

			case 'equal':
				if ( ! isset( $resolved_args[0] ) ) {
					return false;
				}

				$compare_value = $resolved_args[0];
				$true_value  = $resolved_args[1] ?? true;
				$false_value = $resolved_args[2] ?? false;

				return $value === $compare_value ? $true_value : $false_value;
			case 'stripTags':
				if ( ! is_string( $value ) ) {
					return $value;
				}

				return strip_tags( $value );
			case 'pluck':
				if ( ! is_array( $value ) || ! isset( $resolved_args[0] ) || ! array_is_list( $value ) ) {
					return array();
				}

				$property = EtchTypeAsserter::to_string( $resolved_args[0] );
				$keys = explode( '.', $property );

				return array_map(
					function ( $item ) use ( $keys ) {
						if (
						! $item ||
						( ! is_object( $item ) && ! is_array( $item ) )
						) {
							return null;
						}

						$current = $item;

						foreach ( $keys as $key ) {
							if (
							! $current ||
							( ! is_object( $current ) && ! is_array( $current ) )
							) {
								return null;
							}

							// Handle both objects and associative arrays
							if ( is_object( $current ) ) {
								$current = $current->$key ?? null;
							} else {
								$current = $current[ $key ] ?? null;
							}
						}

						return $current;
					},
					$value
				);

			case 'unserializePhp':
			case 'unserializePHP':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				$output = maybe_unserialize( $value );
				// prevent unserializing to objects for security
				if ( is_object( $output ) ) {
					return null;
				}
				return $output;

			default:
				// If no known modifier is matched, return null
				return null;
		}
	}
}
