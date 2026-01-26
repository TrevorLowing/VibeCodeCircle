<?php
/**
 * Condition Evaluator Helper
 *
 * Utility methods for evaluating conditions, inspired by the old Preprocessor ConditionBlock.
 * Handles operand evaluation, operator application, and value normalization.
 *
 * @package Etch\Blocks\ConditionBlock
 */

namespace Etch\Blocks\ConditionBlock;

use Etch\Blocks\Global\Utilities\DynamicContentProcessor;

/**
 * ConditionEvaluator class
 *
 * Provides static utility methods for condition evaluation logic.
 * Adapted from Etch\Preprocessor\Blocks\ConditionBlock.
 */
class ConditionEvaluator {

	/**
	 * Evaluate an operand which can be a scalar value or nested condition.
	 *
	 * @param mixed                                              $operand The operand to evaluate.
	 * @param array<int, array{key: string, source: mixed}>|null $sources Dynamic sources for expression resolution.
	 * @return mixed The evaluated value.
	 */
	public static function evaluate_operand( $operand, ?array $sources ) {
		// If it's a nested condition, evaluate it recursively
		if ( is_array( $operand ) && isset( $operand['operator'] ) ) {
			return self::evaluate_condition( $operand, $sources ?? array() );
		}

		// If it's a string, process dynamic placeholders and preserve types
		if ( is_string( $operand ) ) {
			// Normalize/Decode value first (e.g., remove encoded quotes) before evaluation
			$normalized_value = self::decode_value( $operand );

			return DynamicContentProcessor::process_expression(
				$normalized_value,
				array(
					'sources' => $sources ?? array(),
				)
			);
		}

		return $operand;
	}

	/**
	 * Evaluate a condition recursively.
	 *
	 * @param array<string, mixed>                               $condition The condition to evaluate.
	 * @param array<int, array{key: string, source: mixed}>|null $sources   Dynamic sources for expression resolution.
	 * @return bool True if condition is met, false otherwise.
	 */
	public static function evaluate_condition( array $condition, ?array $sources ): bool {
		if ( ! isset( $condition['operator'] ) || ! is_string( $condition['operator'] ) ) {
			return false;
		}

		$operator = self::decode_operator( $condition['operator'] );

		$left_value = null;
		if ( isset( $condition['leftHand'] ) ) {
			$left_value = self::evaluate_operand( $condition['leftHand'], $sources ?? array() );
		}

		// Handle truthiness operators
		if ( 'isTruthy' === $operator ) {
			return self::is_truthy( $left_value );
		}

		if ( 'isFalsy' === $operator ) {
			return ! self::is_truthy( $left_value );
		}

		// For all other operators, evaluate right operand
		$right_value = null;
		if ( isset( $condition['rightHand'] ) ) {
			$right_value = self::evaluate_operand( $condition['rightHand'], $sources ?? array() );
		}

		// Apply the operator
		return self::apply_operator( $left_value, $operator, $right_value );
	}

	/**
	 * Apply an operator to two values.
	 *
	 * @param mixed  $left Left operand value.
	 * @param string $operator The operator.
	 * @param mixed  $right Right operand value.
	 * @return bool The result of the operation.
	 */
	private static function apply_operator( $left, string $operator, $right ): bool {
		switch ( $operator ) {
			case '==':
				return $left == $right; // Loose comparison

			case '===':
				return $left === $right;

			case '!=':
				return $left != $right; // Loose comparison

			case '!==':
				return $left !== $right;

			case '>':
				return $left > $right;

			case '<':
				return $left < $right;

			case '>=':
				return $left >= $right;

			case '<=':
				return $left <= $right;

			case '&&':
				return self::is_truthy( $left ) && self::is_truthy( $right );

			case '||':
				return self::is_truthy( $left ) || self::is_truthy( $right );

			default:
				// Unknown operator, default to false
				return false;
		}
	}

	/**
	 * Check if a value is truthy.
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if value is truthy.
	 */
	private static function is_truthy( $value ): bool {
		// Empty values are falsy
		if ( empty( $value ) ) {
			return false;
		}

		// String "false" is explicitly falsy
		if ( is_string( $value ) && 'false' === strtolower( $value ) ) {
			return false;
		}

		// Everything else follows PHP's truthiness rules
		return (bool) $value;
	}

	/**
	 * Decode Unicode-encoded operators.
	 *
	 * @param string $operator The encoded operator string.
	 * @return string The decoded operator.
	 */
	private static function decode_operator( string $operator ): string {
		return strtr(
			$operator,
			array(
				'u0026u0026' => '&&',
				'u007cu007c' => '||',
				'u003e'      => '>',
				'u003c'      => '<',
				'u003d'      => '=',
				'u0021'      => '!',
			)
		);
	}

	/**
	 * Decode Unicode characters and normalize values.
	 *
	 * @param string $value The value to decode.
	 * @return string The decoded value.
	 */
	private static function decode_value( string $value ): string {
		// Remove escaped Unicode quotes
		$value = str_replace( 'u0022', '', $value );

		// WordPress converts single quotes to apostrophes in post titles
		// Normalize for proper comparison
		$value = str_replace( '&#8217;', "'", $value );

		return $value;
	}
}
