<?php
/**
 * Component Attributes
 *
 * Attributes for the etch/component block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * ComponentAttributes class
 *
 * Represents the attributes structure for the etch/component block.
 * Mirrors the TypeScript GutenbergComponentAttributes type.
 */
class ComponentAttributes extends BaseAttributes {

	/**
	 * Reference to the component ID (id of the pattern)
	 *
	 * @var int|null
	 */
	public ?int $ref = null;

	/**
	 * Component attributes/properties
	 *
	 * @var array<string, mixed>
	 */
	public array $attributes = array();

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

		// Set ref
		if ( isset( $data['ref'] ) ) {
			if ( is_int( $data['ref'] ) ) {
				$instance->ref = $data['ref'];
			} elseif ( is_numeric( $data['ref'] ) ) {
				$instance->ref = (int) $data['ref'];
			}
		}

		// Set attributes (preserve types, don't force to string)
		if ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$instance->attributes = $data['attributes'];
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

		if ( null !== $this->ref ) {
			$result['ref'] = $this->ref;
		}

		if ( ! empty( $this->attributes ) ) {
			$result['attributes'] = $this->attributes;
		}

		return $result;
	}
}
