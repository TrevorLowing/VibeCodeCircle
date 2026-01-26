<?php
/**
 * Condition Attributes
 *
 * Attributes for the etch/condition block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * ConditionAttributes class
 *
 * Represents the attributes structure for the etch/condition block.
 * Mirrors the TypeScript GutenbergConditionAttributes type.
 */
class ConditionAttributes extends BaseAttributes {

	/**
	 * Parsed condition object
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $condition = null;

	/**
	 * Condition string representation
	 *
	 * @var string
	 */
	public string $conditionString = '';

	/**
	 * Create from array
	 *
	 * @param array<string, mixed> $data Attribute data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$instance = new self();

		// Set base attributes
		$base = parent::from_array( $data );
		$instance->options = $base->options;
		$instance->hidden = $base->hidden;
		$instance->script = $base->script;

		// Set condition
		if ( isset( $data['condition'] ) && is_array( $data['condition'] ) ) {
			$instance->condition = $data['condition'];
		}

		// Set conditionString
		if ( isset( $data['conditionString'] ) && is_string( $data['conditionString'] ) ) {
			$instance->conditionString = $data['conditionString'];
		}

		return $instance;
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = parent::to_array();

		if ( null !== $this->condition ) {
			$result['condition'] = $this->condition;
		}

		$result['conditionString'] = $this->conditionString;

		return $result;
	}
}
