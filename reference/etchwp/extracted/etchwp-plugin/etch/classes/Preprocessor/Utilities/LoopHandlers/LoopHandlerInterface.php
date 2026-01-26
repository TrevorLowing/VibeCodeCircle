<?php
/**
 * Interface for loop handlers in Etch.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

/**
 * Interface that all loop handlers must implement.
 */
abstract class LoopHandlerInterface {

	/**
	 * Get loop data for the specified query/preset name.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params The loop parameters.
	 * @return array<int, array<string, mixed>> Array of data items for the loop.
	 */
	abstract public function get_loop_data( string $query_name, array $loop_params = array() ): array;

	/**
	 * Parse a default value string into its proper PHP type.
	 * Handles: integers, floats, booleans, quoted strings.
	 *
	 * @param string $default_str The default value string to parse.
	 * @return mixed The parsed value with proper type.
	 */
	protected function parse_default_value( string $default_str ): mixed {
		$trimmed = trim( $default_str );

		// Parse integers (including negative).
		if ( preg_match( '/^-?\d+$/', $trimmed ) ) {
			return intval( $trimmed );
		}

		// Parse floats (including negative).
		if ( preg_match( '/^-?\d+\.\d+$/', $trimmed ) ) {
			return floatval( $trimmed );
		}

		// Parse booleans.
		if ( 'true' === $trimmed ) {
			return true;
		}
		if ( 'false' === $trimmed ) {
			return false;
		}

		// Parse quoted strings - remove surrounding quotes.
		if ( ( str_starts_with( $trimmed, "'" ) && str_ends_with( $trimmed, "'" ) ) ||
			( str_starts_with( $trimmed, '"' ) && str_ends_with( $trimmed, '"' ) ) ) {
			return substr( $trimmed, 1, -1 );
		}

		// Return as-is if no specific type detected.
		return $trimmed;
	}

	/**
	 * Extract default parameter values from a value containing $param ?? default patterns.
	 * First occurrence of each param wins (for consistent default values across fields).
	 *
	 * @param mixed                $value The value to extract defaults from.
	 * @param array<string, mixed> $defaults Existing defaults array (passed by reference for recursion).
	 * @return array<string, mixed> Array of param => default value pairs.
	 */
	protected function extract_default_params( mixed $value, array &$defaults = array() ): array {
		if ( is_string( $value ) ) {
			// Match $param ?? default patterns.
			// Pattern: $paramName followed by ?? and default value (number, boolean, or quoted string).
			$pattern = '/(\$\w+)\s*\?\?\s*(-?\d+(?:\.\d+)?|true|false|\'[^\']*\')/';

			if ( preg_match_all( $pattern, $value, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$param_name   = $match[1];
					$default_value = $match[2];

					// First occurrence wins - don't overwrite if already found.
					if ( ! array_key_exists( $param_name, $defaults ) ) {
						$defaults[ $param_name ] = $this->parse_default_value( $default_value );
					}
				}
			}
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $val ) {
				$this->extract_default_params( $val, $defaults );
			}
		}

		return $defaults;
	}

	/**
	 * Replace loop parameters in a value.
	 * Also handles $param ?? default syntax for fallback values.
	 *
	 * @param mixed                $value The value to replace loop parameters in.
	 * @param array<string, mixed> $loop_params The loop parameters (should already include extracted defaults).
	 * @return mixed The value with loop parameters replaced.
	 */
	protected function replace_loop_params_in_array( mixed $value, array $loop_params ): mixed {
		if ( is_string( $value ) ) {
			// Handle $param ?? default patterns - replace entire pattern with param value.
			$pattern = '/(\$\w+)\s*\?\?\s*(-?\d+(?:\.\d+)?|true|false|\'[^\']*\')/';
			$result  = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $loop_params ) {
					$param_name = $matches[1];

					if ( array_key_exists( $param_name, $loop_params ) ) {
						$arg_value = $loop_params[ $param_name ];
						if ( null === $arg_value || '' === $arg_value ) {
							return '';
						}
						return is_scalar( $arg_value ) ? (string) $arg_value : '';
					}

					// This shouldn't happen since defaults are extracted and merged, but fallback.
					return '';
				},
				$value
			);

			if ( null === $result ) {
				$result = $value;
			}

			// Check if entire string is just a param reference (direct match for type preservation).
			if ( array_key_exists( $result, $loop_params ) ) {
				return $loop_params[ $result ];
			}

			// Handle remaining simple $param patterns (without defaults).
			if ( ! empty( $loop_params ) ) {
				$sorted_keys = array_keys( $loop_params );
				usort(
					$sorted_keys,
					function ( $a, $b ) {
						return strlen( $b ) - strlen( $a );
					}
				);

				foreach ( $sorted_keys as $key ) {
					if ( strpos( $result, $key ) !== false ) {
						$arg_value = $loop_params[ $key ];
						if ( null === $arg_value || '' === $arg_value ) {
							$replacement = '';
						} elseif ( is_array( $arg_value ) || is_object( $arg_value ) ) {
							$encoded     = json_encode( $arg_value );
							$replacement = false !== $encoded ? $encoded : '';
						} elseif ( is_scalar( $arg_value ) ) {
							$replacement = (string) $arg_value;
						} else {
							$replacement = '';
						}

						$result = str_replace( $key, $replacement, $result );
					}
				}
			}

			return $result;
		}

		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $val ) {
				$result[ $key ] = $this->replace_loop_params_in_array( $val, $loop_params );
			}
			return $result;
		}

		return $value; // If not a string or array, return as is.
	}

	/**
	 * Get query arguments for the specified query name.
	 *
	 * @param string               $query_name The name of the query preset.
	 * @param array<string, mixed> $loop_params Additional parameters for the loop.
	 * @return array<string, mixed> Query arguments array.
	 */
	protected function get_query_args( string $query_name, array $loop_params ): array {
		$loop_presets = get_option( 'etch_loops', array() );

		if ( ! is_array( $loop_presets ) || ! isset( $loop_presets[ $query_name ]['config']['args'] ) ) {
			return array();
		}

		$query_args = $loop_presets[ $query_name ]['config']['args'];

		if ( ! is_array( $query_args ) ) {
			return array();
		}

		// Extract default values from query args (first occurrence wins).
		$defaults = array();
		$this->extract_default_params( $query_args, $defaults );

		// Merge defaults with provided params (provided params take precedence).
		$merged_params = array_merge( $defaults, $loop_params );

		$replaced_args = $this->replace_loop_params_in_array( $query_args, $merged_params );
		return is_array( $replaced_args ) ? $replaced_args : array();
	}
}
