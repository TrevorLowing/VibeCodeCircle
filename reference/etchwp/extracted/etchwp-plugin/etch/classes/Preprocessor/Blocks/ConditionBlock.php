<?php
/**
 * ConditionBlock Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Blocks;

use Etch\Preprocessor\Data\EtchData;
use Etch\Preprocessor\Data\EtchDataCondition;
use Etch\Preprocessor\Data\EtchDataConditionOperand;
use Etch\Preprocessor\Utilities\EtchParser;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * ConditionBlock class for processing Condition blocks with EtchData.
 */
class ConditionBlock extends BaseBlock {

	/**
	 * The condition data.
	 *
	 * @var EtchDataCondition|null
	 */
	private ?EtchDataCondition $condition;

	/**
	 * Constructor for the ConditionBlock class.
	 *
	 * @param WpBlock                   $block WordPress block data.
	 * @param EtchData                  $data Etch data instance.
	 * @param array<string, mixed>|null $context Parent context to inherit.
	 * @param BaseBlock|null            $parent The parent block.
	 */
	public function __construct( WpBlock $block, EtchData $data, $context = null, $parent = null ) {
		parent::__construct( $block, $data, $context, $parent );

		// Extract condition data from EtchData
		$this->condition = $data->condition;
	}

	/**
	 * Process the condition block and return the transformed block data.
	 *
	 * @return array<int, array<string, mixed>>|null Array of transformed blocks or null if condition is false.
	 */
	public function process(): ?array {
		// If no condition data, return inner blocks as-is
		if ( null === $this->condition ) {
			return $this->get_inner_blocks_as_raw_blocks();
		}

		// Evaluate the condition
		if ( ! $this->evaluate_condition( $this->condition ) ) {
			return null;
		}

		$inner_blocks = $this->get_inner_blocks();

		if ( empty( $inner_blocks ) ) {
			return null;
		}

		return $this->process_inner_blocks_to_raw_blocks( $inner_blocks );
	}

	/**
	 * Evaluate a condition recursively.
	 *
	 * @param EtchDataCondition $condition The condition to evaluate.
	 * @return bool True if condition is met, false otherwise.
	 */
	private function evaluate_condition( EtchDataCondition $condition ): bool {
		$operator = $this->decode_operator( $condition->operator );

		$left_value = $this->evaluate_operand( $condition->leftHand );

		// Handle truthiness operators
		if ( 'isTruthy' === $operator ) {
			return $this->is_truthy( $left_value );
		}

		if ( 'isFalsy' === $operator ) {
			return ! $this->is_truthy( $left_value );
		}

		// For all other operators, evaluate right operand
		$right_value = null !== $condition->rightHand
			? $this->evaluate_operand( $condition->rightHand )
			: null;

		// Apply the operator
		return $this->apply_operator( $left_value, $operator, $right_value );
	}

	/**
	 * Evaluate an operand which can be a scalar value or nested condition.
	 *
	 * @param EtchDataConditionOperand $operand The operand to evaluate.
	 * @return mixed The evaluated value.
	 */
	private function evaluate_operand( EtchDataConditionOperand $operand ) {
		// If it's a nested condition, evaluate it recursively
		if ( $operand->is_condition() ) {
			$nested_condition = $operand->as_condition();
			return null !== $nested_condition
				? $this->evaluate_condition( $nested_condition )
				: null;
		}

		// Get the scalar value
		$value = $operand->as_scalar();

		// If it's a string, process dynamic placeholders and preserve types
		if ( is_string( $value ) ) {
			// Normalize/Decode value first (e.g., remove encoded quotes) before evaluation
			$normalized_value = $this->decode_value( $value );

			// Use process_expression to resolve expressions and preserve their native types
			$processed_value = EtchParser::process_expression( $normalized_value, $this->get_context() );

			// Return the processed value with its original type preserved
			return $processed_value;
		}

		return $value;
	}

	/**
	 * Apply an operator to two values.
	 *
	 * @param mixed  $left Left operand value.
	 * @param string $operator The operator.
	 * @param mixed  $right Right operand value.
	 * @return bool The result of the operation.
	 */
	private function apply_operator( $left, string $operator, $right ): bool {
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
				return $this->is_truthy( $left ) && $this->is_truthy( $right );

			case '||':
				return $this->is_truthy( $left ) || $this->is_truthy( $right );

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
	private function is_truthy( $value ): bool {
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
	private function decode_operator( string $operator ): string {
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
	private function decode_value( string $value ): string {
		// Remove escaped Unicode quotes
		$value = str_replace( 'u0022', '', $value );

		// WordPress converts single quotes to apostrophes in post titles
		// Normalize for proper comparison
		$value = str_replace( '&#8217;', "'", $value );

		return $value;
	}
}
