<?php
/**
 * Component Property Resolver
 *
 * Utility class for resolving component properties from definitions and instance attributes.
 *
 * @package Etch\Blocks\Utilities
 */

namespace Etch\Blocks\Utilities;

use Etch\Blocks\Types\ComponentProperty;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Preprocessor\Utilities\LoopHandlerManager;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;

/**
 * ComponentPropertyResolver class
 *
 * Handles resolution of component properties by merging defaults with instance attributes.
 * Instance attributes are pre-resolved by ComponentBlock, so this class focuses on:
 * - Resolving dynamic expressions in default values
 * - Merging defaults with pre-resolved instance attributes
 * - Type casting values to their defined primitive types
 * - Handling loop props (specialized: array) - returns structured array with key and data
 */
class ComponentPropertyResolver {

	/**
	 * Resolve component properties from property definitions and instance attributes.
	 *
	 * Instance attributes are expected to be pre-resolved by ComponentBlock.
	 * This method resolves default values, merges them with instance attributes,
	 * and applies type casting.
	 *
	 * @param array<int|string, mixed>                      $property_definitions Array of property definitions from pattern.
	 * @param array<string, mixed>                          $instance_attributes  Instance attributes from component block (pre-resolved).
	 * @param array<int, array{key: string, source: mixed}> $sources              Sources for dynamic expression evaluation (used for defaults).
	 * @return array<string, mixed> Resolved properties array.
	 */
	public static function resolve_properties( array $property_definitions, array $instance_attributes, array $sources = array() ): array {
		$resolved_props = array();

		// First, build a map of ComponentProperty instances by key
		$property_map = array();
		foreach ( $property_definitions as $prop_data ) {
			// Ensure prop_data is an array before passing to from_array
			if ( ! is_array( $prop_data ) ) {
				continue;
			}
			$property = ComponentProperty::from_array( $prop_data );
			if ( null !== $property ) {
				$property_map[ $property->key ] = $property;
			}
		}

		// Start with defaults from property definitions
		foreach ( $property_map as $key => $property ) {
			$default_value = $property->default;
			$primitive = $property->get_primitive();

			// Guard against recursion: early return empty value if default contains {props.}
			if ( is_string( $default_value ) && strpos( $default_value, '{props.' ) !== false ) {
				$resolved_props[ $key ] = self::get_empty_value_for_type( $primitive );
				continue;
			}

			// Evaluate dynamic expressions in default value if sources are available
			if ( ! empty( $sources ) && is_string( $default_value ) ) {
				$default_value = DynamicContentProcessor::apply(
					$default_value,
					array(
						'sources' => $sources,
					)
				);
			}

			if ( $property->is_specialized_array() ) {
				$resolved_props[ $key ] = self::resolve_loop_property_value( $default_value, $sources );
			} else {
				// Cast default to appropriate type
				$resolved_props[ $key ] = self::cast_to_type( $default_value, $primitive, $sources );
			}
		}

		// Override with instance attributes (pre-resolved by ComponentBlock), applying type casting
		foreach ( $instance_attributes as $key => $value ) {
			// Only process attributes that have property definitions
			if ( ! isset( $property_map[ $key ] ) ) {
				continue;
			}

			// Null values should behave like “not provided” so defaults apply.
			if ( null === $value ) {
				continue;
			}

			$property = $property_map[ $key ];
			$primitive = $property->get_primitive();

			// Handle loop props (specialized: array) - always returns string loop target
			// Instance attributes are pre-resolved, so just pass through
			if ( $property->is_specialized_array() ) {
				$resolved_props[ $key ] = self::resolve_loop_property_value( $value, $sources );
			} else {
				$resolved_props[ $key ] = self::cast_to_type( $value, $primitive, $sources );
			}
		}

		return $resolved_props;
	}

	/**
	 * Resolve loop property value to a structured array with both key and data.
	 *
	 * For loop props (specialized "array" type), this returns a structure containing:
	 * - 'prop-type': 'loop' - identifies this as a loop prop structure
	 * - 'key': string - the loop target key for LoopBlock's resolution pipeline
	 * - 'data': array - the eagerly resolved loop data for backward compatibility
	 *
	 * @param mixed                                         $value   The property value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for expression resolution.
	 * @return array{prop-type: string, key: string, data: array<mixed>} The loop prop structure.
	 */
	private static function resolve_loop_property_value( $value, array $sources ): array {
		$key = self::resolve_loop_key( $value, $sources );
		$data = self::resolve_loop_data( $value, $sources );

		return array(
			'prop-type' => 'loop',
			'key'       => $key,
			'data'      => $data,
		);
	}

