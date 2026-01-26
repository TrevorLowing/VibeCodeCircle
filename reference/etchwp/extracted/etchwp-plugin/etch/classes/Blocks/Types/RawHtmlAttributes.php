<?php
/**
 * Raw Html Attributes
 *
 * Attributes for the etch/raw-html block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * Raw Html Attributes class
 *
 * Represents the attributes structure for the etch/text block.
 * Mirrors the TypeScript GutenbergRawHtmlBlockAttributes type.
 */
class RawHtmlAttributes extends BaseAttributes {

	/**
	 * The actual html source
	 *
	 * @var string
	 */
	public string $content = '';

	/**
	 * Unsafe flag
	 *
	 * @var string
	 */
	public string $unsafe = '';

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

		if ( isset( $data['unsafe'] ) && is_string( $data['unsafe'] ) ) {
			$instance->unsafe = $data['unsafe'];
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
		$result['unsafe'] = $this->unsafe;

		return $result;
	}
}
