<?php
/**
 * EtchDataConditionOperand class for Etch plugin
 *
 * This file contains the EtchDataConditionOperand class which handles
 * condition operands that can be either scalar values or nested conditions.
 *
 * @package Etch
 */

namespace Etch\Preprocessor\Data;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchDataConditionOperand class for handling condition operands.
 *
 * Represents a union type that can be:
 * - EtchDataCondition (nested condition)
 * - string
 * - bool
 * - int
 * - float
 */
class EtchDataConditionOperand {

	/**
	 * The operand value.
	 *
	 * @var EtchDataCondition|string|bool|int|float|mixed
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param EtchDataCondition|string|bool|int|float|mixed $value The operand value.
	 */
	public function __construct( $value ) {
		$this->value = $value;
	}

	/**
	 * Create EtchDataConditionOperand from raw data.
	 *
	 * @param mixed $data Raw operand data.
	 * @return self EtchDataConditionOperand instance.
	 */
	public static function from_data( $data ) {
		// If it's an array with operator, try to create nested condition
		if ( is_array( $data ) && isset( $data['operator'] ) ) {
			$nestedCondition = EtchDataCondition::from_array( $data );
			return new self( $nestedCondition ?? $data );
		}

		// For scalar values or any other type, store as-is
		return new self( $data );
	}

	/**
	 * Check if operand is a nested condition.
	 *
	 * @return bool True if operand is an EtchDataCondition.
	 */
	public function is_condition() {
		return $this->value instanceof EtchDataCondition;
	}

	/**
	 * Get as condition (if it is one).
	 *
	 * @return EtchDataCondition|null The condition or null if not a condition.
	 */
	public function as_condition() {
		if ( $this->is_condition() ) {
			/**
			 * EtchDataCondition $condition
			 *
			 * @var EtchDataCondition $condition
			 */
			$condition = $this->value;
			return $condition;
		}
		return null;
	}

	/**
	 * Get as scalar value.
	 *
	 * @return string|bool|int|float|null The scalar value or null if not scalar.
	 */
	public function as_scalar() {
		if ( is_scalar( $this->value ) ) {
			/**
			 * Scalar value
			 *
			 * @var string|bool|int|float $scalar
			 */
			$scalar = $this->value;
			return $scalar;
		}
		return null;
	}
}
