<?php
/**
 * Gutenberg Element Attributes
 *
 * Attributes for the etch/element block.
 *
 * @package Etch\Blocks\Types
 */

namespace Etch\Blocks\Types;

/**
 * GutenbergElementAttributes class
 *
 * Represents the attributes structure for the etch/element block.
 * Mirrors the TypeScript ElementAttributes type.
 */
class ElementAttributes extends BaseAttributes {

	/**
	 * HTML tag name
	 *
	 * @var string
	 */
	public string $tag = 'div';

	/**
	 * Array of style IDs
	 *
	 * @var string[]|null
	 */
	public ?array $styles = null;

	/**
	 * HTML attributes applied to the rendered element
	 *
	 * @var array<string, string|null>
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

		// Set tag with validation
		if ( isset( $data['tag'] ) && is_string( $data['tag'] ) ) {
			$instance->tag = $instance->sanitize_tag( $data['tag'] );
		}

		// Set styles
		if ( isset( $data['styles'] ) && is_array( $data['styles'] ) ) {
			$instance->styles = $instance->sanitize_styles( $data['styles'] );
		}

		if ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$instance->attributes = $instance->sanitize_attributes( $data['attributes'] );
		}

		return $instance;
	}

	/**
	 * Sanitize and validate tag name
	 *
	 * Only validates the format of the tag name, not which tags are allowed.
	 * This supports standard HTML tags AND custom elements/web components.
	 *
	 * Valid format:
	 * - Must start with a letter
	 * - Can contain letters, numbers, and hyphens
	 * - No special characters or spaces
	 *
	 * @param string $tag Tag name to sanitize.
	 * @return string Sanitized tag name, or 'div' if invalid.
	 */
	private function sanitize_tag( string $tag ): string {
		$tag = strtolower( trim( $tag ) );

		// Validate tag format: must start with letter, can contain letters, numbers, hyphens
		// This supports both standard tags (div, section) and custom elements (my-component, custom-tag)
		if ( ! preg_match( '/^[a-z][a-z0-9\-]*$/', $tag ) ) {
			return 'div';
		}

		return $tag;
	}

	/**
	 * Check if tag name has valid format
	 *
	 * @param string $tag Tag to check.
	 * @return bool
	 */
	public static function is_valid_tag_format( string $tag ): bool {
		$tag = strtolower( trim( $tag ) );
		return (bool) preg_match( '/^[a-z][a-z0-9\-]*$/', $tag );
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = parent::to_array();

		$result['tag'] = $this->tag;

		if ( null !== $this->styles ) {
			$result['styles'] = $this->styles;
		}

		if ( ! empty( $this->attributes ) ) {
			$result['attributes'] = $this->attributes;
		}

		return $result;
	}

	/**
	 * Sanitize styles array
	 *
	 * @param array<mixed> $styles Raw styles array.
	 * @return string[]
	 */
	private function sanitize_styles( array $styles ): array {
		$sanitized = array();

		foreach ( $styles as $style ) {
			if ( is_string( $style ) && '' !== trim( $style ) ) {
				$sanitized[] = $style;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize HTML attributes array
	 *
	 * @param array<mixed> $attributes Raw attributes.
	 * @return array<string, string|null>
	 */
	private function sanitize_attributes( array $attributes ): array {
		$sanitized = array();

		foreach ( $attributes as $name => $value ) {
			if ( ! is_string( $name ) ) {
				continue;
			}

			$attribute_name = $this->sanitize_attribute_name( $name );
			if ( null === $attribute_name ) {
				continue;
			}

			// Allow null/undefined values as per TypeScript type
			if ( null === $value || '' === $value ) {
				$sanitized[ $attribute_name ] = null;
				continue;
			}

			$normalized_value = $this->normalize_attribute_value( $value );
			if ( null === $normalized_value ) {
				$sanitized[ $attribute_name ] = null;
				continue;
			}

			$sanitized[ $attribute_name ] = $normalized_value;
		}

		return $sanitized;
	}

	/**
	 * Normalize attribute value to a string representation.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return string|null Normalized string or null when unsupported.
	 */
	private function normalize_attribute_value( $value ): ?string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			$lower   = strtolower( $trimmed );

			if ( 'true' === $lower || 'false' === $lower ) {
				return $lower;
			}

			if ( '0' === $trimmed || '1' === $trimmed ) {
				return $trimmed;
			}

			return $trimmed;
		}

		return null;
	}

	/**
	 * Validate and normalize attribute name
	 *
	 * @param string $name Attribute name.
	 * @return string|null Normalized attribute name or null when invalid.
	 */
	private function sanitize_attribute_name( string $name ): ?string {
		$name = trim( $name );

		if ( '' === $name ) {
			return null;
		}

		if ( ! preg_match( '/^[a-zA-Z_:][a-zA-Z0-9_:\-]*$/', $name ) ) {
			return null;
		}

		return $name;
	}
}
