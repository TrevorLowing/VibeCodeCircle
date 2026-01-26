<?php
/**
 * TextAreaField.php
 *
 * This file defines the TextAreaField class, which represents a custom field of type 'textarea'.
 *
 * @package Etch\CustomFields\Fields
 */

namespace Etch\CustomFields\Fields;

/**
 * TextAreaField class.
 *
 * This class represents a custom field of type 'textarea'.
 *
 * @phpstan-import-type CustomField from \Etch\CustomFields\CustomFieldTypes
 */
class TextAreaField extends BaseField {
	/**
	 * The field value.
	 *
	 * @var string
	 */
	public $value = '';

	/**
	 * Constructor.
	 *
	 * @param CustomField $field The custom field.
	 * @param mixed       $val The field value.
	 */
	protected function __construct( $field, $val ) {
		parent::__construct( $field );

		$this->type = 'textarea';
		$this->value = $this->sanitize_value( $val );
	}

	/**
	 * Render the field.
	 *
	 * @return void
	 */
	public function render() {
		$this->render_label();
		echo '<textarea id="' . esc_attr( $this->key ) . '" name="' . esc_attr( $this->key ) . '" class="etch-cf-textarea" rows="4" ' . esc_attr( $this->required ? 'required' : '' ) . '>' . esc_textarea( $this->value ) . '</textarea>';
	}

	/**
	 * Sanitizes the value from the post meta.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The field value.
	 */
	public function sanitize_value( $value ): string {
		return is_string( $value ) ? $value : '';
	}
}
