<?php
/**
 * Slot Placeholder Attributes
 *
 * Attributes for the etch/slot-placeholder block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * SlotPlaceholderAttributes class
 *
 * Represents the attributes structure for the etch/slot-placeholder block.
 * Mirrors the TypeScript GutenbergSlotPlaceholderAttributes type.
 */
class SlotPlaceholderAttributes extends BaseAttributes {

	/**
	 * Name of the slot
	 *
	 * @var string
	 */
	public string $name = '';

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

		// Set name
		if ( isset( $data['name'] ) && is_string( $data['name'] ) ) {
			$instance->name = $data['name'];
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

		$result['name'] = $this->name;

		return $result;
	}
}
