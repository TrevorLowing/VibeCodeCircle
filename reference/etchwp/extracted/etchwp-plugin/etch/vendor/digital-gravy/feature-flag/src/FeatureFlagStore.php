<?php
/**
 * Feature Flag Store
 *
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag;

use DigitalGravy\FeatureFlag\Exception\Not_A_Flag;

class FeatureFlagStore {

	/** @var bool */
	private bool $is_empty = true;

	/** @var array<FeatureFlag> */
	private array $flags = array();

	/**
	 * @param array<string, FeatureFlag>|array<array<string, FeatureFlag>> ...$sources The sources for the flags.
	 * @throws Exception\Not_A_Flag If there are invalid flags.
	 */
	public function __construct( array ...$sources ) {
		$this->flags = self::merge_sources( ...$sources );
		$this->is_empty = empty( $this->flags );
	}

	/**
	 * @param array<string, FeatureFlag>|array<array<string, FeatureFlag>> ...$sources The sources to merge.
	 * @return array<string, FeatureFlag>
	 * @throws Exception\Not_A_Flag If there are invalid flags.
	 */
	private static function merge_sources( array ...$sources ): array {
		$merged = array();
		foreach ( $sources as $source ) {
			foreach ( $source as $flag ) {
				/** @var mixed $flag */
				if ( ! $flag instanceof FeatureFlag ) {
					throw new Exception\Not_A_Flag();
				}
				$merged[ $flag->key ] = $flag;
			}
		}
		return $merged;
	}

	public function is_empty(): bool {
		return $this->is_empty;
	}

	public function is_on( string $flag_key ): bool {
		$flag_key = FeatureFlag::sanitize_key( $flag_key );
		if ( ! isset( $this->flags[ $flag_key ] ) ) {
			throw new Exception\Flag_Key_Not_Found( $flag_key ); // @codingStandardsIgnoreLine
		}
		return 'on' === $this->flags[ $flag_key ]->value;
	}

	/**
	 * @return array<string, string>
	 */
	public function get_flags(): array {
		$flags = array();
		foreach ( $this->flags as $flag ) {
			$flags[ $flag->key ] = $flag->value;
		}
		return $flags;
	}
}
