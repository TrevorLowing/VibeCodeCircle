<?php
/**
 * EtchDataCondition class for Etch plugin
 *
 * This file contains the EtchDataCondition class which handles
 * condition data structures and validation.
 *
 * @package Etch
 */

namespace Etch\Preprocessor\Data;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchDataCondition class for handling condition data.
 */
class EtchDataCondition {

	/**
	 * Left-hand operand of the condition.
	 *
	 * @var EtchDataConditionOperand
	 */
	public $leftHand;

	/**
	 * Condition operator.
	 *
	 * @var string
	 */
	public $operator;

	/**
	 * Right-hand operand of the condition.
	 *
	 * @var EtchDataConditionOperand|null
	 */
	public $rightHand;

	/**
	 * Valid condition operators.
	 */
	private const VALID_OPERATORS = array(
		'==',
		'===',
		'!=',
		'!==',
		'<=',
		'>=',
		'<',
		'>',
		'||',
		'&&',
		'isTruthy',
		'isFalsy',
	);

	/**
	 * Constructor.
	 *
	 * @param EtchDataConditionOperand      $leftHand Left-hand operand.
	 * @param string                        $operator Condition operator.
	 * @param EtchDataConditionOperand|null $rightHand Right-hand operand.
	 */
	public function __construct( $leftHand, $operator, $rightHand ) {
		$this->leftHand = $leftHand;
		$this->operator = $operator;
		$this->rightHand = $rightHand;
	}

	/**
	 * Create EtchDataCondition from array data.
	 *
	 * @param array<string, mixed> $data Condition data array.
	 * @return self|null EtchDataCondition instance or null if invalid.
	 */
	public static function from_array( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		// Extract operator (required)
		$operator = self::extract_string( $data, 'operator' );
		if ( ! self::is_valid_operator( $operator ) ) {
			return null;
		}

		/**
		 * String $operator
		 *
		 * @var string $operator
		 */
		if ( ! isset( $data['leftHand'] ) ) {
			return null;
		}
		$leftHand = EtchDataConditionOperand::from_data( $data['leftHand'] );

		// Extract rightHand (can be null for isTruthy/isFalsy)
		$rightHand = isset( $data['rightHand'] )
			? EtchDataConditionOperand::from_data( $data['rightHand'] )
			: null;

		return new self( $leftHand, $operator, $rightHand );
	}

	/**
	 * Check if operator is valid.
	 *
	 * @param mixed $operator Operator to validate.
	 * @return bool True if valid operator.
	 */
	private static function is_valid_operator( $operator ) {
		return is_string( $operator ) && in_array( $operator, self::VALID_OPERATORS, true );
	}

	/**
	 * Extract string value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return string|null Extracted string or null.
	 */
	private static function extract_string( $data, $key ) {
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return EtchTypeAsserter::to_string_or_null( $data[ $key ] );
	}
}
