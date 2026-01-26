<?php
/**
 * Loop Attributes
 *
 * Attributes for the etch/loop block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * LoopAttributes class
 *
 * Represents the attributes structure for the etch/loop block.
 * Mirrors the TypeScript GutenbergLoopAttributes type.
 */
class LoopAttributes extends BaseAttributes {

	/**
	 * Target expression/path (e.g. "my-loop.items", "this.acf.repeater")
	 *
	 * @var string|null
	 */
	public ?string $target = null;

	/**
	 * Context key to expose current loop item under
	 *
	 * @var string|null
	 */
	public ?string $itemId = null;

	/**
	 * Context key to expose current loop index under
	 *
	 * @var string|null
	 */
	public ?string $indexId = null;

	/**
	 * Explicit loop preset ID (overrides detection from target when provided)
	 *
	 * @var string|null
	 */
	public ?string $loopId = null;

	/**
	 * Loop parameters (can include dynamic expressions as strings)
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $loopParams = null;

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

		if ( isset( $data['target'] ) && is_string( $data['target'] ) && '' !== trim( $data['target'] ) ) {
			$instance->target = $data['target'];
		}

		if ( isset( $data['itemId'] ) && is_string( $data['itemId'] ) && '' !== trim( $data['itemId'] ) ) {
			$instance->itemId = $data['itemId'];
		}

		if ( isset( $data['indexId'] ) && is_string( $data['indexId'] ) && '' !== trim( $data['indexId'] ) ) {
			$instance->indexId = $data['indexId'];
		}

		if ( isset( $data['loopId'] ) && is_string( $data['loopId'] ) && '' !== trim( $data['loopId'] ) ) {
			$instance->loopId = $data['loopId'];
		}

		if ( isset( $data['loopParams'] ) && is_array( $data['loopParams'] ) ) {
			$instance->loopParams = $data['loopParams'];
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

		if ( null !== $this->target ) {
			$result['target'] = $this->target;
		}

		if ( null !== $this->itemId ) {
			$result['itemId'] = $this->itemId;
		}

		if ( null !== $this->indexId ) {
			$result['indexId'] = $this->indexId;
		}

		if ( null !== $this->loopId ) {
			$result['loopId'] = $this->loopId;
		}

		if ( null !== $this->loopParams ) {
			$result['loopParams'] = $this->loopParams;
		}

		return $result;
	}
}
