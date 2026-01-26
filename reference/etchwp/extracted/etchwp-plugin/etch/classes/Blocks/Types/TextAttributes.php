<?php
/**
 * Text Attributes
 *
 * Attributes for the etch/text block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * TextAttributes class
 *
 * Represents the attributes structure for the etch/text block.
 * Mirrors the TypeScript GutenbergTextBlockAttributes type.
 */
class TextAttributes extends BaseAttributes {

	/**
	 * The actual text content
	 *
	 * @var string
	 */
	public string $content = '';

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

		// Set content
		if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
			$instance->content = $data['content'];
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

		$result['content'] = $this->content;

		return $result;
	}
}
