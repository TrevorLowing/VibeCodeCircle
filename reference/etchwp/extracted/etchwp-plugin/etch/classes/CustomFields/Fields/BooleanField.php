<?php
/**
 * BooleanField.php
 *
 * This file defines the BooleanField class, which represents a boolean custom field in the Etch plugin.
 *
 * @package Etch\CustomFields\Fields
 */

namespace Etch\CustomFields\Fields;

/**
 * Class BooleanField
 *
 * Represents a boolean custom field.
 *
 * @phpstan-import-type CustomField from \Etch\CustomFields\CustomFieldTypes
 */
class BooleanField extends BaseField {
	/**
	 * The field value.
	 *
	 * @var bool
	 */
	public bool $value = false;

	/**
	 * Constructor.
	 *
	 * @param CustomField $field The custom field.
	 * @param mixed       $val The field value.
	 */
	protected function __construct( $field, $val ) {
		parent::__construct( $field );

		$this->type = 'boolean';
		$this->value = $this->sanitize_value( $val );
	}

	/**
	 * Render the field.
	 *
	 * @return void
	 */
	public function render() {
		echo '<div class="etch-cf-checkbox-wrapper">';
		echo '<input type="checkbox" id="' . esc_attr( $this->key ) . '" name="' . esc_attr( $this->key ) . '" value="1" class="etch-cf-checkbox" ' . ( $this->value ? 'checked' : '' ) . ' />';
		$this->render_label();
		echo '</div>';
	}

	/**
	 * Sanitizes the value from the post meta.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return bool The field value.
	 */
	public function sanitize_value( $value ): bool {
		return '1' === $value || true === $value;
	}
}
