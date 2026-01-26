<?php
/**
 * EtchTypeAsserter utility class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities;

/**
 * Utility class for type checking and conversion operations used throughout Etch.
 */
class EtchTypeAsserter {

	/**
	 * Safely converts a mixed value to string, with fallback.
	 *
	 * @param mixed  $value The value to convert.
	 * @param string $fallback Fallback value if conversion fails.
	 * @return string The converted string or fallback.
	 */
	public static function to_string( $value, string $fallback = '' ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$json_encoded = json_encode( $value );
			return false !== $json_encoded ? $json_encoded : $fallback;
		}

		return $fallback;
	}

	/**
	 * Safely converts a mixed value to boolean.
	 *
	 * @param mixed $value The value to convert.
	 * @return bool The converted boolean or false if not convertible.
	 */
	public static function to_bool( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? false;
	}

	/**
	 * Safely converts a mixed value to string or null.
	 *
	 * @param mixed $value The value to convert.
	 * @return string|null The converted string or null if not convertible.
	 */
	public static function to_string_or_null( $value ): ?string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return null;
	}

	/**
	 * Checks if a value is a valid array of strings or nulls.
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if valid array of strings/nulls.
	 */
	public static function is_string_array( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && null !== $item ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalizes an array to contain only strings and nulls.
	 *
	 * @param array<mixed> $array The array to normalize.
	 * @return array<int, string|null> Normalized array.
	 */
	public static function normalize_to_string_array( array $array ): array {
		$normalized = array();

		foreach ( $array as $item ) {
			$normalized[] = self::to_string_or_null( $item );
		}

		return $normalized;
	}

	/**
	 * Safely converts a parsed result from EtchParser to string.
	 *
	 * @param mixed  $parsed_result The result from EtchParser::parse().
	 * @param string $fallback Fallback value if conversion fails.
	 * @return string The converted string or fallback.
	 */
	public static function parse_result_to_string( $parsed_result, string $fallback = '' ): string {
		return self::to_string( $parsed_result, $fallback );
	}

	/**
	 * Safely converts a parsed result from EtchParser to string or null.
	 *
	 * @param mixed $parsed_result The result from EtchParser::parse().
	 * @return string|null The converted string or null.
	 */
	public static function parse_result_to_string_or_null( $parsed_result ): ?string {
		return self::to_string_or_null( $parsed_result );
	}

	/**
	 * Checks if a value can be safely converted to string.
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if value can be converted to string.
	 */
	public static function is_stringable( $value ): bool {
		return is_scalar( $value ) ||
			   ( is_object( $value ) && method_exists( $value, '__toString' ) );
	}

	/**
	 * Validates and normalizes attribute values for HTML output.
	 *
	 * @param mixed $value The attribute value to validate.
	 * @return string Normalized attribute value.
	 */
	public static function normalize_attribute_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return self::to_string( $value );
	}

	/**
	 * Safely converts a mixed value to an array.
	 *
	 * @param mixed $value The value to convert.
	 * @return array<string, mixed> The converted array or empty array if not convertible.
	 */
	public static function to_array( $value ): array {
		if ( is_array( $value ) || is_object( $value ) ) {
			return (array) $value;
		}

		return array();
	}

	/**
	 * Safely converts a mixed value to a non-empty string.
	 *
	 * @param mixed  $value The value to convert.
	 * @param string $fallback Fallback value if conversion fails or value is empty.
	 * @return string The converted non-empty string or fallback.
	 */
	public static function to_non_empty_string( $value, string $fallback = '' ): string {
		$converted = self::to_string( $value );
		return '' !== $converted ? $converted : $fallback;
	}

	/**
	 * Safely converts a mixed value to an indexed array, filtering out non-array elements.
	 *
	 * @param mixed $value The value to convert.
	 * @return array<int, array<string, mixed>> An indexed array containing only array elements.
	 */
	public static function to_indexed_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$indexed_array = array();
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				$indexed_array[] = $item;
			}
		}

		return $indexed_array;
	}
}
