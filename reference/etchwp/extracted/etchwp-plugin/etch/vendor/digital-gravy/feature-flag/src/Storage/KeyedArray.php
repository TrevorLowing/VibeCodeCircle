<?php
/**
 * A KeyedArray is an array that is indexed by a key.
 *
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag\Storage;

use DigitalGravy\FeatureFlag\FeatureFlag;

class KeyedArray implements FlagStorageInterface {

	/**
	 * @var array<string,string>
	 */
	private array $flags_dirty;

	/**
	 * @param array<string,string> $flags Array of flags where keys are flag keys and values are flag states.
	 */
	public function __construct( array $flags ) {
		$this->flags_dirty = $flags;
	}

	public function get_flags(): array {
		$clean_flags = array();
		foreach ( $this->flags_dirty as $key => $value ) {
			try {
				$flag = new FeatureFlag( $key, $value );
				$clean_flags[ $flag->key ] = $flag;
			} catch ( \Exception $e ) {
				continue;
			}
		}
		return $clean_flags;
	}
}
