<?php
/**
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag\Storage;

use DigitalGravy\FeatureFlag\FeatureFlag;

class PHPConstant implements FlagStorageInterface {

	/**
	 * @var array<string>
	 */
	private array $constant_names;

	/**
	 * @param array<string> $constant_names List of constant names to load.
	 */
	public function __construct( array $constant_names ) {
		$this->constant_names = $constant_names;
	}

	public function get_flags(): array {
		$flags_clean = array();
		foreach ( $this->constant_names as $constant_name ) {
			if ( ! defined( $constant_name ) ) {
				continue;
			}
			$constant_value = constant( $constant_name );
			$flag_value = is_bool( $constant_value ) ? ( $constant_value ? 'on' : 'off' ) : $constant_value;
			if ( ! is_string( $flag_value ) ) {
				continue;
			}
			try {
				$flag = new FeatureFlag( $constant_name, $flag_value );
				$flags_clean[ $flag->key ] = $flag;
			} catch ( \Exception $e ) {
				continue;
			}
		}
		return $flags_clean;
	}
}
