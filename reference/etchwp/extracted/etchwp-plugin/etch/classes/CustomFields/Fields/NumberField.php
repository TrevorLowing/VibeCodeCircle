<?php
/**
 * NumberField.php
 *
 * This file defines the NumberField class, which represents a custom field of type 'number'.
 *
 * @package Etch\CustomFields\Fields
 */

namespace Etch\CustomFields\Fields;

/**
 * Class NumberField
 *
 * Represents a custom field of type 'number'.
 *
 * @phpstan-import-type CustomField from \Etch\CustomFields\CustomFieldTypes
 */
class NumberField extends BaseField {
	/**
	 * The field value.
	 *
	 * @var float|null
	 */
	public ?float $value = null;

	/**
	 * Constructor.
	 *
	 * @param CustomField $field The custom field.
	 * @param mixed       $val The field value.
	 */
	protected function __construct( $field, $val ) {
		parent::__construct( $field );

		$this->type = 'number';
		$this->value = $this->sanitize_value( $val );
	}

	/**
	 * Render the field.
	 *
	 * @return void
	 */
	public function render() {
		$this->render_label();
		echo '<input type="number" id="' . esc_attr( $this->key ) . '" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( (string) $this->value ) . '" class="etch-cf-number" ' . ( $this->required ? 'required' : '' ) . ' />';
	}

	/**
	 * Sanitizes the value from the post meta.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return float|null The field value.
	 */
	public function sanitize_value( $value ): float|null {
		return is_numeric( $value ) ? (float) $value : null;
	}
}
