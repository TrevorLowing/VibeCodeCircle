<?php
/**
 * EtchTypeAsserter utility class for Blocks system.
 *
 * Enhanced EtchTypeAsserter with additional functionality for Blocks.
 * Self-contained utility for Blocks usage.
 *
 * @package Etch\Blocks\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities;

/**
 * Utility class for type checking and conversion operations used in Blocks system.
 *
 * Provides type conversions with enhanced array parsing.
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
	 * Safely converts a mixed value to float (number).
	 *
	 * @param mixed $value The value to convert.
	 * @return float The converted float or 0 if not convertible.
	 */
	public static function to_number( $value ): float {
		return is_numeric( $value ) ? (float) $value : 0;
	}

	/**
	 * Safely converts a mixed value to an array with enhanced parsing.
	 *
	 * Enhanced version that handles:
	 * - Already arrays/objects (casts to array)
	 * - JSON strings (decodes JSON)
	 * - Comma-separated strings (splits by comma)
	 *
	 * @param mixed $value The value to convert.
	 * @return array<string|int, mixed> The converted array or empty array if not convertible.
	 */
	public static function to_array( $value ): array {
		// Handle already arrays or objects
		if ( is_array( $value ) || is_object( $value ) ) {
			return (array) $value;
		}

		// Handle JSON strings
		if ( is_string( $value ) && ! empty( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
			// Fallback to comma-separated values
			return array_map( 'trim', explode( ',', $value ) );
		}

		return array();
	}
}
