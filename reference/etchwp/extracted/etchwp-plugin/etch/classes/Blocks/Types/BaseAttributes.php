<?php
/**
 *  Base Attributes
 *
 * Represents the base attributes shared by all custom Gutenberg blocks.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * GutenbergBaseAttributes class
 *
 * Represents the base attributes structure for custom Gutenberg blocks.
 * Mirrors the TypeScript GutenbergBaseAttributes type.
 */
class BaseAttributes {

	/**
	 * Arbitrary options that can be used by specific blocks
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $options = null;

	/**
	 * Whether the block is hidden
	 *
	 * @var bool|null
	 */
	public ?bool $hidden = null;

	/**
	 * Script configuration
	 *
	 * @var array{id: string, code: string}|null
	 */
	public ?array $script = null;

	/**
	 * Create from array
	 *
	 * @param array<string, mixed> $data Attribute data.
	 * @return static
	 */
	public static function from_array( array $data ): self {
		$class = static::class;
		/**
		 * Instance of the called class via late static binding.
		 *
		 * @var static $instance
		 */
		$instance = new $class();

		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$instance->options = $data['options'];
		}

		if ( isset( $data['hidden'] ) ) {
			$instance->hidden = EtchTypeAsserter::to_bool( $data['hidden'] );
		}

		if ( isset( $data['script'] ) && is_array( $data['script'] ) && isset( $data['script']['id'] ) && is_string( $data['script']['id'] ) && isset( $data['script']['code'] ) && is_string( $data['script']['code'] ) ) {
			$instance->script = $data['script'];
		}

		return $instance;
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = array();

		if ( null !== $this->options ) {
			$result['options'] = $this->options;
		}

		if ( null !== $this->hidden ) {
			$result['hidden'] = $this->hidden;
		}

		if ( null !== $this->script ) {
			$result['script'] = $this->script;
		}

		return $result;
	}
}
