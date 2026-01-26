<?php
/**
 * Component Property
 *
 * Represents a component property definition, mirroring the TypeScript EtchComponentProperty type.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * ComponentProperty class
 *
 * Represents a component property with type information.
 * Mirrors the TypeScript EtchComponentProperty type structure.
 */
class ComponentProperty {

	/**
	 * Property name (display name)
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Property key (identifier)
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * Whether the key has been touched/modified
	 *
	 * @var bool|null
	 */
	public ?bool $keyTouched = null;

	/**
	 * Property type information
	 *
	 * @var array{primitive: string, specialized?: string}
	 */
	public array $type;

	/**
	 * Default value
	 *
	 * @var mixed
	 */
	public $default = null;

	/**
	 * Options array (for select/string properties)
	 *
	 * @var array<string|number>|null
	 */
	public ?array $options = null;

	/**
	 * Select options as string (for string properties with select specialized type)
	 *
	 * @var string|null
	 */
	public ?string $selectOptionsString = null;

	/**
	 * Create ComponentProperty from array data
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self|null ComponentProperty instance or null if invalid.
	 */
	public static function from_array( array $data ): ?self {
		if ( ! is_array( $data ) || ! isset( $data['key'] ) ) {
			return null;
		}

		$instance = new self();

		// Extract name (use name if exists, otherwise use key)
		if ( isset( $data['name'] ) ) {
			$instance->name = EtchTypeAsserter::to_string( $data['name'] );
		} else {
			$instance->name = EtchTypeAsserter::to_string( $data['key'] );
		}

		// Extract key (we know it exists because of the isset check above)
		$instance->key = EtchTypeAsserter::to_string( $data['key'] );

		// Extract keyTouched
		if ( isset( $data['keyTouched'] ) ) {
			$instance->keyTouched = EtchTypeAsserter::to_bool( $data['keyTouched'] );
		}

		// Extract type
		$type_data = $data['type'] ?? array();
		if ( is_string( $type_data ) ) {
			// Legacy format: type is a string
			$instance->type = array(
				'primitive' => $type_data,
				'specialized' => '',
			);
		} elseif ( is_array( $type_data ) ) {
			$instance->type = array(
				'primitive' => EtchTypeAsserter::to_string( $type_data['primitive'] ?? 'string' ),
				'specialized' => isset( $type_data['specialized'] ) ? EtchTypeAsserter::to_string( $type_data['specialized'] ) : '',
			);
		} else {
			$instance->type = array(
				'primitive' => 'string',
				'specialized' => '',
			);
		}

		// Extract default value
		if ( array_key_exists( 'default', $data ) ) {
			$instance->default = $data['default'];
		}

		// Extract options
		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$instance->options = $data['options'];
		}

		// Extract selectOptionsString
		if ( isset( $data['selectOptionsString'] ) && is_string( $data['selectOptionsString'] ) ) {
			$instance->selectOptionsString = $data['selectOptionsString'];
		}

		// Validate property
		if ( empty( $instance->key ) ) {
			return null;
		}

		$valid_primitives = array( 'string', 'number', 'boolean', 'object', 'array' );
		if ( ! in_array( $instance->type['primitive'], $valid_primitives, true ) ) {
			return null;
		}

		return $instance;
	}

	/**
	 * Get the primitive type
	 *
	 * @return string
	 */
	public function get_primitive(): string {
		return $this->type['primitive'] ?? 'string';
	}

	/**
	 * Get the specialized type
	 *
	 * @return string
	 */
	public function get_specialized(): string {
		return $this->type['specialized'] ?? '';
	}

	/**
	 * Check if this is a specialized array type (loop prop)
	 *
	 * @return bool
	 */
	public function is_specialized_array(): bool {
		return 'array' === $this->get_specialized();
	}

	/**
	 * Check if this is a string property with a specialized type
	 *
	 * @return bool
	 */
	public function is_specialized_string(): bool {
		return 'string' === $this->get_primitive() && ! empty( $this->get_specialized() );
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = array(
			'name' => $this->name,
			'key' => $this->key,
			'type' => $this->type,
		);

		if ( null !== $this->keyTouched ) {
			$result['keyTouched'] = $this->keyTouched;
		}

		if ( null !== $this->default ) {
			$result['default'] = $this->default;
		}

		if ( null !== $this->options ) {
			$result['options'] = $this->options;
		}

		if ( null !== $this->selectOptionsString ) {
			$result['selectOptionsString'] = $this->selectOptionsString;
		}

		return $result;
	}
}
