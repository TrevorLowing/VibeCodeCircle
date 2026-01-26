<?php
/**
 * TextField.php
 *
 * This file defines the TextField class, which represents a custom field of type 'text'.
 *
 * @package Etch\CustomFields\Fields
 */

namespace Etch\CustomFields\Fields;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * Class TextField
 *
 * Represents a custom field of type 'text'.
 *
 * @phpstan-import-type CustomField from \Etch\CustomFields\CustomFieldTypes
 */
class TextField extends BaseField {
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

		$this->type = 'text';
		$this->value = $this->sanitize_value( $val );
	}

	/**
	 * Render the field.
	 *
	 * @return void
	 */
	public function render() {
		$this->render_label();
		echo '<input type="text" id="' . esc_attr( $this->key ) . '" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $this->value ) . '" class="etch-cf-input" ' . ( $this->required ? 'required' : '' ) . ' />';
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
