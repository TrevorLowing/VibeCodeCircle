<?php
/**
 * Feature Flag
 *
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag;

/**
 * @property-read string $key The flag key.
 * @property-read string $value The flag value.
 */
class FeatureFlag {

	private string $key;
	private string $value;

	public function __construct( string $key, string $value ) {
		self::validate( $key, $value );
		$this->key = self::sanitize_key( $key );
		$this->value = $value;
	}

	/**
	 * @param string $name The property name.
	 * @return string|null
	 */
	public function __get( string $name ): string|null {
		if ( ! in_array( $name, array( 'key', 'value' ), true ) ) {
			return null;
		}
		return $this->{$name};
	}

	/**
	 * @param string $key The flag key.
	 * @param string $value The flag value.
	 * @throws Exception\Invalid_Flag_Key If the key is invalid.
	 * @throws Exception\Invalid_Flag_Value If the value is invalid.
	 */
	public static function validate( string $key, string $value ): bool {
		if ( ! self::is_valid_key( $key ) ) {
			throw new Exception\Invalid_Flag_Key( $key ); // @codingStandardsIgnoreLine
		}
		if ( ! self::is_valid_value( $value ) ) {
			throw new Exception\Invalid_Flag_Value( $key, $value ); // @codingStandardsIgnoreLine
		}
		return true;
	}

	public static function is_valid( string $key, mixed $value ): bool {
		return self::is_valid_key( $key ) && self::is_valid_value( $value );
	}

	public static function is_valid_key( string $key ): bool {
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) === 1;
	}

	public static function is_valid_value( mixed $value ): bool {
		return in_array( $value, array( 'on', 'off' ), true );
	}

	public static function sanitize_key( string $key ): string {
		return strtolower( $key );
	}
}
