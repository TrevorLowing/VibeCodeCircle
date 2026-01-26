<?php
/**
 * EtchDataComponentProperty class for Etch plugin
 *
 * This file contains the EtchDataComponentProperty class which is responsible for
 * extracting and validating component property data from blocks.
 *
 * @package Etch
 */

namespace Etch\Preprocessor\Data;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchDataComponentProperty class for handling component property data extraction and validation.
 */
class EtchDataComponentProperty {

	/**
	 * The property key.
	 *
	 * @var string
	 */
	public $key;

	/**
	 * The primitive type (string, number, boolean, object, array).
	 *
	 * @var string
	 */
	public $primitive;

	/**
	 * The specialized type of the property.
	 *
	 * @var string
	 */
	public $specialized;

	/**
	 * The default value for the property.
	 *
	 * @var mixed
	 */
	public $default;


	/**
	 * Value of the property.
	 *
	 * @var mixed
	 */
	public $value;

	/**
	 * Whether this is a valid component property.
	 *
	 * @var bool
	 */
	private $isValid = false;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $rawData Raw component property data.
	 */
	public function __construct( $rawData = array() ) {
		$this->initialize_defaults();
		$this->extract_data( $rawData );

		// Initialize value to the typed default
		$this->value = $this->get_typed_default();
	}

	/**
	 * Create EtchDataComponentProperty from array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self|null EtchDataComponentProperty instance or null if not valid.
	 */
	public static function from_array( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$property = new self( $data );
		return $property->is_valid() ? $property : null;
	}

	/**
	 * Check if this is a valid component property.
	 *
	 * @return bool True if valid component property.
	 */
	public function is_valid() {
		return $this->isValid;
	}

	/**
	 * Get default value with proper type casting.
	 *
	 * @return mixed Default value cast to appropriate type.
	 */
	public function get_typed_default() {

		// early return '' if the default is {props.something} as it's a infinity loop causer (or some other cause of app breaking)
		if ( is_string( $this->default ) && strpos( $this->default, '{props.' ) !== false ) {
			return '';
		}

		if ( 'array' === $this->specialized ) {
			return EtchTypeAsserter::to_string( $this->default );
		}

		switch ( $this->primitive ) {
			case 'string':
				return EtchTypeAsserter::to_string( $this->default );

			case 'number':
				return is_numeric( $this->default ) ? (float) $this->default : 0;

			case 'boolean':
				return EtchTypeAsserter::to_bool( $this->default );

			case 'object':
				return EtchTypeAsserter::to_array( $this->default );

			case 'array':
				return EtchTypeAsserter::to_array( $this->default );

			default:
				return $this->default;
		}
	}

	/**
	 * Set the property value with proper type casting.
	 *
	 * @param mixed $value The value to set.
	 * @return void
	 */
	public function set_value( $value ) {
		$this->value = $this->cast_value_to_type( $value );
	}

	/**
	 * Get the current value of the property.
	 *
	 * @return mixed The current value.
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Cast a value to the correct primitive type.
	 *
	 * @param mixed $value The value to cast.
	 * @return mixed The cast value.
	 */
	private function cast_value_to_type( $value ) {
		switch ( $this->primitive ) {
			case 'string':
				return EtchTypeAsserter::to_string( $value );

			case 'number':
				return is_numeric( $value ) ? (float) $value : 0;

			case 'boolean':
				return EtchTypeAsserter::to_bool( $value );

			case 'array':
				return $this->cast_to_array( $value );

			case 'object':
				return $this->cast_to_array( $value );

			default:
				return $value;
		}
	}

	/**
	 * Cast a value to an array with JSON parsing support.
	 *
	 * @param mixed $value The value to cast.
	 * @return array<mixed> The cast array.
	 */
	private function cast_to_array( $value ): array {
		// Use EtchTypeAsserter for basic array conversion
		$array = EtchTypeAsserter::to_array( $value );

		// If conversion failed and we have a string, try JSON parsing
		if ( empty( $array ) && is_string( $value ) && ! empty( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
			// Fallback to comma-separated values
			return array_map( 'trim', explode( ',', $value ) );
		}

		return $array;
	}




	/**
	 * Initialize default values.
	 *
	 * @return void
	 */
	private function initialize_defaults() {
		$this->key = '';
		$this->primitive = 'string';
		$this->default = null;
		$this->specialized = '';
	}

	/**
	 * Extract data from raw component property data.
	 *
	 * @param array<string, mixed> $rawData Raw property data.
	 * @return void
	 */
	private function extract_data( $rawData ) {
		if ( ! is_array( $rawData ) ) {
			return;
		}

		// Extract key
		$this->key = $this->extract_string_safe( $rawData, 'key', '' );

		// Extract primitive type
		$this->extract_type_data( $rawData );

		// Extract default value
		$this->default = $rawData['default'] ?? null;

		// Validate the property
		$this->validate_property();
	}

	/**
	 * Extract type data from raw data.
	 *
	 * @param array<string, mixed> $rawData Raw property data.
	 * @return void
	 */
	private function extract_type_data( $rawData ) {
		if ( ! isset( $rawData['type'] ) ) {
			return;
		}

		$typeValue = $rawData['type'];

		// Handle case where type is a string (legacy format)
		if ( is_string( $typeValue ) ) {
			$this->primitive = $typeValue;
			$this->specialized = '';
			return;
		}

		// Handle case where type is an object with primitive field
		if ( is_array( $typeValue ) ) {
			$this->primitive = $this->extract_string_safe( $typeValue, 'primitive', 'string' );
			$this->specialized = $this->extract_string_safe( $typeValue, 'specialized', '' );

			// If specialized is "array", override primitive type to "array"
			if ( 'array' === $this->specialized ) {
				$this->primitive = 'array';
			}

			return;
		}

		// Fallback to string type
		$this->primitive = 'string';
		$this->specialized = '';
	}

	/**
	 * Validate the property data.
	 *
	 * @return void
	 */
	private function validate_property() {
		// Property is valid if it has a key and valid primitive type
		$validPrimitives = array( 'string', 'number', 'boolean', 'object', 'array' );

		$this->isValid = ! empty( $this->key ) &&
						 in_array( $this->primitive, $validPrimitives, true );
	}



	/**
	 * Extract string value safely with guaranteed string return.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @param string               $default Default value (non-null).
	 * @return string Extracted string or default.
	 */
	private function extract_string_safe( $data, $key, $default = '' ) {
		if ( ! isset( $data[ $key ] ) ) {
			return $default;
		}

		return EtchTypeAsserter::to_string( $data[ $key ], $default );
	}
}