	/**
	 * Resolve the loop key (string target) from a value.
	 *
	 * @param mixed                                         $value   The property value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for props.* expression resolution.
	 * @return string The loop target string (empty string if no valid target).
	 */
	private static function resolve_loop_key( $value, array $sources ): string {
		$string_value = EtchTypeAsserter::to_string( $value );

		if ( '' === $string_value ) {
			return '';
		}

		$expression = $string_value;
		$trimmed = trim( $string_value );
		if ( str_starts_with( $trimmed, '{' ) && str_ends_with( $trimmed, '}' ) ) {
			$expression = substr( $trimmed, 1, -1 );
		}

		// Only resolve props.* expressions - other expressions (this.*, item.*, etc.)
		// should be passed through as-is for LoopBlock to resolve from context
		if ( ! empty( $sources ) && strpos( $expression, 'props.' ) !== false ) {
			$parsed = DynamicContentProcessor::process_expression(
				$expression,
				array(
					'sources' => $sources,
				)
			);
			if ( is_string( $parsed ) && '' !== $parsed ) {
				return $parsed;
			}
		}

		return $expression;
	}

	/**
	 * Resolve the loop data (array) from a value.
	 *
	 * Checks loop presets, context expressions, and falls back to array parsing.
	 *
	 * @param mixed                                         $value   The property value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<mixed> The resolved array data.
	 */
	private static function resolve_loop_data( $value, array $sources ): array {
		// If already an array, return as is
		if ( is_array( $value ) ) {
			return $value;
		}

		$string_value = EtchTypeAsserter::to_string( $value );

		if ( empty( $string_value ) ) {
			return array();
		}

		$expression = $string_value;
		$trimmed = trim( $string_value );
		if ( str_starts_with( $trimmed, '{' ) && str_ends_with( $trimmed, '}' ) ) {
			$expression = substr( $trimmed, 1, -1 );
		}

		// Check if it's a global loop key (by database key first)
		$loop_presets = LoopHandlerManager::get_loop_presets();
		if ( isset( $loop_presets[ $expression ] ) ) {
			return LoopHandlerManager::get_loop_preset_data( $expression, array() );
		}

		// Check if it matches any loop by key property
		$found_loop_id = LoopHandlerManager::find_loop_by_key( $expression );
		if ( $found_loop_id ) {
			return LoopHandlerManager::get_loop_preset_data( $found_loop_id, array() );
		}

		if ( ! empty( $sources ) ) {
			$parsed = DynamicContentProcessor::process_expression(
				$expression,
				array(
					'sources' => $sources,
				)
			);
			if ( is_array( $parsed ) ) {
				return $parsed;
			}
		}

		// Fall back to JSON decode or comma-separated parsing
		return EtchTypeAsserter::to_array( $expression );
	}

	/**
	 * Get empty value for a given primitive type.
	 *
	 * @param string $primitive The primitive type.
	 * @return mixed Empty value for the type.
	 */
	private static function get_empty_value_for_type( string $primitive ) {
		switch ( $primitive ) {
			case 'string':
				return '';
			case 'number':
				return 0;
			case 'boolean':
				return false;
			case 'array':
			case 'object':
				return array();
			default:
				return '';
		}
	}

	/**
	 * Cast a value to the correct primitive type.
	 *
	 * @param mixed                                         $value     The value to cast.
	 * @param string                                        $primitive  The primitive type (string, number, boolean, object, array).
	 * @param array<int, array{key: string, source: mixed}> $sources   Sources for dynamic expression evaluation (optional).
	 * @return mixed The cast value.
	 */
	private static function cast_to_type( $value, string $primitive, array $sources = array() ) {
		// Handle null/empty defaults
		if ( null === $value || '' === $value ) {
			return self::get_empty_value_for_type( $primitive );
		}

		switch ( $primitive ) {
			case 'string':
				return EtchTypeAsserter::to_string( $value );

			case 'number':
				return EtchTypeAsserter::to_number( $value );

			case 'boolean':
				return EtchTypeAsserter::to_bool( $value );

			case 'array':
			case 'object':
				return EtchTypeAsserter::to_array( $value );

			default:
				return $value;
		}
	}
}
